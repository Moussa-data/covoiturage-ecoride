<?php
/**
 * donner-avis.php — Formulaire pour laisser un avis sur un trajet
 *
 * Affiché uniquement si :
 *  - user connecté
 *  - a réservé le trajet
 *  - trajet passé
 *  - pas déjà noté
 *
 * Le système d'étoiles est implémenté en CSS pur avec des radio buttons :
 * c'est accessible (clavier, lecteurs d'écran) et ça marche sans JS.
 */

require_once __DIR__ . '/lib/init.php';
requireLogin();

$user     = currentUser();
$trajetId = (int) ($_GET['id'] ?? 0);
$trajet   = findTrajetById($trajetId);

if ($trajet === null) {
  http_response_code(404);
  $pageTitle = 'Trajet introuvable — EcoRide';
  include __DIR__ . '/partials/header.php';
  echo '<main class="container detail-page"><div class="empty empty-large"><h1>Trajet introuvable</h1></div></main>';
  include __DIR__ . '/partials/footer.php';
  exit;
}

// Vérifie si on a le droit de noter
$check = canUserReview($user['id'], $trajetId);

$valeurs = [
  'note'        => (int) ($_POST['note'] ?? 5),
  'commentaire' => trim($_POST['commentaire'] ?? ''),
];
$erreur = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!csrfVerify($_POST['csrf_token'] ?? null)) {
    $erreur = 'Token CSRF invalide.';
  } elseif (!$check['ok']) {
    $erreur = $check['reason'];
  } else {
    $r = addAvis($user['id'], $trajetId, $valeurs['note'], $valeurs['commentaire']);
    if ($r['ok']) {
      header('Location: /trajet.php?id=' . $trajetId . '&avis=ok');
      exit;
    } else {
      $erreur = $r['error'];
    }
  }
}

$pageTitle  = 'Donner mon avis — EcoRide';
$activePage = 'mes-reservations';
include __DIR__ . '/partials/header.php';
?>

<main class="container form-page">

  <h1 class="form-title">Donner mon avis</h1>
  <p class="form-subtitle">
    <?= htmlspecialchars($trajet['depart']) ?> → <?= htmlspecialchars($trajet['arrivee']) ?>
    · <?= htmlspecialchars(date('d/m/Y', strtotime($trajet['date']))) ?>
    · Conducteur : <?= htmlspecialchars($trajet['conducteur']['nom']) ?>
  </p>

  <?php if (!$check['ok']): ?>
    <div class="form-banner form-banner-error">
      ⚠️ <?= htmlspecialchars($check['reason']) ?>
    </div>
    <p><a href="/mes-reservations.php" class="btn btn-ghost">Retour à mes réservations</a></p>
  <?php else: ?>

    <?php if ($erreur !== null): ?>
      <div class="form-banner form-banner-error">⚠️ <?= htmlspecialchars($erreur) ?></div>
    <?php endif; ?>

    <form method="POST" action="" class="form-trajet" novalidate>
      <?= csrfField() ?>

      <fieldset class="form-section">
        <legend>Ton expérience</legend>

        <div class="form-field">
          <label>Ta note</label>
          <!--
            Système d'étoiles avec radio buttons inversés en CSS.
            Les étoiles sont rendues à l'envers (5 → 1) pour qu'on
            puisse styler "cette étoile et toutes les suivantes" en hover.
          -->
          <div class="rating-stars">
            <?php for ($n = 5; $n >= 1; $n--): ?>
              <input type="radio" id="star<?= $n ?>" name="note" value="<?= $n ?>"
                     <?= $valeurs['note'] === $n ? 'checked' : '' ?> />
              <label for="star<?= $n ?>" title="<?= $n ?> étoile<?= $n > 1 ? 's' : '' ?>">★</label>
            <?php endfor; ?>
          </div>
        </div>

        <div class="form-field">
          <label for="commentaire">Ton commentaire (optionnel)</label>
          <textarea id="commentaire" name="commentaire" rows="5" placeholder="Décris brièvement ton expérience pour aider les futurs passagers..."><?= htmlspecialchars($valeurs['commentaire']) ?></textarea>
        </div>
      </fieldset>

      <div class="form-actions">
        <a href="/mes-reservations.php" class="btn btn-ghost">Annuler</a>
        <button type="submit" class="btn btn-primary">Publier mon avis</button>
      </div>
    </form>

  <?php endif; ?>
</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
