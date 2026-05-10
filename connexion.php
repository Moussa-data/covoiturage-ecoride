<?php
/**
 * connexion.php — Page de connexion
 */
require_once __DIR__ . '/lib/init.php';

if (isLoggedIn()) {
  header('Location: /');
  exit;
}

$valeurs = ['email' => ''];
$erreurGenerale = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $valeurs['email'] = trim($_POST['email'] ?? '');
  $motDePasse       = $_POST['mot_de_passe'] ?? '';

  if ($valeurs['email'] === '' || $motDePasse === '') {
    $erreurGenerale = 'Email et mot de passe requis.';
  } elseif (loginUser($valeurs['email'], $motDePasse)) {
    // Login réussi : on redirige vers la page initialement demandée si on en a une
    $redirect = $_SESSION['redirect_after_login'] ?? '/';
    unset($_SESSION['redirect_after_login']);
    header('Location: ' . $redirect);
    exit;
  } else {
    // Message générique pour ne pas révéler si l'email existe ou pas (sécurité)
    $erreurGenerale = 'Email ou mot de passe incorrect.';
  }
}

$pageTitle  = 'Connexion — EcoRide';
$activePage = '';
include __DIR__ . '/partials/header.php';
?>

<main class="container auth-page">
  <div class="auth-card">
    <h1>Connexion</h1>
    <p class="auth-subtitle">Bon retour parmi nous 👋</p>

    <?php if ($erreurGenerale !== ''): ?>
      <div class="form-banner form-banner-error">
        ⚠️ <?= htmlspecialchars($erreurGenerale) ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="" class="auth-form" novalidate>

      <div class="form-field">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="<?= htmlspecialchars($valeurs['email']) ?>" placeholder="ton@email.com" autofocus />
      </div>

      <div class="form-field">
        <label for="mot_de_passe">Mot de passe</label>
        <input type="password" id="mot_de_passe" name="mot_de_passe" />
      </div>

      <button type="submit" class="btn btn-primary btn-auth">Se connecter</button>
    </form>

    <p class="auth-switch">
      Pas encore de compte ? <a href="/inscription.php">Inscris-toi</a>
    </p>
  </div>
</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
