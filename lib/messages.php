<?php
/**
 * lib/messages.php — Messagerie entre passagers et conducteurs
 *
 * NOTIONS CLÉS :
 *
 * 1. MODÉLISATION
 *    Une "conversation" n'est pas une vraie entité en BDD : c'est virtuel.
 *    On définit une conversation par 3 valeurs : (trajet_id, user_a, user_b)
 *    où user_a < user_b (pour avoir une clé canonique).
 *    Tous les messages partageant ces 3 valeurs forment la conversation.
 *
 * 2. PERMISSIONS
 *    On ne peut envoyer un message que si :
 *      - on est le conducteur du trajet ET le destinataire est un passager
 *      - on est un passager (a réservé) ET le destinataire est le conducteur
 *
 * 3. STATUT "LU"
 *    Chaque message a une colonne `lu` (0/1).
 *    On marque comme lu quand le destinataire ouvre la conversation.
 *
 * 4. AGRÉGAT pour le badge
 *    SELECT COUNT(*) WHERE destinataire_id = :uid AND lu = 0
 *    → nombre de messages non lus pour le badge dans le header.
 */

require_once __DIR__ . '/db.php';


/**
 * Crée la table messages si elle n'existe pas.
 */
function initMessagesSchema(PDO $pdo): void
{
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS messages (
      id              INTEGER PRIMARY KEY AUTOINCREMENT,
      trajet_id       INTEGER NOT NULL,
      expediteur_id   INTEGER NOT NULL,
      destinataire_id INTEGER NOT NULL,
      contenu         TEXT NOT NULL,
      lu              INTEGER NOT NULL DEFAULT 0,
      cree_le         TEXT NOT NULL,

      FOREIGN KEY (trajet_id)       REFERENCES trajets(id) ON DELETE CASCADE,
      FOREIGN KEY (expediteur_id)   REFERENCES users(id)   ON DELETE CASCADE,
      FOREIGN KEY (destinataire_id) REFERENCES users(id)   ON DELETE CASCADE
    );
  ");
  // Index pour accélérer les recherches par fil de discussion
  $pdo->exec("CREATE INDEX IF NOT EXISTS idx_messages_thread ON messages(trajet_id, expediteur_id, destinataire_id);");
  $pdo->exec("CREATE INDEX IF NOT EXISTS idx_messages_destinataire ON messages(destinataire_id, lu);");
}


/**
 * Vérifie qu'un utilisateur a le droit d'écrire à un autre dans le contexte d'un trajet.
 * → soit il est le conducteur ET l'autre a réservé
 * → soit il a réservé ET l'autre est le conducteur
 */
function canMessage(int $userId, int $trajetId, int $autreUserId): bool
{
  if ($userId === $autreUserId) return false;

  // Récupère le conducteur du trajet
  $stmt = db()->prepare("SELECT user_id FROM trajets WHERE id = :id");
  $stmt->execute(['id' => $trajetId]);
  $conducteurId = (int) ($stmt->fetchColumn() ?: 0);
  if ($conducteurId === 0) return false;

  // Vérifie qui réserve. Au moins UN des deux doit être le conducteur,
  // l'autre doit être un passager qui a réservé.
  if ($userId === $conducteurId) {
    return reservationExiste($autreUserId, $trajetId);
  }
  if ($autreUserId === $conducteurId) {
    return reservationExiste($userId, $trajetId);
  }
  return false;
}

