<?php
/**
 * lib/securite.php — Limite de tentatives de connexion + mot de passe oublié
 *
 * NOTIONS CLÉS :
 *
 * 1. Limite de tentatives (anti "force brute")
 *    → Un robot peut essayer des milliers de mots de passe par minute.
 *    → On note chaque échec (e-mail + IP chiffrée) et on bloque 15 minutes
 *      après 5 échecs. L'IP est stockée sous forme de hash : on ne garde pas
 *      l'adresse elle-même (RGPD), juste de quoi la reconnaître.
 *
 * 2. Jeton de réinitialisation
 *    → On envoie un lien contenant un jeton aléatoire (random_bytes).
 *    → En base on ne stocke QUE le hash du jeton : si la base fuite,
 *      personne ne peut utiliser les liens.
 *    → Le jeton expire après 1 heure et ne sert qu'une fois.
 */

const MAX_ECHECS_CONNEXION   = 5;
const BLOCAGE_MINUTES        = 15;
const RESET_VALIDITE_MINUTES = 60;


/** Crée les deux tables si elles n'existent pas encore (rétrocompatible). */
function initSecuriteSchema(PDO $pdo): void
{
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS login_echecs (
      id       INTEGER PRIMARY KEY AUTOINCREMENT,
      email    TEXT NOT NULL,
      ip_hash  TEXT NOT NULL,
      cree_le  INTEGER NOT NULL
    )
  ");
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS reset_mdp (
      id          INTEGER PRIMARY KEY AUTOINCREMENT,
      user_id     INTEGER NOT NULL,
      token_hash  TEXT NOT NULL,
      expire_le   INTEGER NOT NULL,
      utilise     INTEGER NOT NULL DEFAULT 0,
      cree_le     INTEGER NOT NULL,
      FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )
  ");
}

/** Hash de l'IP du visiteur (on ne stocke jamais l'IP en clair). */
function ipHash(): string
{
  $ip = $_SERVER['REMOTE_ADDR'] ?? 'inconnue';
  return hash('sha256', 'ecoride|' . $ip);
}


// =============================================================
// Limite de tentatives de connexion
// =============================================================

