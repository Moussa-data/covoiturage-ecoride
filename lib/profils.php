<?php
/**
 * lib/profils.php — Profils publics et vérification de l'adresse e-mail
 *
 * Vérification de l'e-mail : même principe que le mot de passe oublié.
 * On envoie un lien avec un jeton aléatoire ; en base on ne garde que son
 * hash. Quand l'utilisateur clique, users.email_verifie passe à 1 et le
 * badge « E-mail vérifié » apparaît sur son profil public.
 */

const VERIF_EMAIL_VALIDITE_JOURS = 7;


/** Crée un lien de vérification et l'envoie. Renvoie le lien (utile en local). */
function envoyerVerificationEmail(int $userId): ?string
{
  $user = findUserById($userId);
  if (!$user || !empty($user['email_verifie'])) {
    return null;
  }

  $pdo = db();
  // Anti-abus : 3 envois maximum par heure
  $stmt = $pdo->prepare("SELECT COUNT(*) FROM email_verifications WHERE user_id = :u AND cree_le > :depuis");
  $stmt->execute(['u' => $userId, 'depuis' => time() - 3600]);
  if ((int) $stmt->fetchColumn() >= 3) {
    return null;
  }

  $token = bin2hex(random_bytes(32));
  $pdo->prepare("INSERT INTO email_verifications (user_id, token_hash, expire_le, cree_le) VALUES (:u, :h, :e, :t)")
      ->execute([
        'u' => $userId,
        'h' => hash('sha256', $token),
        'e' => time() + VERIF_EMAIL_VALIDITE_JOURS * 86400,
        't' => time(),
      ]);

  $lien = lienSite('/verifier-email.php?token=' . $token);
  $corps = "Bonjour " . $user['nom'] . ",\n\n"
         . "Bienvenue sur EcoRide ! Confirmez votre adresse e-mail en cliquant sur ce lien :\n\n"
         . $lien . "\n\n"
         . "Le badge « E-mail vérifié » apparaîtra sur votre profil : les autres membres "
         . "sauront que votre compte est authentique.\n\n"
         . "L'équipe EcoRide";

  if (IS_PROD) {
    envoyerEmail($user['email'], 'Confirmez votre adresse e-mail', $corps);
  }
  return $lien;
}


/** Valide un jeton. Renvoie true si l'e-mail est maintenant vérifié. */
function verifierEmail(string $token): bool
{
  if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
    return false;
  }
  $pdo = db();
  $stmt = $pdo->prepare("SELECT user_id FROM email_verifications WHERE token_hash = :h AND expire_le > :now LIMIT 1");
  $stmt->execute(['h' => hash('sha256', $token), 'now' => time()]);
  $userId = $stmt->fetchColumn();
  if ($userId === false) {
    return false;
  }
  $pdo->prepare("UPDATE users SET email_verifie = 1 WHERE id = :id")->execute(['id' => $userId]);
  $pdo->prepare("DELETE FROM email_verifications WHERE user_id = :id")->execute(['id' => $userId]);
  return true;
}


/** Enregistre la présentation (bio) d'un utilisateur. */
function majBio(int $userId, string $bio): array
{
  $bio = trim($bio);
  if (mb_strlen($bio) > 400) {
    return ['ok' => false, 'error' => 'La présentation est trop longue (400 caractères max).'];
  }
  db()->prepare("UPDATE users SET bio = :b WHERE id = :id")->execute(['b' => $bio === '' ? null : $bio, 'id' => $userId]);
  return ['ok' => true, 'error' => null];
}


/**
 * Tout ce qu'affiche le profil public d'un membre.
 * L'adresse e-mail n'est JAMAIS renvoyée (voir la politique de confidentialité).
 */
function getProfilPublic(int $userId): ?array
{
  $stmt = db()->prepare("SELECT id, nom, cree_le, bio, email_verifie, est_conducteur, email FROM users WHERE id = :id");
  $stmt->execute(['id' => $userId]);
  $u = $stmt->fetch();
  if (!$u) {
    return null;
  }
  $estDemo = str_ends_with((string) $u['email'], '@demo.ecoride.local');
  unset($u['email']);

  $note = getNoteMoyenneConducteur($userId);

  $stmt = db()->prepare("SELECT COUNT(*) FROM trajets WHERE user_id = :id");
  $stmt->execute(['id' => $userId]);
  $nbTrajets = (int) $stmt->fetchColumn();

  $stmt = db()->prepare("
    SELECT COALESCE(SUM(r.places), 0) FROM reservations r
    INNER JOIN trajets t ON t.id = r.trajet_id
    WHERE t.user_id = :id AND r.statut = 'confirmee' AND t.date < DATE('now')
  ");
  $stmt->execute(['id' => $userId]);
  $nbPassagersTransportes = (int) $stmt->fetchColumn();

  $stmt = db()->prepare("SELECT * FROM trajets WHERE user_id = :id AND date >= DATE('now') ORDER BY date, heure_depart LIMIT 6");
  $stmt->execute(['id' => $userId]);
  $aVenir = array_map('shapeTrajetRow', $stmt->fetchAll());

  return [
    'id'             => (int) $u['id'],
    'nom'            => $u['nom'],
    'membre_depuis'  => $u['cree_le'],
    'bio'            => $u['bio'],
    'email_verifie'  => (bool) $u['email_verifie'],
    'est_conducteur' => (bool) $u['est_conducteur'],
    'est_demo'       => $estDemo,
    'note'           => $note,
    'avis'           => getAvisByConducteur($userId),
    'nb_trajets'     => $nbTrajets,
    'nb_passagers'   => $nbPassagersTransportes,
    'a_venir'        => $aVenir,
  ];
}


/** "2026-11-19T…" → "novembre 2026" */
function moisAnnee(string $date): string
{
  $mois = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
  $ts = strtotime($date) ?: time();
  return $mois[(int) date('n', $ts) - 1] . ' ' . date('Y', $ts);
}
