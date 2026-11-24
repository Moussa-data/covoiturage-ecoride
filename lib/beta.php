<?php
/**
 * lib/beta.php — Tout ce qui sert à la phase de test entre amis (bêta)
 *
 *  1. Codes d'invitation personnels (un code par ami, nombre d'utilisations limité)
 *  2. Plafond de prix par kilomètre (le covoiturage doit rester un partage de frais)
 *  3. Signalements de problèmes envoyés par les testeurs
 *  4. Suppression de son propre compte (RGPD)
 */


// =============================================================
// Migration : tables et colonnes de la bêta
// =============================================================
function migrateBeta(PDO $pdo): void
{
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS invitations (
      id               INTEGER PRIMARY KEY AUTOINCREMENT,
      code             TEXT NOT NULL UNIQUE,
      invite_nom       TEXT NOT NULL,          -- à qui on a donné le code (pour s'y retrouver)
      max_utilisations INTEGER NOT NULL DEFAULT 3,
      utilisations     INTEGER NOT NULL DEFAULT 0,
      actif            INTEGER NOT NULL DEFAULT 1,
      cree_le          TEXT NOT NULL
    )
  ");
  ajouterColonneSiAbsente($pdo, 'users', 'invitation_id', 'INTEGER');
  ajouterColonneSiAbsente($pdo, 'trajets', 'distance_km', 'INTEGER');
  // Tableau de bord : date de la dernière visite d'un membre connecté
  ajouterColonneSiAbsente($pdo, 'users', 'derniere_visite', 'TEXT');

  // Tableau de bord : journal des e-mails envoyés (sans l'adresse du destinataire)
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS emails_log (
      id       INTEGER PRIMARY KEY AUTOINCREMENT,
      sujet    TEXT NOT NULL,
      ok       INTEGER NOT NULL,
      methode  TEXT,
      cree_le  TEXT NOT NULL
    )
  ");

  // Bêta : on retire les conducteurs et trajets fictifs de la démonstration
  // (comptes en @demo.ecoride.local, créés à partir du jeu de données d'origine).
  // Leurs réservations, avis et messages partent avec (clés en cascade).
  if (defined('BETA_MODE') && BETA_MODE) {
    $demo = "SELECT id FROM users WHERE email LIKE '%@demo.ecoride.local'";
    $pdo->exec("DELETE FROM trajets WHERE user_id IS NULL OR user_id IN ($demo)");
    $pdo->exec("DELETE FROM users WHERE email LIKE '%@demo.ecoride.local'");
  }

  $pdo->exec("
    CREATE TABLE IF NOT EXISTS signalements (
      id        INTEGER PRIMARY KEY AUTOINCREMENT,
      user_id   INTEGER,
      page      TEXT,
      message   TEXT NOT NULL,
      traite    INTEGER NOT NULL DEFAULT 0,
      cree_le   TEXT NOT NULL
    )
  ");
}


// =============================================================
// 1. Codes d'invitation
// =============================================================

/** Code lisible à dicter au téléphone : ECO-7KQM-3RTP (pas de 0/O ni 1/I/L). */
function genererCodeInvitation(): string
{
  $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
  $bloc = function () use ($alphabet) {
    $s = '';
    for ($i = 0; $i < 4; $i++) {
      $s .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return $s;
  };
  return 'ECO-' . $bloc() . '-' . $bloc();
}

/** Met un code saisi à la main au bon format (minuscules, espaces, tirets oubliés). */
function normaliserCodeInvitation(string $code): string
{
  $brut = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code));
  if (strlen($brut) === 11 && str_starts_with($brut, 'ECO')) {
    return 'ECO-' . substr($brut, 3, 4) . '-' . substr($brut, 7, 4);
  }
  return $brut;
}

function creerInvitation(string $inviteNom, int $maxUtilisations = 3): array
{
  $inviteNom = trim($inviteNom);
  if ($inviteNom === '' || mb_strlen($inviteNom) > 60) {
    return ['ok' => false, 'error' => 'Indiquez à qui vous donnez ce code (60 caractères max).'];
  }
  $maxUtilisations = max(1, min(20, $maxUtilisations));

  for ($essai = 0; $essai < 5; $essai++) {
    $code = genererCodeInvitation();
    try {
      db()->prepare("INSERT INTO invitations (code, invite_nom, max_utilisations, cree_le) VALUES (:c, :n, :m, :t)")
          ->execute(['c' => $code, 'n' => $inviteNom, 'm' => $maxUtilisations, 't' => date('c')]);
      return ['ok' => true, 'code' => $code];
    } catch (PDOException $e) {
      // collision très improbable sur UNIQUE : on retente avec un autre code
    }
  }
  return ['ok' => false, 'error' => 'Impossible de générer un code, réessayez.'];
}

/** Vérifie qu'un code peut encore servir. Renvoie la ligne, ou un message d'erreur. */
function verifierCodeInvitation(string $code): array
{
  $code = normaliserCodeInvitation($code);
  if ($code === '') {
    return ['ok' => false, 'error' => 'Le code d\'invitation est requis pendant la phase de test.'];
  }
  $stmt = db()->prepare("SELECT * FROM invitations WHERE code = :c");
  $stmt->execute(['c' => $code]);
  $inv = $stmt->fetch();
  if (!$inv || !$inv['actif']) {
    return ['ok' => false, 'error' => 'Ce code d\'invitation n\'est pas valide.'];
  }
  if ((int) $inv['utilisations'] >= (int) $inv['max_utilisations']) {
    return ['ok' => false, 'error' => 'Ce code a déjà été utilisé le nombre maximum de fois.'];
  }
  return ['ok' => true, 'invitation' => $inv];
}

/**
 * Consomme une utilisation du code, de façon atomique : si deux personnes
 * s'inscrivent en même temps avec la dernière place, une seule passe.
 */
function utiliserInvitation(int $invitationId, int $userId): bool
{
  $pdo = db();
  $stmt = $pdo->prepare("
    UPDATE invitations SET utilisations = utilisations + 1
    WHERE id = :id AND actif = 1 AND utilisations < max_utilisations
  ");
  $stmt->execute(['id' => $invitationId]);
  if ($stmt->rowCount() === 0) {
    return false;
  }
  $pdo->prepare("UPDATE users SET invitation_id = :i WHERE id = :u")->execute(['i' => $invitationId, 'u' => $userId]);
  return true;
}

function basculerInvitation(int $invitationId): void
{
  db()->prepare("UPDATE invitations SET actif = 1 - actif WHERE id = :id")->execute(['id' => $invitationId]);
}

/** Liste des codes avec les membres inscrits grâce à chacun. */
function listerInvitations(): array
{
  $invitations = db()->query("SELECT * FROM invitations ORDER BY id DESC")->fetchAll();
  $inscrits = db()->query("SELECT id, nom, invitation_id FROM users WHERE invitation_id IS NOT NULL ORDER BY id")->fetchAll();
  $parInvitation = [];
  foreach ($inscrits as $u) {
    $parInvitation[(int) $u['invitation_id']][] = $u;
  }
  foreach ($invitations as &$inv) {
    $inv['inscrits'] = $parInvitation[(int) $inv['id']] ?? [];
  }
  return $invitations;
}


// =============================================================
// 2. Plafond de prix (partage de frais)
// =============================================================
// Référence : le barème kilométrique fiscal (~0,60 €/km pour une voiture
// moyenne) représente le coût réel d'un km en voiture. Ce coût est partagé
// entre tous les occupants, conducteur compris. Chaque passager paie donc
// au maximum : distance × 0,60 € ÷ (places proposées + 1).
// Ainsi le conducteur ne peut jamais recevoir plus que le coût de son trajet.

const COUT_KM_REFERENCE = 0.60;
const PRIX_PLANCHER_MAX = 2.00;   // pour les tout petits trajets, on autorise jusqu'à 2 €

function prixMaxParPassager(int $distanceKm, int $places): float
{
  $places = max(1, $places);
  $max = $distanceKm * COUT_KM_REFERENCE / ($places + 1);
  return round(max($max, PRIX_PLANCHER_MAX), 2);
}

/** Message d'erreur si le prix dépasse le plafond, null sinon. */
function controlerPrix(float $prix, int $distanceKm, int $places): ?string
{
  if ($distanceKm < 1 || $distanceKm > 1500) {
    return null; // la distance a sa propre erreur
  }
  $max = prixMaxParPassager($distanceKm, $places);
  if ($prix > $max) {
    return 'Pour ' . $distanceKm . ' km et ' . $places . ' place' . ($places > 1 ? 's' : '')
         . ', le prix maximum est de ' . formatPrix($max) . ' € par passager : '
         . 'en covoiturage, on partage les frais, on ne gagne pas d\'argent.';
  }
  return null;
}


// =============================================================
// 3. Signalements des testeurs
// =============================================================
function signalerProbleme(?int $userId, string $page, string $message): array
{
  $message = trim($message);
  if (mb_strlen($message) < 5) {
    return ['ok' => false, 'error' => 'Décrivez le problème en quelques mots.'];
  }
  if (mb_strlen($message) > 2000) {
    return ['ok' => false, 'error' => 'Message trop long (2 000 caractères max).'];
  }
  // Anti-abus : 10 signalements par heure et par session
  $_SESSION['signalements'] = array_filter($_SESSION['signalements'] ?? [], fn($t) => $t > time() - 3600);
  if (count($_SESSION['signalements']) >= 10) {
    return ['ok' => false, 'error' => 'Beaucoup de signalements d\'un coup : réessayez dans un moment.'];
  }
  $_SESSION['signalements'][] = time();

  $page = mb_substr(trim($page), 0, 300);
  db()->prepare("INSERT INTO signalements (user_id, page, message, cree_le) VALUES (:u, :p, :m, :t)")
      ->execute(['u' => $userId, 'p' => $page, 'm' => $message, 't' => date('c')]);

  // Copie par e-mail à l'administrateur
  if (IS_PROD && defined('EMAIL_ADMIN')) {
    $qui = 'visiteur non connecté';
    if ($userId) {
      $u = findUserById($userId);
      $qui = $u ? $u['nom'] . ' (compte n°' . $userId . ')' : 'compte n°' . $userId;
    }
    envoyerEmail(EMAIL_ADMIN, 'Signalement bêta : ' . mb_substr($message, 0, 40),
      "Nouveau signalement sur EcoRide\n\nDe : $qui\nPage : $page\n\n$message\n\n"
      . "Tous les signalements : " . lienSite('/admin.php#signalements'));
  }
  return ['ok' => true, 'error' => null];
}

function listerSignalements(int $limite = 50): array
{
  $stmt = db()->prepare("
    SELECT s.*, u.nom AS user_nom FROM signalements s
    LEFT JOIN users u ON u.id = s.user_id
    ORDER BY s.traite ASC, s.id DESC LIMIT :l
  ");
  $stmt->bindValue('l', $limite, PDO::PARAM_INT);
  $stmt->execute();
  return $stmt->fetchAll();
}

function basculerSignalement(int $id): void
{
  db()->prepare("UPDATE signalements SET traite = 1 - traite WHERE id = :id")->execute(['id' => $id]);
}


// =============================================================
// 4. Suppression de son propre compte
// =============================================================
function supprimerMonCompte(int $userId, string $motDePasse): array
{
  $stmt = db()->prepare("SELECT mot_de_passe, est_admin FROM users WHERE id = :id");
  $stmt->execute(['id' => $userId]);
  $u = $stmt->fetch();
  if (!$u || !password_verify($motDePasse, $u['mot_de_passe'])) {
    return ['ok' => false, 'error' => 'Mot de passe incorrect.'];
  }
  if ((int) $u['est_admin'] === 1) {
    $nbAdmins = (int) db()->query("SELECT COUNT(*) FROM users WHERE est_admin = 1")->fetchColumn();
    if ($nbAdmins <= 1) {
      return ['ok' => false, 'error' => 'Vous êtes le seul administrateur : nommez-en un autre avant de supprimer votre compte.'];
    }
  }
  supprimerUtilisateurEtDonnees($userId);
  return ['ok' => true, 'error' => null];
}


/**
 * Supprime un membre et tout ce qui lui appartient.
 * Les trajets sont supprimés à la main : leur clé étrangère est en
 * « ON DELETE SET NULL » (héritage des premières versions), et un trajet
 * sans conducteur serait sinon rattaché à un faux compte par une migration.
 * Les réservations, avis et messages suivent (clés en cascade).
 */
function supprimerUtilisateurEtDonnees(int $userId): bool
{
  $pdo = db();
  $pdo->beginTransaction();
  try {
    $pdo->prepare("DELETE FROM trajets WHERE user_id = :id")->execute(['id' => $userId]);
    $pdo->prepare("UPDATE signalements SET user_id = NULL WHERE user_id = :id")->execute(['id' => $userId]);
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id");
    $stmt->execute(['id' => $userId]);
    $pdo->commit();
    return $stmt->rowCount() > 0;
  } catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
  }
}
