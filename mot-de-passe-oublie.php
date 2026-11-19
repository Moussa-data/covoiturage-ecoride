<?php
/**
 * mot-de-passe-oublie.php — Demande d'un lien de réinitialisation
 *
 * Le message affiché est TOUJOURS le même, que l'e-mail ait un compte ou non :
 * sinon n'importe qui pourrait tester quels e-mails sont inscrits.
 */
require_once __DIR__ . '/lib/init.php';

if (isLoggedIn()) {
  header('Location: /profil.php');
  exit;
}

$email    = '';
$envoye   = false;
$erreur   = '';
$lienDev  = null; // en local seulement : le lien s'affiche (pas de serveur mail)

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = trim($_POST['email'] ?? '');

  if (!csrfVerify($_POST['csrf_token'] ?? null)) {
    $erreur = 'La page a expiré. Rechargez-la puis réessayez.';
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $erreur = 'Saisissez une adresse e-mail valide.';
  } else {
    $lien = demanderReinitialisation($email);
    if (!IS_PROD) {
      $lienDev = $lien;
    }
    $envoye = true;
  }
}

$pageTitle  = 'Mot de passe oublié — EcoRide';
$activePage = '';
include __DIR__ . '/partials/header.php';
?>

<main class="container auth-page">
  <div class="auth-card">
    <h1>Mot de passe oublié</h1>

    <?php if ($envoye): ?>
      <div class="form-banner form-banner-success">
        Si un compte existe avec <strong><?= htmlspecialchars($email) ?></strong>, un e-mail vient de
        lui être envoyé avec un lien pour choisir un nouveau mot de passe. Le lien est valable 1 heure.
        Pensez à regarder dans vos spams.
      </div>
      <?php if ($lienDev): ?>
        <p class="rgpd-note">Mode local (pas d'envoi d'e-mail) : <a href="<?= htmlspecialchars($lienDev) ?>">ouvrir le lien de réinitialisation</a></p>
      <?php endif; ?>
      <p class="auth-switch"><a href="/connexion.php">Retour à la connexion</a></p>

    <?php else: ?>
      <p class="auth-subtitle">Saisissez l'e-mail de votre compte : vous recevrez un lien pour choisir un nouveau mot de passe.</p>

      <?php if ($erreur !== ''): ?>
        <div class="form-banner form-banner-error"><?= htmlspecialchars($erreur) ?></div>
      <?php endif; ?>

      <form method="POST" action="" class="auth-form">
        <?= csrfField() ?>
        <div class="form-field">
          <label for="email">Email</label>
          <input type="email" id="email" name="email" required value="<?= htmlspecialchars($email) ?>" placeholder="vous@email.com" autofocus />
        </div>
        <button type="submit" class="btn btn-primary btn-auth">Recevoir le lien</button>
      </form>

      <p class="auth-switch"><a href="/connexion.php">Retour à la connexion</a></p>
    <?php endif; ?>
  </div>
</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
