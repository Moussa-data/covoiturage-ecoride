<?php
/**
 * inscription.php — Création d'un compte
 */
require_once __DIR__ . '/lib/init.php';

// Si déjà connecté, pas la peine d'afficher l'inscription
if (isLoggedIn()) {
  header('Location: /');
  exit;
}

$valeurs = ['nom' => '', 'email' => ''];
$erreurs = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $valeurs['nom']   = trim($_POST['nom']   ?? '');
  $valeurs['email'] = trim($_POST['email'] ?? '');
  $motDePasse       = $_POST['mot_de_passe'] ?? '';
  $confirmation     = $_POST['confirmation'] ?? '';

  $resultat = registerUser($valeurs['nom'], $valeurs['email'], $motDePasse, $confirmation);

  if ($resultat['ok']) {
    // Inscription réussie → on connecte directement
    loginUser($valeurs['email'], $motDePasse);

    // Redirection : page demandée si on a sauvegardé une URL, sinon accueil
    $redirect = $_SESSION['redirect_after_login'] ?? '/';
    unset($_SESSION['redirect_after_login']);
    header('Location: ' . $redirect);
    exit;
  } else {
    $erreurs = $resultat['errors'];
  }
}

$pageTitle  = 'Inscription — EcoRide';
$activePage = '';
include __DIR__ . '/partials/header.php';
?>

<main class="container auth-page">
  <div class="auth-card">
    <h1>Créer ton compte EcoRide</h1>
    <p class="auth-subtitle">Rejoins la communauté du covoiturage écologique 🌱</p>

    <?php if (!empty($erreurs)): ?>
      <div class="form-banner form-banner-error">
        ⚠️ Le formulaire contient des erreurs.
      </div>
    <?php endif; ?>

    <form method="POST" action="" class="auth-form" novalidate>

      <div class="form-field <?= isset($erreurs['nom']) ? 'has-error' : '' ?>">
        <label for="nom">Nom (ou prénom + initiale)</label>
        <input type="text" id="nom" name="nom" value="<?= htmlspecialchars($valeurs['nom']) ?>" placeholder="Moussa W." />
        <?php if (isset($erreurs['nom'])): ?>
          <span class="error-msg"><?= htmlspecialchars($erreurs['nom']) ?></span>
        <?php endif; ?>
      </div>

      <div class="form-field <?= isset($erreurs['email']) ? 'has-error' : '' ?>">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="<?= htmlspecialchars($valeurs['email']) ?>" placeholder="ton@email.com" />
        <?php if (isset($erreurs['email'])): ?>
          <span class="error-msg"><?= htmlspecialchars($erreurs['email']) ?></span>
        <?php endif; ?>
      </div>

      <div class="form-field <?= isset($erreurs['mot_de_passe']) ? 'has-error' : '' ?>">
        <label for="mot_de_passe">Mot de passe</label>
        <input type="password" id="mot_de_passe" name="mot_de_passe" placeholder="8 caractères minimum" />
        <?php if (isset($erreurs['mot_de_passe'])): ?>
          <span class="error-msg"><?= htmlspecialchars($erreurs['mot_de_passe']) ?></span>
        <?php endif; ?>
      </div>

      <div class="form-field <?= isset($erreurs['confirmation']) ? 'has-error' : '' ?>">
        <label for="confirmation">Confirme ton mot de passe</label>
        <input type="password" id="confirmation" name="confirmation" />
        <?php if (isset($erreurs['confirmation'])): ?>
          <span class="error-msg"><?= htmlspecialchars($erreurs['confirmation']) ?></span>
        <?php endif; ?>
      </div>

      <button type="submit" class="btn btn-primary btn-auth">Créer mon compte</button>
    </form>

    <p class="auth-switch">
      Déjà un compte ? <a href="/connexion.php">Connecte-toi</a>
    </p>
  </div>
</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