function reservationExiste(int $userId, int $trajetId): bool
{
  $stmt = db()->prepare("
    SELECT 1 FROM reservations
    WHERE user_id = :uid AND trajet_id = :tid AND statut = 'confirmee'
    LIMIT 1
  ");
  $stmt->execute(['uid' => $userId, 'tid' => $trajetId]);
  return (bool) $stmt->fetchColumn();
}


/**
 * Envoie un message.
 *
 * @return array ['ok' => bool, 'error' => string|null]
 */
function sendMessage(int $expediteurId, int $destinataireId, int $trajetId, string $contenu): array
{
  $contenu = trim($contenu);
  if ($contenu === '') {
    return ['ok' => false, 'error' => 'Le message ne peut pas être vide.'];
  }
  if (mb_strlen($contenu) > 2000) {
    return ['ok' => false, 'error' => 'Message trop long (2000 caractères max).'];
  }
  if (!canMessage($expediteurId, $trajetId, $destinataireId)) {
    return ['ok' => false, 'error' => 'Tu n\'as pas le droit d\'écrire à cette personne pour ce trajet.'];
  }

  $stmt = db()->prepare("
    INSERT INTO messages (trajet_id, expediteur_id, destinataire_id, contenu, cree_le)
    VALUES (:tid, :exp, :dest, :contenu, :cree_le)
  ");
  $stmt->execute([
    'tid'     => $trajetId,
    'exp'     => $expediteurId,
    'dest'    => $destinataireId,
    'contenu' => $contenu,
    'cree_le' => date('c'),
  ]);

  return ['ok' => true, 'error' => null];
}


/**
 * Retourne tous les messages d'un fil (peu importe qui est expéditeur).
 * Triés du plus ancien au plus récent.
 */
function getMessages(int $userId, int $autreUserId, int $trajetId): array
{
  $stmt = db()->prepare("
    SELECT m.*, u.nom AS expediteur_nom
    FROM messages m
    INNER JOIN users u ON u.id = m.expediteur_id
    WHERE m.trajet_id = :tid
      AND ((m.expediteur_id = :uid AND m.destinataire_id = :other)
        OR (m.expediteur_id = :other AND m.destinataire_id = :uid))
    ORDER BY m.cree_le ASC
  ");
  $stmt->execute(['tid' => $trajetId, 'uid' => $userId, 'other' => $autreUserId]);
  return $stmt->fetchAll();
}


/**
 * Marque comme lus tous les messages dont $userId est le destinataire,
 * dans la conversation avec $autreUserId pour le trajet $trajetId.
 */
function markMessagesAsRead(int $userId, int $autreUserId, int $trajetId): void
{
  $stmt = db()->prepare("
    UPDATE messages
    SET lu = 1
    WHERE trajet_id = :tid
      AND destinataire_id = :uid
      AND expediteur_id = :other
      AND lu = 0
  ");
  $stmt->execute(['tid' => $trajetId, 'uid' => $userId, 'other' => $autreUserId]);
}


/**
 * Liste toutes les conversations d'un utilisateur, avec :
 *  - l'autre participant
 *  - le trajet concerné
 *  - le dernier message + date
 *  - le nombre de messages non lus dans cette conversation
 *
 * Requête plus complexe car on agrège par "fil" (trajet + paire de users).
 */
function getConversations(int $userId): array
{
  // Pour grouper les messages "A→B" et "B→A" dans une seule conversation,
  // on calcule "l'autre user" : si je suis l'expéditeur, c'est le destinataire,
  // sinon c'est l'expéditeur. Avec un CASE c'est sans ambiguïté.
  //
  // (On évitait avant MIN(a,b) / MAX(a,b) car SQLite peut les interpréter
  // comme agrégats dans un GROUP BY → bug classique.)
  $stmt = db()->prepare("
    WITH fils AS (
      SELECT
        trajet_id,
        CASE WHEN expediteur_id = :uid
             THEN destinataire_id
             ELSE expediteur_id END AS autre_user_id,
        MAX(cree_le) AS dernier_message_le
      FROM messages
      WHERE (expediteur_id = :uid OR destinataire_id = :uid)
        AND expediteur_id != destinataire_id
      GROUP BY trajet_id, autre_user_id
    )
    SELECT
      f.trajet_id,
      f.dernier_message_le,
      f.autre_user_id,
      u.nom AS autre_user_nom,
      t.depart, t.arrivee, t.date,
      (SELECT contenu FROM messages
       WHERE trajet_id = f.trajet_id
         AND ((expediteur_id = :uid AND destinataire_id = f.autre_user_id)
           OR (expediteur_id = f.autre_user_id AND destinataire_id = :uid))
       ORDER BY cree_le DESC LIMIT 1) AS dernier_contenu,
      (SELECT COUNT(*) FROM messages
       WHERE trajet_id = f.trajet_id
         AND destinataire_id = :uid
         AND expediteur_id = f.autre_user_id
         AND lu = 0) AS non_lus
    FROM fils f
    INNER JOIN users u ON u.id = f.autre_user_id
    INNER JOIN trajets t ON t.id = f.trajet_id
    ORDER BY f.dernier_message_le DESC
  ");
  $stmt->execute(['uid' => $userId]);
  return $stmt->fetchAll();
}


/**
 * Compte total de messages non lus pour un user (pour le badge du header).
 */
function getUnreadCount(int $userId): int
{
  $stmt = db()->prepare("
    SELECT COUNT(*) FROM messages
    WHERE destinataire_id = :uid
      AND expediteur_id != destinataire_id
      AND lu = 0
  ");
  $stmt->execute(['uid' => $userId]);
  return (int) $stmt->fetchColumn();
}
