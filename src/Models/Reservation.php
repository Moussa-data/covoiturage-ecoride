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
   * Crée une réservation en transaction (INSERT + UPDATE places_dispo).
   * Toutes les vérifs métier sont faites avant le BEGIN.
   *
   * @return array ['ok' => bool, 'error' => string|null, 'reservation_id' => int|null]
   */
  public static function create(int $userId, int $trajetId): array
  {
    $pdo = Database::getInstance();

    // Pré-vérifs (avant la transaction)
    $trajet = Trajet::find($trajetId);
    if ($trajet === null) {
      return ['ok' => false, 'error' => 'Trajet introuvable.', 'reservation_id' => null];
    }
    if ($trajet->placesDispo <= 0) {
      return ['ok' => false, 'error' => 'Désolé, ce trajet est complet.', 'reservation_id' => null];
    }
    if ($trajet->userId === $userId) {
      return ['ok' => false, 'error' => 'Tu ne peux pas réserver ton propre trajet.', 'reservation_id' => null];
    }
    $deja = Database::value(
      "SELECT 1 FROM reservations WHERE user_id = :u AND trajet_id = :t LIMIT 1",
      ['u' => $userId, 't' => $trajetId]
    );
    if ($deja) {
      return ['ok' => false, 'error' => 'Tu as déjà réservé ce trajet.', 'reservation_id' => null];
    }

    // Transaction
    try {
      $pdo->beginTransaction();

      Database::query(
        "INSERT INTO reservations (user_id, trajet_id, statut, cree_le)
         VALUES (:u, :t, 'confirmee', :now)",
        ['u' => $userId, 't' => $trajetId, 'now' => date('c')]
      );
      $newId = (int) $pdo->lastInsertId();

      $stmt = Database::query(
        "UPDATE trajets SET places_dispo = places_dispo - 1
         WHERE id = :id AND places_dispo > 0",
        ['id' => $trajetId]
      );
      if ($stmt->rowCount() === 0) {
        throw new RuntimeException('Le trajet vient de devenir complet.');
      }

      $pdo->commit();
      return ['ok' => true, 'error' => null, 'reservation_id' => $newId];
    } catch (Exception $e) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }
      return ['ok' => false, 'error' => $e->getMessage(), 'reservation_id' => null];
    }
  }

  /**
   * Annule une réservation et restitue la place.
   */
  public static function cancel(int $reservationId, int $userId): bool
  {
    $pdo = Database::getInstance();

    $trajetId = Database::value(
      "SELECT trajet_id FROM reservations
       WHERE id = :id AND user_id = :u AND statut = 'confirmee' LIMIT 1",
      ['id' => $reservationId, 'u' => $userId]
    );
    if ($trajetId === false || $trajetId === null) {
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
      Database::query(
        "UPDATE trajets SET places_dispo = places_dispo + 1 WHERE id = :id",
        ['id' => $trajetId]
      );
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
      "SELECT r.id AS reservation_id, r.statut, r.cree_le AS reserve_le, t.*
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
