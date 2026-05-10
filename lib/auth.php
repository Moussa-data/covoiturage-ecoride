<?php
/**
 * lib/auth.php — Authentification (version SQL avec PDO)
 *
 * Cette version utilise SQLite au lieu d'un fichier JSON.
 * Les SIGNATURES des fonctions sont identiques à la version précédente :
 * du coup, le reste du code (inscription.php, connexion.php, etc.) ne change pas.
 * C'est la magie d'une bonne architecture.
 *
 * NOTIONS CLÉS À OBSERVER :
 *  - prepare() + execute() = requête préparée = anti-injection SQL
 *  - fetch()       → une seule ligne (la prochaine)
 *  - fetchAll()    → toutes les lignes
 *  - lastInsertId() → l'ID auto-généré du dernier INSERT
 */

require_once __DIR__ . '/db.php';


// =============================================================
// Recherche d'utilisateurs
// =============================================================

// ============================================================
// 🆕 Refacto progressive : ces fonctions sont maintenant des "façades"
// qui délèguent au modèle POO EcoRide\Models\User. Ça permet de garder
// 100% de compatibilité avec l'ancien code tout en migrant vers les classes.
// ============================================================

function findUserByEmail(string $email): ?array
{
  $user = \EcoRide\Models\User::findByEmail($email);
  return $user ? $user->toArray(includePassword: true) : null;
}

function findUserById(int $id): ?array
{
  $user = \EcoRide\Models\User::find($id);
  return $user ? $user->toArray(includePassword: true) : null;
}


// =============================================================
// Inscription
// =============================================================