/** true si cet e-mail OU cette IP a trop d'échecs récents. */
function connexionBloquee(string $email): bool
{
  $pdo = db();
  initSecuriteSchema($pdo);

  // Ménage : on oublie les échecs de plus de 24 h
  $pdo->prepare("DELETE FROM login_echecs WHERE cree_le < :limite")
      ->execute(['limite' => time() - 86400]);

  $stmt = $pdo->prepare("
    SELECT COUNT(*) FROM login_echecs
    WHERE (email = :email OR ip_hash = :ip) AND cree_le > :depuis
  ");
  $stmt->execute([
    'email'  => strtolower(trim($email)),
    'ip'     => ipHash(),
    'depuis' => time() - BLOCAGE_MINUTES * 60,
  ]);
  return (int) $stmt->fetchColumn() >= MAX_ECHECS_CONNEXION;
}

function enregistrerEchecConnexion(string $email): void
{
  $pdo = db();
  initSecuriteSchema($pdo);
  $pdo->prepare("INSERT INTO login_echecs (email, ip_hash, cree_le) VALUES (:e, :ip, :t)")
      ->execute(['e' => strtolower(trim($email)), 'ip' => ipHash(), 't' => time()]);
}

function effacerEchecsConnexion(string $email): void
{
  $pdo = db();
  initSecuriteSchema($pdo);
  $pdo->prepare("DELETE FROM login_echecs WHERE email = :e")
      ->execute(['e' => strtolower(trim($email))]);
}


// =============================================================
// Mot de passe oublié
// =============================================================

/**
 * Crée un lien de réinitialisation et l'envoie par e-mail.
 * Renvoie le lien (utile en local pour tester sans serveur mail),
 * ou null si l'e-mail ne correspond à aucun compte.
 * La page affiche TOUJOURS le même message, pour ne pas révéler
 * quels e-mails ont un compte.
 */
function demanderReinitialisation(string $email): ?string
{
  $pdo = db();
  initSecuriteSchema($pdo);

  $user = findUserByEmail(strtolower(trim($email)));
  if ($user === null) {
    return null;
  }

  // Anti-abus : 3 demandes maximum par heure pour un même compte
  $stmt = $pdo->prepare("SELECT COUNT(*) FROM reset_mdp WHERE user_id = :u AND cree_le > :depuis");
  $stmt->execute(['u' => $user['id'], 'depuis' => time() - 3600]);
  if ((int) $stmt->fetchColumn() >= 3) {
    return null;
  }

  $token = bin2hex(random_bytes(32));
  $pdo->prepare("
    INSERT INTO reset_mdp (user_id, token_hash, expire_le, cree_le)
    VALUES (:u, :h, :exp, :t)
  ")->execute([
    'u'   => $user['id'],
    'h'   => hash('sha256', $token),
    'exp' => time() + RESET_VALIDITE_MINUTES * 60,
    't'   => time(),
  ]);

  $schema = IS_PROD ? 'https' : 'http';
  $lien   = $schema . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/reinitialiser.php?token=' . $token;

  $sujet = 'Réinitialisation de votre mot de passe EcoRide';
  $corps = "Bonjour " . $user['nom'] . ",\n\n"
         . "Vous avez demandé à réinitialiser votre mot de passe EcoRide.\n"
         . "Cliquez sur ce lien pour en choisir un nouveau (valable 1 heure) :\n\n"
         . $lien . "\n\n"
         . "Si vous n'êtes pas à l'origine de cette demande, ignorez cet e-mail : "
         . "votre mot de passe actuel reste valable.\n\n"
         . "L'équipe EcoRide";

  $domaine = preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
  $entetes = [
    'From'         => 'EcoRide <noreply@' . $domaine . '>',
    'Reply-To'     => 'm.wanecamara@gmail.com',
    'Content-Type' => 'text/plain; charset=UTF-8',
  ];

  if (IS_PROD) {
    @mail($user['email'], '=?UTF-8?B?' . base64_encode($sujet) . '?=', $corps, $entetes);
  }

  return $lien;
}

/** Renvoie l'id du compte si le jeton est valide, sinon null. */
function verifierJetonReinitialisation(string $token): ?int
{
  if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
    return null;
  }
  $pdo = db();
  initSecuriteSchema($pdo);
  $stmt = $pdo->prepare("
    SELECT user_id FROM reset_mdp
    WHERE token_hash = :h AND utilise = 0 AND expire_le > :now
    LIMIT 1
  ");
  $stmt->execute(['h' => hash('sha256', $token), 'now' => time()]);
  $userId = $stmt->fetchColumn();
  return $userId === false ? null : (int) $userId;
}

/**
 * Change le mot de passe avec un jeton valide.
 * @return array ['ok' => bool, 'errors' => array]
 */
function reinitialiserMotDePasse(string $token, string $mdp, string $confirmation): array
{
  $userId = verifierJetonReinitialisation($token);
  if ($userId === null) {
    return ['ok' => false, 'errors' => ['general' => 'Ce lien a expiré ou a déjà été utilisé. Faites une nouvelle demande.']];
  }

  $errors = [];
  if (strlen($mdp) < 8) {
    $errors['mot_de_passe'] = 'Le mot de passe doit faire au moins 8 caractères.';
  }
  if ($mdp !== $confirmation) {
    $errors['confirmation'] = 'Les deux mots de passe ne correspondent pas.';
  }
  if ($errors) {
    return ['ok' => false, 'errors' => $errors];
  }

  $pdo = db();
  $pdo->beginTransaction();
  $pdo->prepare("UPDATE users SET mot_de_passe = :m WHERE id = :id")
      ->execute(['m' => password_hash($mdp, PASSWORD_DEFAULT), 'id' => $userId]);
  // Tous les liens de ce compte deviennent inutilisables
  $pdo->prepare("UPDATE reset_mdp SET utilise = 1 WHERE user_id = :id")
      ->execute(['id' => $userId]);
  $pdo->commit();

  return ['ok' => true, 'errors' => []];
}
