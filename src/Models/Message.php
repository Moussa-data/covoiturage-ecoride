<?php
/**
 * src/Models/Message.php — Modèle Message en POO
 */

namespace EcoRide\Models;

use EcoRide\Database;

class Message
{
  /**
   * Vérifie qu'un user a le droit d'écrire à un autre dans le contexte d'un trajet.
   */
  public static function canMessage(int $userId, int $trajetId, int $autreUserId): bool
  {
    if ($userId === $autreUserId) return false;

    $conducteurId = (int) (Database::value(
      "SELECT user_id FROM trajets WHERE id = :id", ['id' => $trajetId]
    ) ?: 0);
    if ($conducteurId === 0) return false;

    if ($userId === $conducteurId) {
      return self::hasReservation($autreUserId, $trajetId);
    }
    if ($autreUserId === $conducteurId) {
      return self::hasReservation($userId, $trajetId);
    }
    return false;
  }

  private static function hasReservation(int $userId, int $trajetId): bool
  {
    return (bool) Database::value(
      "SELECT 1 FROM reservations WHERE user_id = :u AND trajet_id = :t AND statut = 'confirmee' LIMIT 1",
      ['u' => $userId, 't' => $trajetId]
    );
  }

  /**
   * Envoie un message (avec vérif d'autorisation).
   */
  public static function send(int $expediteurId, int $destinataireId, int $trajetId, string $contenu): array
  {
    $contenu = trim($contenu);
    if ($contenu === '') {
      return ['ok' => false, 'error' => 'Le message ne peut pas être vide.'];
    }
    if (mb_strlen($contenu) > 2000) {
      return ['ok' => false, 'error' => 'Message trop long (2000 caractères max).'];
    }
    if (!self::canMessage($expediteurId, $trajetId, $destinataireId)) {
      return ['ok' => false, 'error' => 'Tu n\'as pas le droit d\'écrire à cette personne pour ce trajet.'];
    }

    Database::query(
      "INSERT INTO messages (trajet_id, expediteur_id, destinataire_id, contenu, cree_le)
       VALUES (:t, :e, :d, :c, :now)",
      [
        't' => $trajetId, 'e' => $expediteurId, 'd' => $destinataireId,
        'c' => $contenu,  'now' => date('c'),
      ]
    );
    return ['ok' => true, 'error' => null];
  }

  /**
   * Tous les messages d'un fil entre 2 users sur un trajet.
   */
  public static function getThread(int $userId, int $autreUserId, int $trajetId): array
  {
    return Database::all(
      "SELECT m.*, u.nom AS expediteur_nom
       FROM messages m INNER JOIN users u ON u.id = m.expediteur_id
       WHERE m.trajet_id = :t
         AND ((m.expediteur_id = :u AND m.destinataire_id = :a)
           OR (m.expediteur_id = :a AND m.destinataire_id = :u))
       ORDER BY m.cree_le ASC",
      ['t' => $trajetId, 'u' => $userId, 'a' => $autreUserId]
    );
  }

  public static function markAsRead(int $userId, int $autreUserId, int $trajetId): void
  {
    Database::query(
      "UPDATE messages SET lu = 1
       WHERE trajet_id = :t AND destinataire_id = :u AND expediteur_id = :a AND lu = 0",
      ['t' => $trajetId, 'u' => $userId, 'a' => $autreUserId]
    );
  }

  /**
   * Liste des conversations d'un user (CTE + sous-requêtes corrélées).
   */
  public static function getConversations(int $userId): array
  {
    return Database::all(
      "WITH fils AS (
         SELECT trajet_id,
                CASE WHEN expediteur_id = :u THEN destinataire_id ELSE expediteur_id END AS autre_user_id,
                MAX(cree_le) AS dernier_message_le
         FROM messages
         WHERE (expediteur_id = :u OR destinataire_id = :u)
           AND expediteur_id != destinataire_id
         GROUP BY trajet_id, autre_user_id
       )
       SELECT f.trajet_id, f.dernier_message_le, f.autre_user_id,
              u.nom AS autre_user_nom,
              t.depart, t.arrivee, t.date,
              (SELECT contenu FROM messages
                WHERE trajet_id = f.trajet_id
                  AND ((expediteur_id = :u AND destinataire_id = f.autre_user_id)
                    OR (expediteur_id = f.autre_user_id AND destinataire_id = :u))
                ORDER BY cree_le DESC LIMIT 1) AS dernier_contenu,
              (SELECT COUNT(*) FROM messages
                WHERE trajet_id = f.trajet_id
                  AND destinataire_id = :u
                  AND expediteur_id = f.autre_user_id
                  AND lu = 0) AS non_lus
       FROM fils f
       INNER JOIN users u ON u.id = f.autre_user_id
       INNER JOIN trajets t ON t.id = f.trajet_id
       ORDER BY f.dernier_message_le DESC",
      ['u' => $userId]
    );
  }

  public static function getUnreadCount(int $userId): int
  {
    return (int) Database::value(
      "SELECT COUNT(*) FROM messages
       WHERE destinataire_id = :u
         AND expediteur_id != destinataire_id
         AND lu = 0",
      ['u' => $userId]
    );
  }
}
