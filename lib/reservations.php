<?php
/**
 * lib/reservations.php — Gestion des réservations
 *
 * NOUVELLES NOTIONS :
 *
 * 1. Les TRANSACTIONS SQL
 *    Quand on réserve, on doit faire DEUX choses cohérentes :
 *      - INSERT INTO reservations (...)
 *      - UPDATE trajets SET places_dispo = places_dispo - 1
 *    Si la 2e échoue après la 1re, on a une réservation sans places décrémentées.
 *    La transaction garantit que les 2 réussissent OU les 2 échouent (atomicité).
 *
 *    db()->beginTransaction();
 *    try {
 *      // requêtes
 *      db()->commit();
 *    } catch (Exception $e) {
 *      db()->rollBack();
 *    }
 *
 * 2. Les RELATIONS entre tables
 *    reservations.user_id   → users.id   (qui a réservé)
 *    reservations.trajet_id → trajets.id (quel trajet)
 *
 * 3. Les CONTRAINTES MÉTIER
 *    On vérifie en PHP avant la transaction :
 *    - le user existe et n'est pas le conducteur
 *    - le trajet existe
 *    - il y a encore des places
 *    - le user n'a pas déjà réservé ce trajet
 */

require_once __DIR__ . '/db.php';


/**
 * Crée la table reservations si elle n'existe pas.
 * Appelée une fois dans db.php au premier lancement.
 */
function initReservationsSchema(PDO $pdo): void
{
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS reservations (
      id          INTEGER PRIMARY KEY AUTOINCREMENT,
      user_id     INTEGER NOT NULL,
      trajet_id   INTEGER NOT NULL,
      statut      TEXT NOT NULL DEFAULT 'confirmee',
      cree_le     TEXT NOT NULL,

      FOREIGN KEY (user_id)   REFERENCES users(id)   ON DELETE CASCADE,
      FOREIGN KEY (trajet_id) REFERENCES trajets(id) ON DELETE CASCADE,

      -- un même user ne peut pas réserver 2 fois le même trajet
      UNIQUE(user_id, trajet_id)
    );
  ");
  $pdo->exec("CREATE INDEX IF NOT EXISTS idx_reservations_user   ON reservations(user_id);");
  $pdo->exec("CREATE INDEX IF NOT EXISTS idx_reservations_trajet ON reservations(trajet_id);");
}


/**
 * Crée une réservation pour un user sur un trajet, en transaction.
 *
 * @return array ['ok' => bool, 'error' => string|null, 'reservation_id' => int|null]
 */
// 🆕 Façade vers \EcoRide\Models\Reservation::create
function addReservation(int $userId, int $trajetId, int $places = 1): array
{
  $resultat = \EcoRide\Models\Reservation::create($userId, $trajetId, $places);
  if ($resultat['ok']) {
    notifierConducteurReservation((int) $resultat['reservation_id']);
  }
  return $resultat;
}


/** La réservation d'un utilisateur sur un trajet (ou null). */
function getReservationUtilisateur(int $userId, int $trajetId): ?array
{
  $stmt = db()->prepare("SELECT id, statut, places FROM reservations WHERE user_id = :u AND trajet_id = :t LIMIT 1");
  $stmt->execute(['u' => $userId, 't' => $trajetId]);
  $r = $stmt->fetch();
  return $r ?: null;
}


/** Le conducteur accepte une demande, puis le passager est prévenu par e-mail. */
function accepterReservation(int $reservationId, int $conducteurId): array
{
  $resultat = \EcoRide\Models\Reservation::accepter($reservationId, $conducteurId);
  if ($resultat['ok']) {
    notifierPassagerReponse($reservationId, true);
  }
  return $resultat;
}


/** Le conducteur refuse une demande, puis le passager est prévenu par e-mail. */
function refuserReservation(int $reservationId, int $conducteurId): bool
{
  $ok = \EcoRide\Models\Reservation::refuser($reservationId, $conducteurId);
  if ($ok) {
    notifierPassagerReponse($reservationId, false);
  }
  return $ok;
}


/**
 * Toutes les réservations (demandes comprises) sur les trajets d'un conducteur,
 * pour la page "Mes trajets".
 */
