<?php
/**
 * mes-reservations.php — Liste des réservations de l'utilisateur
 */
require_once __DIR__ . '/lib/init.php';
requireLogin();

$user = currentUser();
$message = null;
$messageType = 'success';


// Annulation d'une réservation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'annuler') {
  if (!csrfVerify($_POST['csrf_token'] ?? null)) {
    $message = 'Token CSRF invalide.';
    $messageType = 'error';
  } else {
    $reservationId = (int) ($_POST['id'] ?? 0);
    if (cancelReservation($reservationId, $user['id'])) {
      $message = 'Réservation annulée. La place a été remise en circulation.';
    } else {
      $message = 'Impossible d\'annuler cette réservation.';
      $messageType = 'error';
    }
  }
}


$reservations = getReservationsByUser($user['id']);

$pageTitle  = 'Mes réservations — EcoRide';
$activePage = 'mes-reservations';
include __DIR__ . '/partials/header.php';
?>

<main class="container mes-trajets-page">

  <div class="page-header">
    <h1>Mes réservations</h1>
    <a href="/" class="btn btn-primary">Trouver un nouveau trajet</a>
  </div>

  <?php if ($message !== null): ?>
    <div class="form-banner form-banner-<?= $messageType ?>">
      <?= htmlspecialchars($message) ?>
    </div>
  <?php endif; ?>

  <?php if (empty($reservations)): ?>
    <div class="empty empty-large">
      <h2>Aucune réservation pour l'instant</h2>
      <p>Cherche un trajet et réserve ta place 🌱</p>
      <a href="/" class="btn btn-primary">Voir les trajets</a>
    </div>
  <?php else: ?>

    <p class="page-subtitle">
      <?= count($reservations) ?> réservation<?= count($reservations) > 1 ? 's' : '' ?>.
    </p>

    <div class="my-trips">
      <?php foreach ($reservations as $r): ?>
        <article class="my-trip-card">

          <div class="my-trip-info">
            <div class="my-trip-route">
              <strong><?= htmlspecialchars($r['depart']) ?> → <?= htmlspecialchars($r['arrivee']) ?></strong>
              <span class="my-trip-date">
                <?= htmlspecialchars(date('d/m/Y', strtotime($r['date']))) ?>
                · <?= htmlspecialchars($r['heure_depart']) ?> → <?= htmlspecialchars($r['heure_arrivee']) ?>
                · Conducteur : <?= htmlspecialchars($r['conducteur']['nom']) ?>
              </span>
            </div>
            <div class="my-trip-meta">
              <span class="my-trip-tag my-trip-tag-status">✓ <?= htmlspecialchars(ucfirst($r['statut'])) ?></span>
              <span class="my-trip-tag"><?= $r['prix'] ?> €</span>
              <span class="my-trip-tag my-trip-tag-eco">−<?= htmlspecialchars((string) $r['co2_economise_kg']) ?> kg CO₂</span>
            </div>
          </div>

          <div class="my-trip-actions">
            <a href="/trajet.php?id=<?= $r['id'] ?>" class="btn btn-ghost">Voir</a>

            <?php
            // Si le trajet est passé et que l'utilisateur ne l'a pas encore noté,
            // on affiche un bouton "Donner mon avis".
            $trajetPasse = $r['date'] < date('Y-m-d');
            $dejaNote    = hasUserReviewed($user['id'], $r['id']);
            ?>
            <?php if ($trajetPasse && !$dejaNote): ?>
              <a href="/donner-avis.php?id=<?= $r['id'] ?>" class="btn btn-primary">⭐ Noter</a>
            <?php elseif ($trajetPasse && $dejaNote): ?>
              <span class="my-trip-tag my-trip-tag-status">✓ Avis donné</span>
            <?php else: ?>
              <form method="POST" action="" class="inline-form" onsubmit="return confirm('Annuler cette réservation ?');">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="annuler">
                <input type="hidden" name="id" value="<?= $r['reservation_id'] ?>">
                <button type="submit" class="btn btn-danger">Annuler</button>
              </form>
            <?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
