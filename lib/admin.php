<?php
/**
 * lib/admin.php — Fonctions d'administration
 *
 * Toutes les fonctions ici DOIVENT être appelées depuis une page protégée
 * par requireAdmin(). Elles ne re-vérifient pas le rôle pour rester simples.
 *
 * NOUVELLE NOTION : les AGRÉGATS multiples
 *   SELECT COUNT(*), SUM(prix), AVG(co2)
 *   FROM trajets
 *   → en une seule requête, tu as plusieurs stats. Bien plus rapide
 *     que 3 requêtes séparées.
 */

require_once __DIR__ . '/db.php';


/**
 * Statistiques globales pour le dashboard.
 * Une requête par stat — pour la lisibilité.
 */
function getAdminStats(): array
{
  $pdo = db();
  return [
    'nb_users'       => (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'nb_conducteurs' => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE est_conducteur = 1")->fetchColumn(),
    'nb_trajets'     => (int) $pdo->query("SELECT COUNT(*) FROM trajets")->fetchColumn(),
    'nb_reservations'=> (int) $pdo->query("SELECT COUNT(*) FROM reservations WHERE statut = 'confirmee'")->fetchColumn(),
    'nb_avis'        => (int) $pdo->query("SELECT COUNT(*) FROM avis")->fetchColumn(),
    'nb_messages'    => (int) $pdo->query("SELECT COUNT(*) FROM messages")->fetchColumn(),

    // Somme du CO2 économisé par les réservations confirmées
    // (on multiplie par le nb de réservations sur chaque trajet)
    'co2_total_economise' => (float) ($pdo->query("
      SELECT COALESCE(SUM(t.co2_economise_kg), 0)
      FROM reservations r
      INNER JOIN trajets t ON t.id = r.trajet_id
      WHERE r.statut = 'confirmee'
    ")->fetchColumn() ?: 0),

    // Note moyenne globale de la plateforme
    'note_moyenne' => (float) ($pdo->query("SELECT AVG(note) FROM avis")->fetchColumn() ?: 0),
  ];
}


/**
 * Liste tous les utilisateurs avec quelques stats par user.
 * Utilise des sous-requêtes corrélées pour compter les trajets/réservations de chacun.
 */
function listUsersAdmin(): array
{
  $stmt = db()->query("
    SELECT
      u.id, u.nom, u.email, u.cree_le, u.est_conducteur, u.est_admin,
      (SELECT COUNT(*) FROM trajets WHERE user_id = u.id)       AS nb_trajets,
      (SELECT COUNT(*) FROM reservations WHERE user_id = u.id)  AS nb_reservations
    FROM users u
    ORDER BY u.id ASC
  ");
  return $stmt->fetchAll();
}


/**
 * Liste tous les trajets avec le nom de leur conducteur en BDD.
 */
function listTrajetsAdmin(): array
{
  $stmt = db()->query("
    SELECT
      t.id, t.depart, t.arrivee, t.date, t.heure_depart, t.places_dispo,
      t.prix, t.user_id,
      u.nom AS conducteur_nom_user,
      (SELECT COUNT(*) FROM reservations WHERE trajet_id = t.id) AS nb_reservations
    FROM trajets t
    LEFT JOIN users u ON u.id = t.user_id
    ORDER BY t.date DESC, t.heure_depart DESC
  ");
  return $stmt->fetchAll();
}


/**
 * Liste les 20 dernières réservations.
 */
function listReservationsAdmin(int $limit = 20): array
{
  $stmt = db()->prepare("
    SELECT
      r.id, r.statut, r.cree_le,
      u.nom AS passager_nom,
      t.depart, t.arrivee, t.date
    FROM reservations r
    INNER JOIN users u ON u.id = r.user_id
    INNER JOIN trajets t ON t.id = r.trajet_id
    ORDER BY r.cree_le DESC
    LIMIT :limit
  ");
  $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
  $stmt->execute();
  return $stmt->fetchAll();
}


// =============================================================
// Actions admin (toggles + suppression)
// =============================================================

/**
 * Bascule le rôle conducteur d'un user.
 */
function adminToggleConducteur(int $userId): bool
{
  $stmt = db()->prepare("
    UPDATE users SET est_conducteur = 1 - est_conducteur WHERE id = :id
  ");
  $stmt->execute(['id' => $userId]);
  return $stmt->rowCount() > 0;
}


/**
 * Bascule le rôle admin d'un user.
 * SÉCURITÉ : empêche un admin de se dépromouvoir lui-même
 * (pour qu'il reste toujours au moins un admin).
 */
function adminToggleAdmin(int $userId, int $currentAdminId): array
{
  if ($userId === $currentAdminId) {
    return ['ok' => false, 'error' => 'Tu ne peux pas modifier ton propre rôle admin.'];
  }
  $stmt = db()->prepare("
    UPDATE users SET est_admin = 1 - est_admin WHERE id = :id
  ");
  $stmt->execute(['id' => $userId]);
  return ['ok' => $stmt->rowCount() > 0, 'error' => null];
}


/**
 * Supprime un trajet en mode admin (sans vérif de propriétaire).
 * Les réservations associées partent en cascade.
 */
function adminDeleteTrajet(int $trajetId): bool
{
  $stmt = db()->prepare("DELETE FROM trajets WHERE id = :id");
  $stmt->execute(['id' => $trajetId]);
  return $stmt->rowCount() > 0;
}


/**
 * Supprime un utilisateur (cascade sur ses trajets, réservations, etc.).
 * Empêche la suppression de soi-même.
 */
function adminDeleteUser(int $userId, int $currentAdminId): array
{
  if ($userId === $currentAdminId) {
    return ['ok' => false, 'error' => 'Tu ne peux pas supprimer ton propre compte depuis l\'admin.'];
  }
  $stmt = db()->prepare("DELETE FROM users WHERE id = :id");
  $stmt->execute(['id' => $userId]);
  return ['ok' => $stmt->rowCount() > 0, 'error' => null];
}
