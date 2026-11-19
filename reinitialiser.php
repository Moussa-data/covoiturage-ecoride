<?php
/**
 * reinitialiser.php — Choix d'un nouveau mot de passe via le lien reçu par e-mail
 */
require_once __DIR__ . '/lib/init.php';

$token   = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
$erreurs = [];
$fait    = false;

$jetonValide = verifierJetonReinitialisation($token) !== null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $jetonValide) {
  if (!csrfVerify($_POST['csrf_token'] ?? null)) {
    $erreurs['general'] = 'La page a expiré. Rechargez-la puis réessayez.';
  } else {
    $resultat = reinitialiserMotDePasse($token, $_POST['mot_de_passe'] ?? '', $_POST['confirmation'] ?? '');
    $fait     = $resultat['ok'];
    $erreurs  = $resultat['errors'];
  }
}

$pageTitle  = 'Nouveau mot de passe — EcoRide';
$activePage = '';
include __DIR__ . '/partials/header.php';
?>

<main class="container auth-page">
  <div class="auth-card">
    <h1>Nouveau mot de passe</h1>

    <?php if ($fait): ?>
      <div class="form-banner form-banner-success">Votre mot de passe a été changé. Vous pouvez vous connecter.</div>
      <a href="/connexion.php" class="btn btn-primary btn-auth">Se connecter</a>

    <?php elseif (!$jetonValide): ?>
      <div class="form-banner form-banner-error">Ce lien a expiré ou a déjà été utilisé.</div>
      <a href="/mot-de-passe-oublie.php" class="btn btn-primary btn-auth">Recevoir un nouveau lien</a>

    <?php else: ?>
      <?php if (isset($erreurs['general'])): ?>
        <div class="form-banner form-banner-error"><?= htmlspecialchars($erreurs['general']) ?></div>
      <?php endif; ?>

      <form method="POST" action="" class="auth-form">
        <?= csrfField() ?>
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>" />

        <div class="form-field <?= isset($erreurs['mot_de_passe']) ? 'has-error' : '' ?>">
          <label for="mot_de_passe">Nouveau mot de passe</label>
          <input type="password" id="mot_de_passe" name="mot_de_passe" required minlength="8" placeholder="8 caractères minimum" />
          <?php if (isset($erreurs['mot_de_passe'])): ?><span class="error-msg"><?= htmlspecialchars($erreurs['mot_de_passe']) ?></span><?php endif; ?>
        </div>

        <div class="form-field <?= isset($erreurs['confirmation']) ? 'has-error' : '' ?>">
          <label for="confirmation">Confirmez le nouveau mot de passe</label>
          <input type="password" id="confirmation" name="confirmation" required />
          <?php if (isset($erreurs['confirmation'])): ?><span class="error-msg"><?= htmlspecialchars($erreurs['confirmation']) ?></span><?php endif; ?>
        </div>

        <button type="submit" class="btn btn-primary btn-auth">Changer le mot de passe</button>
      </form>
    <?php endif; ?>
  </div>
</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
