<?php
/**
 * signaler.php — Les testeurs signalent un bug ou une idée.
 * Le message est enregistré (visible dans l'admin) et envoyé par e-mail à l'administrateur.
 */
require_once __DIR__ . '/lib/init.php';

$user = currentUser();

// Page d'où vient le signalement (on ne garde qu'un chemin interne, jamais une URL externe)
$page = (string) ($_POST['page'] ?? $_GET['page'] ?? '/');
if (!str_starts_with($page, '/') || str_starts_with($page, '//') || str_contains($page, '\\')) {
  $page = '/';
}

$message = '';
$retour  = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $message = (string) ($_POST['message'] ?? '');
  if (!csrfVerify($_POST['csrf_token'] ?? null)) {
    $retour = ['type' => 'error', 'text' => 'La session a expiré, rechargez la page et réessayez.'];
  } else {
    $r = signalerProbleme($user ? (int) $user['id'] : null, $page, $message);
    $retour = $r['ok']
      ? ['type' => 'success', 'text' => 'Merci ! Votre message est bien arrivé. Chaque retour aide à améliorer EcoRide.']
      : ['type' => 'error', 'text' => $r['error']];
    if ($r['ok']) {
      $message = '';
    }
  }
}

$pageTitle  = 'Signaler un problème — EcoRide';
$activePage = '';
include __DIR__ . '/partials/header.php';
?>

<main class="container form-page">
  <h1 class="form-title">Signaler un problème</h1>
  <p class="form-subtitle">Un bug, quelque chose de pas clair, une idée ? Décrivez ce que vous faisiez et ce qui s'est passé.</p>

  <?php if ($retour): ?>
    <div class="form-banner form-banner-<?= $retour['type'] ?>"><?= htmlspecialchars($retour['text']) ?></div>
  <?php endif; ?>

  <form method="POST" action="" class="form-trajet" novalidate>
    <?= csrfField() ?>
    <input type="hidden" name="page" value="<?= htmlspecialchars($page) ?>">
    <fieldset class="form-section">
      <legend>Votre message</legend>
      <div class="form-field">
        <label for="message">Que s'est-il passé ?</label>
        <textarea id="message" name="message" rows="6" maxlength="2000" required
                  placeholder="Ex. : j'ai cliqué sur « Réserver » et la page est restée blanche."><?= htmlspecialchars($message) ?></textarea>
        <span class="field-hint">Page concernée : <?= htmlspecialchars($page) ?><?= $user ? ' · envoyé au nom de ' . htmlspecialchars($user['nom']) : '' ?></span>
      </div>
    </fieldset>
    <div class="form-actions">
      <button type="submit" class="btn btn-primary">Envoyer</button>
      <a href="<?= htmlspecialchars($page) ?>" class="btn btn-ghost">Retour à la page</a>
    </div>
  </form>
</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