function getReservationsPourConducteur(int $conducteurId): array
{
  $stmt = db()->prepare("
    SELECT r.id, r.trajet_id, r.statut, r.places, r.cree_le,
           u.id AS passager_id, u.nom AS passager_nom
    FROM reservations r
    INNER JOIN trajets t ON t.id = r.trajet_id
    INNER JOIN users u   ON u.id = r.user_id
    WHERE t.user_id = :c
    ORDER BY r.cree_le ASC
  ");
  $stmt->execute(['c' => $conducteurId]);
  $parTrajet = [];
  foreach ($stmt->fetchAll() as $r) {
    $parTrajet[(int) $r['trajet_id']][] = $r;
  }
  return $parTrajet;
}


// =============================================================
// Notifications par e-mail
// =============================================================

/** Infos utiles pour écrire un e-mail sur une réservation. */
function infosReservation(int $reservationId): ?array
{
  $stmt = db()->prepare("
    SELECT r.statut, r.places, t.id AS trajet_id, t.depart, t.arrivee, t.date, t.heure_depart,
           p.nom AS passager_nom, p.email AS passager_email,
           c.nom AS conducteur_nom, c.email AS conducteur_email
    FROM reservations r
    INNER JOIN trajets t ON t.id = r.trajet_id
    INNER JOIN users p   ON p.id = r.user_id
    LEFT JOIN users c    ON c.id = t.user_id
    WHERE r.id = :id
  ");
  $stmt->execute(['id' => $reservationId]);
  $r = $stmt->fetch();
  return $r ?: null;
}

function lienSite(string $chemin): string
{
  $schema = IS_PROD ? 'https' : 'http';
  return $schema . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $chemin;
}

/** Prévient le conducteur : nouvelle réservation, ou nouvelle demande à traiter. */
function notifierConducteurReservation(int $reservationId): void
{
  $r = infosReservation($reservationId);
  if (!$r || empty($r['conducteur_email']) || str_ends_with($r['conducteur_email'], '@demo.ecoride.local')) {
    return;
  }
  $trajet = $r['depart'] . ' → ' . $r['arrivee'] . ', ' . dateCourte($r['date']) . ' à ' . $r['heure_depart'];
  $places = (int) $r['places'] . ' place' . ($r['places'] > 1 ? 's' : '');

  if ($r['statut'] === 'en_attente') {
    $sujet = 'Nouvelle demande de réservation';
    $corps = "Bonjour " . $r['conducteur_nom'] . ",

"
           . $r['passager_nom'] . " demande " . $places . " sur votre trajet " . $trajet . ".

"
           . "Acceptez ou refusez la demande depuis « Mes trajets » :
" . lienSite('/mes-trajets.php') . "

"
           . "L'équipe EcoRide";
  } else {
    $sujet = 'Nouvelle réservation sur votre trajet';
    $corps = "Bonjour " . $r['conducteur_nom'] . ",

"
           . $r['passager_nom'] . " a réservé " . $places . " sur votre trajet " . $trajet . ".

"
           . "Retrouvez vos passagers dans « Mes trajets » :
" . lienSite('/mes-trajets.php') . "

"
           . "L'équipe EcoRide";
  }
  if (IS_PROD) {
    envoyerEmail($r['conducteur_email'], $sujet, $corps);
  }
}

/** Prévient le passager de la réponse du conducteur. */
function notifierPassagerReponse(int $reservationId, bool $acceptee): void
{
  $r = infosReservation($reservationId);
  if (!$r) {
    return;
  }
  $trajet = $r['depart'] . ' → ' . $r['arrivee'] . ', ' . dateCourte($r['date']) . ' à ' . $r['heure_depart'];
  if ($acceptee) {
    $sujet = 'Votre réservation est confirmée';
    $corps = "Bonjour " . $r['passager_nom'] . ",

"
           . "Bonne nouvelle : " . ($r['conducteur_nom'] ?? 'le conducteur') . " a accepté votre demande pour le trajet " . $trajet . ".

"
           . "Détails et messagerie : " . lienSite('/trajet.php?id=' . $r['trajet_id']) . "

"
           . "Bon trajet !
L'équipe EcoRide";
  } else {
    $sujet = 'Votre demande de réservation';
    $corps = "Bonjour " . $r['passager_nom'] . ",

"
           . "Le conducteur n'a pas pu accepter votre demande pour le trajet " . $trajet . ".
"
           . "D'autres trajets vous attendent peut-être : " . lienSite('/') . "

"
           . "L'équipe EcoRide";
  }
  if (IS_PROD) {
    envoyerEmail($r['passager_email'], $sujet, $corps);
  }
}


/**
 * Annule une réservation (avec restitution de la place).
 *
 * @return bool true si la réservation a été annulée
 */
function cancelReservation(int $reservationId, int $userId): bool
{
  return \EcoRide\Models\Reservation::cancel($reservationId, $userId);
}


/**
 * Retourne les réservations d'un utilisateur, jointes avec les détails du trajet.
 *
 * Cette requête contient ton premier JOIN ! Le JOIN combine deux tables
 * en une seule "ligne virtuelle" en utilisant une clé commune (ici trajet_id).
 */
function getReservationsByUser(int $userId): array
{
  $stmt = db()->prepare("
    SELECT
      r.id            AS reservation_id,
      r.statut,
      r.places        AS places_reservees,
      r.cree_le       AS reserve_le,
      t.*
    FROM reservations r
    INNER JOIN trajets t ON t.id = r.trajet_id
    WHERE r.user_id = :uid
    ORDER BY t.date DESC, t.heure_depart DESC
  ");
  $stmt->execute(['uid' => $userId]);

  // On reshape chaque ligne (qui contient les colonnes du trajet ET de la réservation)
  $resultats = [];
  foreach ($stmt->fetchAll() as $row) {
    $trajet = shapeTrajetRow($row);
    $trajet['reservation_id'] = (int) $row['reservation_id'];
    $trajet['statut']         = $row['statut'];
    $trajet['places_reservees'] = (int) ($row['places_reservees'] ?? 1);
    $trajet['reserve_le']     = $row['reserve_le'];
    $resultats[] = $trajet;
  }
  return $resultats;
}


/**
 * Vérifie si un user a déjà réservé un trajet donné.
 */
function userHasReserved(int $userId, int $trajetId): bool
{
  return \EcoRide\Models\Reservation::userHasReserved($userId, $trajetId);
}


/**
 * Compte les réservations sur un trajet donné.
 * Utile pour afficher "X passagers ont réservé" sur la page Mes trajets.
 */
function countReservationsForTrajet(int $trajetId): int
{
  $stmt = db()->prepare("
    SELECT COALESCE(SUM(places), 0) FROM reservations WHERE trajet_id = :tid AND statut = 'confirmee'
  ");
  $stmt->execute(['tid' => $trajetId]);
  return (int) $stmt->fetchColumn();
}