function registerUser(string $nom, string $email, string $motDePasse, string $confirmation): array
{
  $errors = [];

  $nom   = trim($nom);
  $email = trim(strtolower($email));

  if ($nom === '') {
    $errors['nom'] = 'Le nom est requis.';
  } elseif (mb_strlen($nom) > 50) {
    $errors['nom'] = 'Le nom est trop long (50 caractères max).';
  }

  if ($email === '') {
    $errors['email'] = 'L\'email est requis.';
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'L\'email n\'est pas valide.';
  } elseif (findUserByEmail($email) !== null) {
    $errors['email'] = 'Un compte existe déjà avec cet email.';
  }

  if ($motDePasse === '') {
    $errors['mot_de_passe'] = 'Le mot de passe est requis.';
  } elseif (strlen($motDePasse) < 8) {
    $errors['mot_de_passe'] = 'Le mot de passe doit faire au moins 8 caractères.';
  }

  if ($motDePasse !== $confirmation) {
    $errors['confirmation'] = 'Les deux mots de passe ne correspondent pas.';
  }

  if (!empty($errors)) {
    return ['ok' => false, 'errors' => $errors, 'user' => null];
  }

  // INSERT préparé. Les :placeholders sont remplacés par PDO en sécurité.
  $stmt = db()->prepare("
    INSERT INTO users (nom, email, mot_de_passe, cree_le)
    VALUES (:nom, :email, :mdp, :cree_le)
  ");
  $stmt->execute([
    'nom'     => $nom,
    'email'   => $email,
    'mdp'     => password_hash($motDePasse, PASSWORD_DEFAULT),
    'cree_le' => date('c'),
  ]);

  // lastInsertId() = l'ID auto-généré que SQLite vient de créer
  $newId = (int) db()->lastInsertId();
  $user  = findUserById($newId);
  unset($user['mot_de_passe']);

  return ['ok' => true, 'errors' => [], 'user' => $user];
}


// =============================================================
// Connexion / déconnexion
// =============================================================

function loginUser(string $email, string $motDePasse): bool
{
  return \EcoRide\Auth::login($email, $motDePasse);
}

function logoutUser(): void
{
  \EcoRide\Auth::logout();
}


// =============================================================
// État de connexion
// =============================================================

function currentUser(): ?array
{
  $u = \EcoRide\Auth::user();
  return $u ? $u->toArray() : null;
}

function isLoggedIn(): bool
{
  return \EcoRide\Auth::check();
}

function requireLogin(): void
{
  \EcoRide\Auth::requireLogin();
}


// =============================================================
// Rôles : passager (par défaut) vs conducteur
// =============================================================

/**
 * Vérifie si l'utilisateur connecté est conducteur.
 * Un user passager peut réserver des trajets mais pas en publier.
 */
function isConducteur(): bool
{
  $user = currentUser();
  return $user !== null && !empty($user['est_conducteur']);
}

/**
 * À appeler en haut d'une page réservée aux conducteurs.
 * Si pas connecté → page connexion.
 * Si connecté mais pas conducteur → page "devenir conducteur".
 */
function requireConducteur(): void
{
  requireLogin();
  if (!isConducteur()) {
    header('Location: /devenir-conducteur.php');
    exit;
  }
}

/**
 * Active le rôle conducteur sur un compte.
 * Action irréversible côté UI mais réversible côté BDD si besoin.
 */
function becomeConducteur(int $userId): bool
{
  $stmt = db()->prepare("UPDATE users SET est_conducteur = 1 WHERE id = :id");
  $stmt->execute(['id' => $userId]);
  return $stmt->rowCount() > 0;
}


// =============================================================
// Rôle admin (super-pouvoirs)
// =============================================================

/**
 * Vérifie si l'utilisateur connecté est admin.
 * Les admins ont accès au debug, à la modération, etc.
 */
function isAdmin(): bool
{
  return \EcoRide\Auth::isAdmin();
}

function requireAdmin(): void
{
  \EcoRide\Auth::requireAdmin();
}


// =============================================================
// Mise à jour du profil
// =============================================================

/**
 * Met à jour les infos d'un utilisateur (nom, email).
 * Le mot de passe se change via changePassword().
 *
 * @return array ['ok' => bool, 'errors' => string[]]
 */
function updateUserProfile(int $userId, string $nom, string $email): array
{
  $errors = [];

  $nom   = trim($nom);
  $email = trim(strtolower($email));

  if ($nom === '') {
    $errors['nom'] = 'Le nom est requis.';
  } elseif (mb_strlen($nom) > 50) {
    $errors['nom'] = 'Le nom est trop long (50 caractères max).';
  }

  if ($email === '') {
    $errors['email'] = 'L\'email est requis.';
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'L\'email n\'est pas valide.';
  } else {
    // Email déjà pris par un AUTRE utilisateur ?
    $stmt = db()->prepare("SELECT id FROM users WHERE email = :email AND id != :id LIMIT 1");
    $stmt->execute(['email' => $email, 'id' => $userId]);
    if ($stmt->fetch()) {
      $errors['email'] = 'Cet email est déjà utilisé par un autre compte.';
    }
  }

  if (!empty($errors)) {
    return ['ok' => false, 'errors' => $errors];
  }

  $stmt = db()->prepare("UPDATE users SET nom = :nom, email = :email WHERE id = :id");
  $stmt->execute(['nom' => $nom, 'email' => $email, 'id' => $userId]);

  return ['ok' => true, 'errors' => []];
}


/**
 * Change le mot de passe d'un utilisateur (avec vérification de l'ancien).
 *
 * @return array ['ok' => bool, 'errors' => string[]]
 */
function changePassword(int $userId, string $ancienMdp, string $nouveauMdp, string $confirmation): array
{
  $errors = [];

  $user = findUserById($userId);
  if ($user === null) {
    return ['ok' => false, 'errors' => ['general' => 'Utilisateur introuvable.']];
  }

  if (!password_verify($ancienMdp, $user['mot_de_passe'])) {
    $errors['ancien'] = 'L\'ancien mot de passe est incorrect.';
  }

  if (strlen($nouveauMdp) < 8) {
    $errors['nouveau'] = 'Le nouveau mot de passe doit faire au moins 8 caractères.';
  }

  if ($nouveauMdp !== $confirmation) {
    $errors['confirmation'] = 'Les deux mots de passe ne correspondent pas.';
  }

  if (!empty($errors)) {
    return ['ok' => false, 'errors' => $errors];
  }

  $stmt = db()->prepare("UPDATE users SET mot_de_passe = :mdp WHERE id = :id");
  $stmt->execute([
    'mdp' => password_hash($nouveauMdp, PASSWORD_DEFAULT),
    'id'  => $userId,
  ]);

  return ['ok' => true, 'errors' => []];
}
