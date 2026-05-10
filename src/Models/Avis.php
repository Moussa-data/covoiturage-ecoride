<?php
/**
 * src/Models/Avis.php — Modèle Avis en POO
 */

namespace EcoRide\Models;

use EcoRide\Database;

class Avis
{
  /**
   * Vérifie qu'un user PEUT donner un avis sur un trajet.
   * Conditions : a réservé + trajet passé + pas déjà noté.
   */
  public static function canReview(int $userId, int $trajetId): array
  {
    if (!Database::value(
      "SELECT 1 FROM reservations WHERE user_id = :u AND trajet_id = :t AND statut = 'confirmee' LIMIT 1",
      ['u' => $userId, 't' => $trajetId]
    )) {
      return ['ok' => false, 'reason' => 'Tu n\'as pas réservé ce trajet.'];
    }

    $date = Database::value("SELECT date FROM trajets WHERE id = :id", ['id' => $trajetId]);
    if ($date === false) {
      return ['ok' => false, 'reason' => 'Trajet introuvable.'];
    }
    if ($date >= date('Y-m-d')) {
      return ['ok' => false, 'reason' => 'Tu pourras laisser un avis après la date du trajet.'];
    }

    if (self::hasReviewed($userId, $trajetId)) {
      return ['ok' => false, 'reason' => 'Tu as déjà laissé un avis sur ce trajet.'];
    }

    return ['ok' => true, 'reason' => null];
  }

  public static function hasReviewed(int $userId, int $trajetId): bool
  {
    return (bool) Database::value(
      "SELECT 1 FROM avis WHERE user_id = :u AND trajet_id = :t LIMIT 1",
      ['u' => $userId, 't' => $trajetId]
    );
  }

  /**
   * Crée un avis (vérifs incluses).
   */
  public static function create(int $userId, int $trajetId, int $note, string $commentaire): array
  {
    if ($note < 1 || $note > 5) {
      return ['ok' => false, 'error' => 'La note doit être entre 1 et 5.'];
    }
    if (mb_strlen($commentaire) > 1000) {
      return ['ok' => false, 'error' => 'Commentaire trop long (1000 caractères max).'];
    }

    $check = self::canReview($userId, $trajetId);
    if (!$check['ok']) {
      return ['ok' => false, 'error' => $check['reason']];
    }

    $conducteurUserId = Database::value(
      "SELECT user_id FROM trajets WHERE id = :id", ['id' => $trajetId]
    ) ?: null;

    Database::query(
      "INSERT INTO avis (user_id, trajet_id, conducteur_user_id, note, commentaire, cree_le)
       VALUES (:u, :t, :cu, :n, :c, :now)",
      [
        'u'   => $userId,
        't'   => $trajetId,
        'cu'  => $conducteurUserId,
        'n'   => $note,
        'c'   => trim($commentaire),
        'now' => date('c'),
      ]
    );
    return ['ok' => true, 'error' => null];
  }

  /**
   * Note moyenne d'un conducteur (avec AVG SQL).
   */
  public static function getAverageForConducteur(int $conducteurUserId): array
  {
    $row = Database::one(
      "SELECT AVG(note) AS moyenne, COUNT(*) AS nombre
       FROM avis WHERE conducteur_user_id = :u",
      ['u' => $conducteurUserId]
    );
    return [
      'moyenne' => $row['moyenne'] !== null ? round((float) $row['moyenne'], 1) : null,
      'nombre'  => (int) ($row['nombre'] ?? 0),
    ];
  }

  /**
   * Avis sur un trajet donné (avec le nom du passager via JOIN).
   */
  public static function getByTrajet(int $trajetId): array
  {
    return Database::all(
      "SELECT a.id, a.note, a.commentaire, a.cree_le, u.nom AS passager_nom
       FROM avis a INNER JOIN users u ON u.id = a.user_id
       WHERE a.trajet_id = :t
       ORDER BY a.cree_le DESC",
      ['t' => $trajetId]
    );
  }
}
