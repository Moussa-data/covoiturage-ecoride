<?php
/**
 * mes-trajets.php — Liste des trajets publiés par l'utilisateur connecté
 *
 * Pour chaque trajet, on affiche :
 *  - Les infos principales (route, date, prix)
 *  - La liste des passagers ayant réservé (JOIN reservations + users)
 *  - Les actions : Voir, Modifier, Supprimer
 */
require_once __DIR__ . '/lib/init.php';
requireConducteur();

$user = currentUser();
$message = null;
$messageType = 'success';


// Suppression d'un trajet
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'supprimer') {
  if (!csrfVerify($_POST['csrf_token'] ?? null)) {
    $message = 'Token CSRF invalide. Réessaie.';
    $messageType = 'error';
  } else {
    $idASupprimer = (int) ($_POST['id'] ?? 0);
    if (deleteTrajet($idASupprimer, $user['id'])) {
      $message = 'Trajet supprimé.';
    } else {
      $message = 'Trajet introuvable ou tu n\'es pas le propriétaire.';
      $messageType = 'error';
    }
  }
}

// Bannière de retour de la page modifier-trajet.php
if (($_GET['modifie'] ?? '') === '1') {
  $message = 'Trajet modifié avec succès.';
  $messageType = 'success';
}


$trajets = getTrajetsByUser($user['id']);

$pageTitle  = 'Mes trajets — EcoRide';
$activePage = 'mes-trajets';
include __DIR__ . '/partials/header.php';
?>

<main class="container mes-trajets-page">

  <div class="page-header">
    <h1>Mes trajets</h1>
    <a href="/publier.php" class="btn btn-primary">+ Publier un nouveau trajet</a>
  </div>

  <?php if ($message !== null): ?>
    <div class="form-banner form-banner-<?= $messageType ?>">
      <?= htmlspecialchars($message) ?>
    </div>
  <?php endif; ?>

  <?php if (empty($trajets)): ?>
    <div class="empty empty-large">
      <h2>Tu n'as pas encore publié de trajet</h2>
      <p>Partage ton prochain déplacement pour économiser et aider la planète 🌱</p>
      <a href="/publier.php" class="btn btn-primary">Publier mon premier trajet</a>
    </div>
  <?php else: ?>

    <p class="page-subtitle">
      <?= count($trajets) ?> trajet<?= count($trajets) > 1 ? 's' : '' ?> publié<?= count($trajets) > 1 ? 's' : '' ?>.
    </p>

    <div class="my-trips">
      <?php foreach ($trajets as $t): ?>
        <?php $passagers = getPassengersForTrajet($t['id']); ?>
        <article class="my-trip-card my-trip-card-rich">

          <div class="my-trip-info">
            <div class="my-trip-route">
              <strong><?= htmlspecialchars($t['depart']) ?> → <?= htmlspecialchars($t['arrivee']) ?></strong>
              <span class="my-trip-date">
                <?= htmlspecialchars(date('d/m/Y', strtotime($t['date']))) ?>
                · <?= htmlspecialchars($t['heure_depart']) ?> → <?= htmlspecialchars($t['heure_arrivee']) ?>
                · <?= htmlspecialchars($t['duree']) ?>
              </span>
            </div>
            <div class="my-trip-meta">
              <span class="my-trip-tag"><?= $t['places_dispo'] ?> place<?= $t['places_dispo'] > 1 ? 's' : '' ?> restante<?= $t['places_dispo'] > 1 ? 's' : '' ?></span>
              <span class="my-trip-tag"><?= $t['prix'] ?> €</span>
              <span class="my-trip-tag my-trip-tag-eco">−<?= htmlspecialchars((string) $t['co2_economise_kg']) ?> kg CO₂</span>
              <span class="my-trip-tag my-trip-tag-passengers">
                👥 <?= count($passagers) ?> passager<?= count($passagers) > 1 ? 's' : '' ?>
              </span>
            </div>

            <?php if (!empty($passagers)): ?>
              <details class="passengers-section">
                <summary>Voir les passagers</summary>
                <ul class="passengers-list">
                  <?php foreach ($passagers as $p): ?>
                    <li>
                      <strong><?= htmlspecialchars($p['nom']) ?></strong>
                      <span class="passenger-email"><?= htmlspecialchars($p['email']) ?></span>
                      <span class="passenger-date">— réservé le <?= htmlspecialchars(date('d/m/Y', strtotime($p['cree_le']))) ?></span>
                    </li>
                  <?php endforeach; ?>
                </ul>
              </details>
            <?php endif; ?>
          </div>

          <div class="my-trip-actions">
            <a href="/trajet.php?id=<?= $t['id'] ?>" class="btn btn-ghost">Voir</a>
            <a href="/modifier-trajet.php?id=<?= $t['id'] ?>" class="btn btn-ghost">Modifier</a>

            <form method="POST" action="" class="inline-form" onsubmit="return confirm('Supprimer ce trajet ? Les <?= count($passagers) ?> réservation(s) seront aussi supprimées.');">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="supprimer">
              <input type="hidden" name="id" value="<?= $t['id'] ?>">
              <button type="submit" class="btn btn-danger">Supprimer</button>
            </form>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
