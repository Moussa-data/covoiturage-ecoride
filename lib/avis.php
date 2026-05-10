<?php
/**
 * lib/avis.php — Système de notation et d'avis
 *
 * NOUVELLES NOTIONS :
 *
 * 1. Les AGRÉGATS SQL
 *    AVG(note)   → moyenne
 *    COUNT(*)    → nombre de lignes
 *    SUM(prix)   → somme
 *    MIN/MAX     → min/max
 *    Combinés avec GROUP BY, c'est extrêmement puissant pour des stats.
 *
 * 2. La CONTRAINTE UNIQUE composite
 *    UNIQUE(user_id, trajet_id) → un user ne peut donner qu'UN seul avis
 *    par trajet. La BDD elle-même refuse les doublons.
 *
 * 3. La LOGIQUE de cycle de vie
 *    On ne peut noter qu'après avoir effectivement voyagé :
 *    - le trajet doit être passé (date < aujourd'hui)
 *    - on doit avoir réservé
 *    - on ne doit pas déjà avoir noté
 */

require_once __DIR__ . '/db.php';


/**
 * Crée la table avis si elle n'existe pas.
 * Appelée depuis db() au premier lancement, comme reservations.
 */
function initAvisSchema(PDO $pdo): void
{
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS avis (
      id                 INTEGER PRIMARY KEY AUTOINCREMENT,
      user_id            INTEGER NOT NULL,           -- le passager qui note
      trajet_id          INTEGER NOT NULL,           -- le trajet noté
      conducteur_user_id INTEGER,                    -- le conducteur qui reçoit la note (peut être null si trajet importé du JSON)
      note               INTEGER NOT NULL CHECK (note BETWEEN 1 AND 5),
      commentaire        TEXT,
      cree_le            TEXT NOT NULL,

      FOREIGN KEY (user_id)            REFERENCES users(id)   ON DELETE CASCADE,
      FOREIGN KEY (trajet_id)          REFERENCES trajets(id) ON DELETE CASCADE,
      FOREIGN KEY (conducteur_user_id) REFERENCES users(id)   ON DELETE SET NULL,

      -- Un user ne peut donner qu'UN avis par trajet
      UNIQUE(user_id, trajet_id)
    );
  ");
  $pdo->exec("CREATE INDEX IF NOT EXISTS idx_avis_conducteur ON avis(conducteur_user_id);");
  $pdo->exec("CREATE INDEX IF NOT EXISTS idx_avis_trajet     ON avis(trajet_id);");
}


/**
 * Vérifie si un user PEUT donner un avis sur un trajet :
 * - il a réservé ce trajet (statut confirmee)
 * - le trajet est passé (date < aujourd'hui)
 * - il n'a pas encore donné d'avis
 *
 * @return array ['ok' => bool, 'reason' => string|null]
 */
