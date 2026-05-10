<?php
/**
 * lib/trajets.php — Gestion des trajets (version SQL avec PDO)
 *
 * Mêmes signatures de fonctions qu'avant (loadTrajets, findTrajetById, addTrajet)
 * → le reste du code marche tel quel.
 *
 * Pédago :
 *  - shapeTrajetRow() (dans db.php) reconstruit l'objet imbriqué
 *    (conducteur, preferences) à partir des colonnes plates.
 *    C'est ce qu'on appelle un "mapper" : il fait le pont entre
 *    le format BDD (relationnel, plat) et le format PHP (objet, imbriqué).
 */

require_once __DIR__ . '/db.php';


/**
 * Charge tous les trajets, triés par date puis heure de départ (les plus proches d'abord).
 */
function loadTrajets(): array
{
  $stmt = db()->query("
    SELECT *
    FROM trajets
    ORDER BY date ASC, heure_depart ASC
  ");
  $rows = $stmt->fetchAll();

  // array_map applique shapeTrajetRow sur chaque ligne
  return array_map('shapeTrajetRow', $rows);
}


/**
 * Cherche un trajet par son ID.
 */
function findTrajetById(int $id): ?array
{
  $stmt = db()->prepare("SELECT * FROM trajets WHERE id = :id LIMIT 1");
  $stmt->execute(['id' => $id]);
  $row = $stmt->fetch();
  return $row ? shapeTrajetRow($row) : null;
}


/**
 * Ajoute un nouveau trajet.
 *
 * @param array $trajet Format imbriqué (conducteur, preferences).
 * @return int L'ID auto-généré du nouveau trajet.
 */
function addTrajet(array $trajet): int
{
  // Calcul du CO2 si absent
  $co2ParType = [
    'electrique' => 18.5,
    'hybride'    => 14.0,
    'essence'    => 9.5,
  ];
  if (!isset($trajet['co2_economise_kg'])) {
    $trajet['co2_economise_kg'] = $co2ParType[$trajet['type_vehicule']] ?? 10.0;
  }

  $cond  = $trajet['conducteur']  ?? [];
  $prefs = $trajet['preferences'] ?? [];

  $stmt = db()->prepare("
    INSERT INTO trajets (
      conducteur_nom, conducteur_note, conducteur_avatar,
      conducteur_trajets_effectues, conducteur_verifie,
      depart, depart_lieu, arrivee, arrivee_lieu,
      heure_depart, heure_arrivee, duree, date,
      prix, places_dispo, type_vehicule, modele_vehicule,
      co2_economise_kg, description,
      pref_fumeur, pref_animaux, pref_musique, pref_discussion,
      user_id, cree_le
    ) VALUES (
      :c_nom, :c_note, :c_avatar,
      :c_te, :c_verif,
      :depart, :depart_lieu, :arrivee, :arrivee_lieu,
      :hd, :ha, :duree, :date,
      :prix, :places, :type_v, :modele,
      :co2, :desc,
      :pf, :pa, :pm, :pd,
      :user_id, :cree_le
    )
  ");

  $stmt->execute([
    'c_nom'        => $cond['nom'] ?? '',
    'c_note'       => $cond['note'] ?? 5.0,
    'c_avatar'     => $cond['avatar'] ?? null,
    'c_te'         => $cond['trajets_effectues'] ?? 0,
    'c_verif'      => !empty($cond['verifie']) ? 1 : 0,
    'depart'       => $trajet['depart'],
    'depart_lieu'  => $trajet['depart_lieu'],
    'arrivee'      => $trajet['arrivee'],
    'arrivee_lieu' => $trajet['arrivee_lieu'],
    'hd'           => $trajet['heure_depart'],
    'ha'           => $trajet['heure_arrivee'],
    'duree'        => $trajet['duree'] ?? null,
    'date'         => $trajet['date'],
    'prix'         => $trajet['prix'],
    'places'       => $trajet['places_dispo'],
    'type_v'       => $trajet['type_vehicule'],
    'modele'       => $trajet['modele_vehicule'] ?? null,
    'co2'          => $trajet['co2_economise_kg'],
    'desc'         => $trajet['description'] ?? null,
    'pf'           => !empty($prefs['fumeur'])  ? 1 : 0,
    'pa'           => !empty($prefs['animaux']) ? 1 : 0,
    'pm'           => isset($prefs['musique']) ? ($prefs['musique'] ? 1 : 0) : 1,
    'pd'           => $prefs['discussion'] ?? 'selon humeur',
    'user_id'      => $trajet['user_id'] ?? null,
    'cree_le'      => date('c'),
  ]);

  return (int) db()->lastInsertId();
}


/**
 * Retourne tous les trajets publiés par un utilisateur donné.
 * Triés du plus récent au plus ancien (par date de trajet).
 */
function getTrajetsByUser(int $userId): array
{
  $stmt = db()->prepare("
    SELECT *
    FROM trajets
    WHERE user_id = :uid
    ORDER BY date DESC, heure_depart DESC
  ");
  $stmt->execute(['uid' => $userId]);
  return array_map('shapeTrajetRow', $stmt->fetchAll());
}


/**
 * Supprime un trajet — UNIQUEMENT si l'utilisateur en est bien le propriétaire.
 *
 * Le AND user_id = :uid dans la requête DELETE est une SÉCURITÉ ESSENTIELLE :
 * même si quelqu'un essaie de supprimer un trajet qu'il n'a pas créé en
 * forgeant l'ID, la requête ne touchera rien (rowCount() = 0).
 *
 * @return bool true si une ligne a été supprimée
 */
function deleteTrajet(int $id, int $userId): bool
{
  $stmt = db()->prepare("
    DELETE FROM trajets
    WHERE id = :id AND user_id = :uid
  ");
  $stmt->execute(['id' => $id, 'uid' => $userId]);
  return $stmt->rowCount() > 0;
}


/**
 * Met à jour un trajet existant.
 * Le AND user_id = :uid garantit qu'on ne peut modifier que ses propres trajets.
 *
 * @return bool true si une ligne a été modifiée
 */
function updateTrajet(int $id, int $userId, array $trajet): bool
{
  $cond  = $trajet['conducteur']  ?? [];
  $prefs = $trajet['preferences'] ?? [];

  $stmt = db()->prepare("
    UPDATE trajets SET
      conducteur_nom    = :c_nom,
      depart            = :depart,
      depart_lieu       = :depart_lieu,
      arrivee           = :arrivee,
      arrivee_lieu      = :arrivee_lieu,
      heure_depart      = :hd,
      heure_arrivee     = :ha,
      duree             = :duree,
      date              = :date,
      prix              = :prix,
      places_dispo      = :places,
      type_vehicule     = :type_v,
      modele_vehicule   = :modele,
      co2_economise_kg  = :co2,
      description       = :desc,
      pref_fumeur       = :pf,
      pref_animaux      = :pa,
      pref_musique      = :pm,
      pref_discussion   = :pd
    WHERE id = :id AND user_id = :uid
  ");

  $stmt->execute([
    'c_nom'        => $cond['nom'] ?? '',
    'depart'       => $trajet['depart'],
    'depart_lieu'  => $trajet['depart_lieu'],
    'arrivee'      => $trajet['arrivee'],
    'arrivee_lieu' => $trajet['arrivee_lieu'],
    'hd'           => $trajet['heure_depart'],
    'ha'           => $trajet['heure_arrivee'],
    'duree'        => $trajet['duree'] ?? null,
    'date'         => $trajet['date'],
    'prix'         => $trajet['prix'],
    'places'       => $trajet['places_dispo'],
    'type_v'       => $trajet['type_vehicule'],
    'modele'       => $trajet['modele_vehicule'] ?? null,
    'co2'          => $trajet['co2_economise_kg'],
    'desc'         => $trajet['description'] ?? null,
    'pf'           => !empty($prefs['fumeur'])  ? 1 : 0,
    'pa'           => !empty($prefs['animaux']) ? 1 : 0,
    'pm'           => isset($prefs['musique']) ? ($prefs['musique'] ? 1 : 0) : 1,
    'pd'           => $prefs['discussion'] ?? 'selon humeur',
    'id'           => $id,
    'uid'          => $userId,
  ]);

  return $stmt->rowCount() > 0;
}


/**
 * Liste les passagers ayant réservé sur un trajet donné.
 * JOIN entre reservations et users pour récupérer le nom + email.
 */
function getPassengersForTrajet(int $trajetId): array
{
  $stmt = db()->prepare("
    SELECT u.id AS user_id, u.nom, u.email, r.id AS reservation_id, r.cree_le
    FROM reservations r
    INNER JOIN users u ON u.id = r.user_id
    WHERE r.trajet_id = :tid AND r.statut = 'confirmee'
    ORDER BY r.cree_le ASC
  ");
  $stmt->execute(['tid' => $trajetId]);
  return $stmt->fetchAll();
}


/**
 * Recherche de trajets avec filtres optionnels (départ, arrivée, date).
 * Utile pour la barre de recherche — pour l'instant pas appelé,
 * mais c'est là quand on en aura besoin.
 *
 * Exemple :
 *   $trajets = searchTrajets(['depart' => 'Paris', 'arrivee' => 'Lyon']);
 */
function searchTrajets(array $filtres = []): array
{
  $sql = "SELECT * FROM trajets WHERE 1=1"; // 1=1 = astuce pour pouvoir ajouter des AND facilement
  $params = [];

  if (!empty($filtres['depart'])) {
    $sql .= " AND LOWER(depart) LIKE :depart";
    $params['depart'] = '%' . strtolower($filtres['depart']) . '%';
  }
  if (!empty($filtres['arrivee'])) {
    $sql .= " AND LOWER(arrivee) LIKE :arrivee";
    $params['arrivee'] = '%' . strtolower($filtres['arrivee']) . '%';
  }
  if (!empty($filtres['date'])) {
    $sql .= " AND date = :date";
    $params['date'] = $filtres['date'];
  }

  $sql .= " ORDER BY date ASC, heure_depart ASC";

  $stmt = db()->prepare($sql);
  $stmt->execute($params);
  return array_map('shapeTrajetRow', $stmt->fetchAll());
}


/**
 * Décide si les stats sont assez "impressionnantes" pour être affichées.
 *
 * Pourquoi ? La preuve sociale ne fonctionne que si les chiffres sont
 * costauds. Afficher "1 conducteur, 2 trajets" fait FUIR au lieu d'attirer.
 * On affiche les stats quand AU MOINS UN critère est atteint.
 *
 * Ces seuils sont à ajuster selon la maturité du projet.
 */
function shouldShowPublicStats(array $stats): bool
{
  // Seuils "produit mature" : on n'affiche les chiffres bruts que
  // quand la plateforme a vraiment de la traction. En dessous, on
  // bascule sur du qualitatif ("rejoins la communauté").
  $SEUIL_CONDUCTEURS = 50;
  $SEUIL_TRAJETS     = 100;
  $SEUIL_AVIS        = 30;

  return $stats['conducteurs_actifs'] >= $SEUIL_CONDUCTEURS
      || $stats['trajets_dispo']     >= $SEUIL_TRAJETS
      || $stats['nb_avis']           >= $SEUIL_AVIS;
}


/**
 * Stats publiques affichées sur la page d'accueil.
 * Pas besoin d'être loggué — ces données sont publiques.
 */
function getPublicStats(): array
{
  $pdo = db();

  return [
    // Trajets à venir avec encore des places
    'trajets_dispo' => (int) $pdo->query("
      SELECT COUNT(*) FROM trajets
      WHERE date >= DATE('now') AND places_dispo > 0
    ")->fetchColumn(),

    // CO2 économisé cumulé via les réservations confirmées
    'co2_economise' => (float) ($pdo->query("
      SELECT COALESCE(SUM(t.co2_economise_kg), 0)
      FROM reservations r
      INNER JOIN trajets t ON t.id = r.trajet_id
      WHERE r.statut = 'confirmee'
    ")->fetchColumn() ?: 0),

    // Conducteurs actifs (= ont au moins 1 trajet)
    'conducteurs_actifs' => (int) $pdo->query("
      SELECT COUNT(DISTINCT user_id) FROM trajets
      WHERE user_id IS NOT NULL
    ")->fetchColumn(),

    // Note moyenne (null si aucun avis)
    'note_moyenne' => (float) ($pdo->query("SELECT AVG(note) FROM avis")->fetchColumn() ?: 0),

    // Nombre total d'avis (pour afficher la confiance)
    'nb_avis' => (int) $pdo->query("SELECT COUNT(*) FROM avis")->fetchColumn(),
  ];
}


/**
 * Calcule la durée entre deux heures (format HH:MM).
 * Ne touche pas à la BDD, on la garde ici par convention.
 */
function calculerDuree(string $depart, string $arrivee): string
{
  [$hd, $md] = array_map('intval', explode(':', $depart));
  [$ha, $ma] = array_map('intval', explode(':', $arrivee));

  $minutesDepart  = $hd * 60 + $md;
  $minutesArrivee = $ha * 60 + $ma;
  $diff = $minutesArrivee - $minutesDepart;

  if ($diff < 0) {
    $diff += 24 * 60;
  }

  $heures  = intdiv($diff, 60);
  $minutes = $diff % 60;

  return $heures . 'h' . str_pad((string) $minutes, 2, '0', STR_PAD_LEFT);
}
