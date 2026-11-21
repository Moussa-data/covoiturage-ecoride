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
      $message = 'Trajet introuvable ou vous n\'en êtes pas le propriétaire.';
      $messageType = 'error';
    }
  }
}

// Réponse du conducteur à une demande de réservation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['accepter', 'refuser'], true)) {
  if (!csrfVerify($_POST['csrf_token'] ?? null)) {
    $message = 'La page a expiré. Rechargez-la puis réessayez.';
    $messageType = 'error';
  } else {
    $reservationId = (int) ($_POST['reservation_id'] ?? 0);
    if ($_POST['action'] === 'accepter') {
      $res = accepterReservation($reservationId, $user['id']);
      $message     = $res['ok'] ? 'Demande acceptée. Le passager est prévenu par e-mail.' : $res['error'];
      $messageType = $res['ok'] ? 'success' : 'error';
    } else {
      $ok = refuserReservation($reservationId, $user['id']);
      $message     = $ok ? 'Demande refusée. Le passager est prévenu par e-mail.' : 'Demande introuvable ou déjà traitée.';
      $messageType = $ok ? 'success' : 'error';
    }
  }
}

// Bannière après la publication d'un trajet régulier
if ((int) ($_GET['regulier'] ?? 0) > 0) {
  $message = 'Trajet régulier publié : ' . (int) $_GET['regulier'] . ' dates ajoutées.';
  $messageType = 'success';
}

// Bannière de retour de la page modifier-trajet.php
if (($_GET['modifie'] ?? '') === '1') {
  $message = 'Trajet modifié avec succès.';
  $messageType = 'success';
}


$trajets          = getTrajetsByUser($user['id']);
$reservationsPar  = getReservationsPourConducteur($user['id']);

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
      <h2>Vous n'avez pas encore publié de trajet</h2>
      <p>Partagez votre prochain déplacement pour économiser et aider la planète 🌱</p>
      <a href="/publier.php" class="btn btn-primary">Publier mon premier trajet</a>
    </div>
  <?php else: ?>

    <p class="page-subtitle">
      <?= count($trajets) ?> trajet<?= count($trajets) > 1 ? 's' : '' ?> publié<?= count($trajets) > 1 ? 's' : '' ?>.
    </p>

    <div class="my-trips">
      <?php foreach ($trajets as $t): ?>
        <?php
          $resas      = $reservationsPar[$t['id']] ?? [];
          $demandes   = array_values(array_filter($resas, fn($r) => $r['statut'] === 'en_attente'));
          $confirmees = array_values(array_filter($resas, fn($r) => $r['statut'] === 'confirmee'));
          $nbPassagers = array_sum(array_map(fn($r) => (int) $r['places'], $confirmees));
        ?>
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
              <span class="my-trip-tag"><?= formatPrix((float) $t['prix']) ?> €</span>
              <span class="my-trip-tag my-trip-tag-eco">−<?= str_replace('.', ',', (string) round((float) $t['co2_economise_kg'], 1)) ?> kg CO₂</span>
              <span class="my-trip-tag my-trip-tag-passengers">
                👥 <?= $nbPassagers ?> passager<?= $nbPassagers > 1 ? 's' : '' ?>
              </span>
              <?php if (!empty($t['serie_id'])): ?>
                <span class="my-trip-tag badge-regulier">↻ Régulier</span>
              <?php endif; ?>
              <?php if (!$t['reservation_auto']): ?>
                <span class="my-trip-tag badge-demande">Sur demande</span>
              <?php endif; ?>
            </div>

            <?php if (!empty($demandes)): ?>
              <!-- Demandes à traiter : bien visibles, avec les deux boutons -->
              <div class="demandes">
                <p class="demandes-titre"><span class="pastille-attente" aria-hidden="true"></span>
                  <?= count($demandes) ?> demande<?= count($demandes) > 1 ? 's' : '' ?> à traiter</p>
                <?php foreach ($demandes as $d): ?>
                  <div class="demande">
                    <a href="/profil-public.php?id=<?= (int) $d['passager_id'] ?>" class="demande-nom"><?= htmlspecialchars($d['passager_nom']) ?></a>
                    <span class="demande-places"><?= (int) $d['places'] ?> place<?= $d['places'] > 1 ? 's' : '' ?></span>
                    <form method="POST" action="" class="inline-form">
                      <?= csrfField() ?>
                      <input type="hidden" name="reservation_id" value="<?= (int) $d['id'] ?>">
                      <button type="submit" name="action" value="accepter" class="btn btn-primary btn-petit">Accepter</button>
                      <button type="submit" name="action" value="refuser" class="btn btn-ghost btn-petit">Refuser</button>
                    </form>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

            <?php if (!empty($confirmees)): ?>
              <details class="passengers-section">
                <summary>Voir les passagers</summary>
                <ul class="passengers-list">
                  <?php foreach ($confirmees as $p): ?>
                    <li>
                      <a href="/profil-public.php?id=<?= (int) $p['passager_id'] ?>"><strong><?= htmlspecialchars($p['passager_nom']) ?></strong></a>
                      — <?= (int) $p['places'] ?> place<?= $p['places'] > 1 ? 's' : '' ?>
                      <a href="/conversation.php?trajet=<?= $t['id'] ?>&avec=<?= (int) $p['passager_id'] ?>" class="passenger-message">💬 Écrire</a>
                    </li>
                  <?php endforeach; ?>
                </ul>
              </details>
            <?php endif; ?>
          </div>

          <div class="my-trip-actions">
            <a href="/trajet.php?id=<?= $t['id'] ?>" class="btn btn-ghost">Voir</a>
            <a href="/modifier-trajet.php?id=<?= $t['id'] ?>" class="btn btn-ghost">Modifier</a>

            <form method="POST" action="" class="inline-form" onsubmit="return confirm('Supprimer ce trajet ? Les <?= count($resas) ?> réservation(s) seront aussi supprimées.');">
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