function canUserReview(int $userId, int $trajetId): array
{
  // A-t-il réservé ?
  $stmt = db()->prepare("
    SELECT 1 FROM reservations
    WHERE user_id = :uid AND trajet_id = :tid AND statut = 'confirmee'
    LIMIT 1
  ");
  $stmt->execute(['uid' => $userId, 'tid' => $trajetId]);
  if (!$stmt->fetch()) {
    return ['ok' => false, 'reason' => 'Tu n\'as pas réservé ce trajet.'];
  }

  // Le trajet est-il passé ?
  $stmt = db()->prepare("SELECT date FROM trajets WHERE id = :id");
  $stmt->execute(['id' => $trajetId]);
  $date = $stmt->fetchColumn();
  if ($date === false) {
    return ['ok' => false, 'reason' => 'Trajet introuvable.'];
  }
  if ($date >= date('Y-m-d')) {
    return ['ok' => false, 'reason' => 'Tu pourras laisser un avis après la date du trajet.'];
  }

  // A-t-il déjà donné un avis ?
  if (hasUserReviewed($userId, $trajetId)) {
    return ['ok' => false, 'reason' => 'Tu as déjà laissé un avis sur ce trajet.'];
  }

  return ['ok' => true, 'reason' => null];
}


function hasUserReviewed(int $userId, int $trajetId): bool
{
  $stmt = db()->prepare("
    SELECT 1 FROM avis WHERE user_id = :uid AND trajet_id = :tid LIMIT 1
  ");
  $stmt->execute(['uid' => $userId, 'tid' => $trajetId]);
  return (bool) $stmt->fetchColumn();
}


/**
 * Crée un avis. Vérifie tout via canUserReview().
 *
 * @return array ['ok' => bool, 'error' => string|null]
 */
function addAvis(int $userId, int $trajetId, int $note, string $commentaire): array
{
  // Validation côté PHP (en plus du CHECK SQL)
  if ($note < 1 || $note > 5) {
    return ['ok' => false, 'error' => 'La note doit être entre 1 et 5.'];
  }
  if (mb_strlen($commentaire) > 1000) {
    return ['ok' => false, 'error' => 'Le commentaire est trop long (1000 caractères max).'];
  }

  $check = canUserReview($userId, $trajetId);
  if (!$check['ok']) {
    return ['ok' => false, 'error' => $check['reason']];
  }

  // Récupère le user_id du conducteur (pour stocker la cible de l'avis)
  $stmt = db()->prepare("SELECT user_id FROM trajets WHERE id = :id");
  $stmt->execute(['id' => $trajetId]);
  $conducteurUserId = $stmt->fetchColumn() ?: null;

  $stmt = db()->prepare("
    INSERT INTO avis (user_id, trajet_id, conducteur_user_id, note, commentaire, cree_le)
    VALUES (:uid, :tid, :cuid, :note, :com, :cree_le)
  ");
  $stmt->execute([
    'uid'     => $userId,
    'tid'     => $trajetId,
    'cuid'    => $conducteurUserId,
    'note'    => $note,
    'com'     => trim($commentaire),
    'cree_le' => date('c'),
  ]);

  return ['ok' => true, 'error' => null];
}


/**
 * Retourne la note moyenne d'un conducteur (AVG SQL) + le nombre d'avis.
 *
 * @return array ['moyenne' => float|null, 'nombre' => int]
 */
function getNoteMoyenneConducteur(int $conducteurUserId): array
{
  $stmt = db()->prepare("
    SELECT AVG(note) AS moyenne, COUNT(*) AS nombre
    FROM avis
    WHERE conducteur_user_id = :uid
  ");
  $stmt->execute(['uid' => $conducteurUserId]);
  $row = $stmt->fetch();
  return [
    'moyenne' => $row['moyenne'] !== null ? round((float) $row['moyenne'], 1) : null,
    'nombre'  => (int) $row['nombre'],
  ];
}


/**
 * Liste les avis sur un trajet donné, avec le nom de chaque passager.
 * Utilise un JOIN pour récupérer le nom de qui a noté.
 */
function getAvisByTrajet(int $trajetId): array
{
  $stmt = db()->prepare("
    SELECT a.id, a.note, a.commentaire, a.cree_le,
           u.nom AS passager_nom
    FROM avis a
    INNER JOIN users u ON u.id = a.user_id
    WHERE a.trajet_id = :tid
    ORDER BY a.cree_le DESC
  ");
  $stmt->execute(['tid' => $trajetId]);
  return $stmt->fetchAll();
}


/**
 * Liste tous les avis qu'un conducteur a reçus, à travers tous ses trajets.
 * Utile pour une future page profil publique.
 */
function getAvisByConducteur(int $conducteurUserId): array
{
  $stmt = db()->prepare("
    SELECT a.id, a.note, a.commentaire, a.cree_le,
           u.nom AS passager_nom,
           t.depart, t.arrivee, t.date
    FROM avis a
    INNER JOIN users u   ON u.id = a.user_id
    INNER JOIN trajets t ON t.id = a.trajet_id
    WHERE a.conducteur_user_id = :uid
    ORDER BY a.cree_le DESC
  ");
  $stmt->execute(['uid' => $conducteurUserId]);
  return $stmt->fetchAll();
}
