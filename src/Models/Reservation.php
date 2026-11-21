<?php
/**
 * src/Models/Reservation.php — Modèle Réservation en POO
 */

namespace EcoRide\Models;

use EcoRide\Database;
use PDO;
use Exception;
use RuntimeException;

class Reservation
{
  public int $id;
  public int $userId;
  public int $trajetId;
  public string $statut;
  public string $creeLe;

  public function __construct(array $row)
  {
    $this->id       = (int) ($row['id'] ?? 0);
    $this->userId   = (int) ($row['user_id'] ?? 0);
    $this->trajetId = (int) ($row['trajet_id'] ?? 0);
    $this->statut   = $row['statut'] ?? 'confirmee';
    $this->creeLe   = $row['cree_le'] ?? '';
  }

  /**
   * Crée une réservation de $places places.
   *
   * Deux cas selon le choix du conducteur (trajets.reservation_auto) :
   *  - réservation immédiate (1) : statut 'confirmee', les places sont
   *    retirées tout de suite, dans une transaction ;
   *  - réservation sur demande (0) : statut 'en_attente', les places ne
   *    sont retirées que quand le conducteur accepte (voir accepter()).
   *
   * @return array ['ok' => bool, 'error' => string|null, 'reservation_id' => int|null, 'statut' => string|null]
   */
  public static function create(int $userId, int $trajetId, int $places = 1): array
  {
    $pdo = Database::getInstance();
    $echec = fn(string $msg) => ['ok' => false, 'error' => $msg, 'reservation_id' => null, 'statut' => null];

    $places = max(1, min(4, $places));

    // Pré-vérifs (avant la transaction)
    $trajet = Trajet::find($trajetId);
    if ($trajet === null) {
      return $echec('Trajet introuvable.');
    }
    if ($trajet->placesDispo <= 0) {
      return $echec('Désolé, ce trajet est complet.');
    }
    if ($trajet->placesDispo < $places) {
      return $echec('Il ne reste que ' . $trajet->placesDispo . ' place(s) sur ce trajet.');
    }
    if ($trajet->userId === $userId) {
      return $echec('Vous ne pouvez pas réserver votre propre trajet.');
    }
    $deja = Database::value(
      "SELECT 1 FROM reservations WHERE user_id = :u AND trajet_id = :t LIMIT 1",
      ['u' => $userId, 't' => $trajetId]
    );
    if ($deja) {
      return $echec('Vous avez déjà réservé ce trajet.');
    }

    $auto = (int) Database::value("SELECT reservation_auto FROM trajets WHERE id = :id", ['id' => $trajetId]) === 1;
    $statut = $auto ? 'confirmee' : 'en_attente';

    try {
      $pdo->beginTransaction();

      Database::query(
        "INSERT INTO reservations (user_id, trajet_id, statut, places, cree_le)
         VALUES (:u, :t, :statut, :places, :now)",
        ['u' => $userId, 't' => $trajetId, 'statut' => $statut, 'places' => $places, 'now' => date('c')]
      );
      $newId = (int) $pdo->lastInsertId();

      if ($auto) {
        // La condition "places_dispo >= :places" protège contre deux
        // réservations simultanées sur les dernières places.
        $stmt = Database::query(
          "UPDATE trajets SET places_dispo = places_dispo - :places
           WHERE id = :id AND places_dispo >= :places",
          ['id' => $trajetId, 'places' => $places]
        );
        if ($stmt->rowCount() === 0) {
          throw new RuntimeException('Le trajet vient de devenir complet.');
        }
      }

      $pdo->commit();
      return ['ok' => true, 'error' => null, 'reservation_id' => $newId, 'statut' => $statut];
    } catch (Exception $e) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }
      return $echec($e->getMessage());
    }
  }

  /**
   * Le conducteur accepte une demande : les places sont retirées du trajet.
   * @return array ['ok' => bool, 'error' => string|null]
   */
  public static function accepter(int $reservationId, int $conducteurId): array
  {
    $pdo = Database::getInstance();
    $r = Database::one(
      "SELECT r.id, r.trajet_id, r.places, r.statut
       FROM reservations r INNER JOIN trajets t ON t.id = r.trajet_id
       WHERE r.id = :id AND t.user_id = :c LIMIT 1",
      ['id' => $reservationId, 'c' => $conducteurId]
    );
    if (!$r || $r['statut'] !== 'en_attente') {
      return ['ok' => false, 'error' => 'Demande introuvable ou déjà traitée.'];
    }

    try {
      $pdo->beginTransaction();
      $stmt = Database::query(
        "UPDATE trajets SET places_dispo = places_dispo - :p WHERE id = :t AND places_dispo >= :p",
        ['p' => (int) $r['places'], 't' => (int) $r['trajet_id']]
      );
      if ($stmt->rowCount() === 0) {
        throw new RuntimeException('Il ne reste plus assez de places pour accepter cette demande.');
      }
      Database::query("UPDATE reservations SET statut = 'confirmee' WHERE id = :id", ['id' => $reservationId]);
      $pdo->commit();
      return ['ok' => true, 'error' => null];
    } catch (Exception $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      return ['ok' => false, 'error' => $e->getMessage()];
    }
  }

  /** Le conducteur refuse une demande (aucune place n'avait été retirée). */
  public static function refuser(int $reservationId, int $conducteurId): bool
  {
    $stmt = Database::query(
      "UPDATE reservations SET statut = 'refusee'
       WHERE id = :id AND statut = 'en_attente'
         AND trajet_id IN (SELECT id FROM trajets WHERE user_id = :c)",
      ['id' => $reservationId, 'c' => $conducteurId]
    );
    return $stmt->rowCount() > 0;
  }

  /**
   * Annule une réservation (par le passager).
   * Si elle était confirmée, ses places sont rendues au trajet.
   */
  public static function cancel(int $reservationId, int $userId): bool
  {
    $pdo = Database::getInstance();

    $r = Database::one(
      "SELECT trajet_id, statut, places FROM reservations
       WHERE id = :id AND user_id = :u AND statut IN ('confirmee', 'en_attente') LIMIT 1",
      ['id' => $reservationId, 'u' => $userId]
    );
    if (!$r) {
      return false;
    }

    try {
      $pdo->beginTransaction();
      $stmt = Database::query(
        "DELETE FROM reservations WHERE id = :id AND user_id = :u",
        ['id' => $reservationId, 'u' => $userId]
      );
      if ($stmt->rowCount() === 0) {
        throw new RuntimeException('Réservation introuvable.');
      }
      if ($r['statut'] === 'confirmee') {
        Database::query(
          "UPDATE trajets SET places_dispo = places_dispo + :p WHERE id = :id",
          ['id' => (int) $r['trajet_id'], 'p' => (int) $r['places']]
        );
      }
      $pdo->commit();
      return true;
    } catch (Exception $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      return false;
    }
  }

  /**
   * Réservations d'un user, jointes avec le trajet (pour Mes réservations).
   */
  public static function findByUserWithTrajet(int $userId): array
  {
    return Database::all(
      "SELECT r.id AS reservation_id, r.statut, r.places AS places_reservees, r.cree_le AS reserve_le, t.*
       FROM reservations r
       INNER JOIN trajets t ON t.id = r.trajet_id
       WHERE r.user_id = :u
       ORDER BY t.date DESC, t.heure_depart DESC",
      ['u' => $userId]
    );
  }

  public static function userHasReserved(int $userId, int $trajetId): bool
  {
    return (bool) Database::value(
      "SELECT 1 FROM reservations WHERE user_id = :u AND trajet_id = :t LIMIT 1",
      ['u' => $userId, 't' => $trajetId]
    );
  }
}
