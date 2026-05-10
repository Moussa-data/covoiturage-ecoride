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
function addReservation(int $userId, int $trajetId): array
{
  return \EcoRide\Models\Reservation::create($userId, $trajetId);
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
    SELECT COUNT(*) FROM reservations WHERE trajet_id = :tid
  ");
  $stmt->execute(['tid' => $trajetId]);
  return (int) $stmt->fetchColumn();
}
