<?php
/**
 * admin.php — Tableau de bord administrateur
 *
 * Sections :
 *  1. Stats globales (cards en haut)
 *  2. Utilisateurs (liste + toggles + suppression)
 *  3. Trajets (liste + suppression)
 *  4. Réservations récentes (lecture seule)
 *
 * Toutes les actions POST sont protégées par CSRF.
 */

require_once __DIR__ . '/lib/init.php';
requireAdmin();

$user = currentUser();
$message = null;
$messageType = 'success';


// =============================================================
// Traitement des actions POST
// =============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!csrfVerify($_POST['csrf_token'] ?? null)) {
    $message = 'Token CSRF invalide.';
    $messageType = 'error';
  } else {
    $action = $_POST['action'] ?? '';
    $targetId = (int) ($_POST['id'] ?? 0);

    switch ($action) {
      case 'toggle_conducteur':
        if (adminToggleConducteur($targetId)) {
          $message = 'Rôle conducteur basculé.';
        }
        break;

      case 'toggle_admin':
        $r = adminToggleAdmin($targetId, $user['id']);
        if ($r['ok']) {
          $message = 'Rôle admin basculé.';
        } else {
          $message = $r['error'];
          $messageType = 'error';
        }
        break;

      case 'delete_user':
        $r = adminDeleteUser($targetId, $user['id']);
        if ($r['ok']) {
          $message = 'Utilisateur supprimé (avec ses trajets et réservations en cascade).';
        } else {
          $message = $r['error'];
          $messageType = 'error';
        }
        break;

      case 'delete_trajet':
        if (adminDeleteTrajet($targetId)) {
          $message = 'Trajet supprimé.';
        }
        break;

      default:
        $message = 'Action inconnue.';
        $messageType = 'error';
    }
  }
}


// =============================================================
// Récupération des données pour l'affichage
// =============================================================
$stats        = getAdminStats();
$users        = listUsersAdmin();
$trajets      = listTrajetsAdmin();
$reservations = listReservationsAdmin();


$pageTitle  = 'Admin — EcoRide';
$activePage = 'admin';
include __DIR__ . '/partials/header.php';
?>

<main class="container admin-page">

  <h1>👑 Tableau de bord</h1>
  <p class="page-subtitle">Vue globale de la plateforme. Manipule avec attention 🤓.</p>

  <?php if ($message !== null): ?>
    <div class="form-banner form-banner-<?= $messageType ?>">
      <?= htmlspecialchars($message) ?>
    </div>
  <?php endif; ?>

  <!-- ============== Stats globales ============== -->
  <section class="admin-stats">
    <div class="stat-card">
      <span class="stat-label">Utilisateurs</span>
      <span class="stat-value"><?= $stats['nb_users'] ?></span>
      <span class="stat-extra">dont <?= $stats['nb_conducteurs'] ?> conducteurs</span>
    </div>
    <div class="stat-card">
      <span class="stat-label">Trajets publiés</span>
      <span class="stat-value"><?= $stats['nb_trajets'] ?></span>
    </div>
    <div class="stat-card">
      <span class="stat-label">Réservations confirmées</span>
      <span class="stat-value"><?= $stats['nb_reservations'] ?></span>
    </div>
    <div class="stat-card stat-card-eco">
      <span class="stat-label">CO₂ économisé</span>
      <span class="stat-value"><?= number_format($stats['co2_total_economise'], 1) ?> kg</span>
      <span class="stat-extra">grâce aux passagers 🌱</span>
    </div>
    <div class="stat-card">
      <span class="stat-label">Avis</span>
      <span class="stat-value"><?= $stats['nb_avis'] ?></span>
      <?php if ($stats['nb_avis'] > 0): ?>
        <span class="stat-extra">★ <?= number_format($stats['note_moyenne'], 1) ?> en moyenne</span>
      <?php endif; ?>
    </div>
    <div class="stat-card">
      <span class="stat-label">Messages</span>
      <span class="stat-value"><?= $stats['nb_messages'] ?></span>
    </div>
  </section>


  <!-- ============== Utilisateurs ============== -->
  <section class="admin-section">
    <h2>👤 Utilisateurs (<?= count($users) ?>)</h2>
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Nom</th>
            <th>Email</th>
            <th>Inscrit le</th>
            <th>Rôles</th>
            <th>Activité</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $u): ?>
            <tr <?= $u['id'] === $user['id'] ? 'class="row-self"' : '' ?>>
              <td><?= $u['id'] ?></td>
              <td><strong><?= htmlspecialchars($u['nom']) ?></strong></td>
              <td><small><?= htmlspecialchars($u['email']) ?></small></td>
              <td><small><?= htmlspecialchars(date('d/m/Y', strtotime($u['cree_le']))) ?></small></td>
              <td>
                <?php if ($u['est_admin']): ?>
                  <span class="role-tag role-tag-admin">👑 Admin</span>
                <?php endif; ?>
                <?php if ($u['est_conducteur']): ?>
                  <span class="role-tag role-tag-conducteur">🚗 Conducteur</span>
                <?php else: ?>
                  <span class="role-tag role-tag-passager">🎒 Passager</span>
                <?php endif; ?>
              </td>
              <td>
                <small><?= $u['nb_trajets'] ?> trajet<?= $u['nb_trajets'] > 1 ? 's' : '' ?> · <?= $u['nb_reservations'] ?> résa</small>
              </td>
              <td class="admin-actions">
                <form method="POST" action="" class="inline-form">
                  <?= csrfField() ?>
                  <input type="hidden" name="id" value="<?= $u['id'] ?>">
                  <button type="submit" name="action" value="toggle_conducteur" class="btn btn-ghost btn-sm" title="Basculer conducteur">
                    🚗 <?= $u['est_conducteur'] ? 'Off' : 'On' ?>
                  </button>
                </form>
                <?php if ($u['id'] !== $user['id']): ?>
                  <form method="POST" action="" class="inline-form">
                    <?= csrfField() ?>
                    <input type="hidden" name="id" value="<?= $u['id'] ?>">
                    <button type="submit" name="action" value="toggle_admin" class="btn btn-ghost btn-sm" title="Basculer admin">
                      👑 <?= $u['est_admin'] ? 'Off' : 'On' ?>
                    </button>
                  </form>
                  <form method="POST" action="" class="inline-form" onsubmit="return confirm('Supprimer définitivement <?= htmlspecialchars($u['nom']) ?> ? Tous ses trajets et réservations partiront avec.');">
                    <?= csrfField() ?>
                    <input type="hidden" name="id" value="<?= $u['id'] ?>">
                    <button type="submit" name="action" value="delete_user" class="btn btn-danger btn-sm">🗑️</button>
                  </form>
                <?php else: ?>
                  <span class="row-self-tag">toi</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>


  <!-- ============== Trajets ============== -->
  <section class="admin-section">
    <h2>🛣️ Trajets (<?= count($trajets) ?>)</h2>
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Itinéraire</th>
            <th>Date</th>
            <th>Conducteur</th>
            <th>Places</th>
            <th>Prix</th>
            <th>Résas</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($trajets as $t): ?>
            <tr>
              <td><?= $t['id'] ?></td>
              <td><strong><?= htmlspecialchars($t['depart']) ?> → <?= htmlspecialchars($t['arrivee']) ?></strong></td>
              <td><small><?= htmlspecialchars(date('d/m/Y H:i', strtotime($t['date'] . ' ' . $t['heure_depart']))) ?></small></td>
              <td>
                <?php if ($t['conducteur_nom_user']): ?>
                  <a href="#"><?= htmlspecialchars($t['conducteur_nom_user']) ?></a>
                <?php else: ?>
                  <em style="color:#b91c1c;">orphelin</em>
                <?php endif; ?>
              </td>
              <td><?= $t['places_dispo'] ?></td>
              <td><?= $t['prix'] ?> €</td>
              <td><?= $t['nb_reservations'] ?></td>
              <td>
                <form method="POST" action="" class="inline-form" onsubmit="return confirm('Supprimer ce trajet et toutes ses réservations ?');">
                  <?= csrfField() ?>
                  <input type="hidden" name="id" value="<?= $t['id'] ?>">
                  <button type="submit" name="action" value="delete_trajet" class="btn btn-danger btn-sm">🗑️</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>


  <!-- ============== Réservations récentes ============== -->
  <section class="admin-section">
    <h2>🎟️ Réservations récentes (<?= count($reservations) ?>)</h2>
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Passager</th>
            <th>Trajet</th>
            <th>Date trajet</th>
            <th>Statut</th>
            <th>Réservé le</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($reservations)): ?>
            <tr><td colspan="6" style="text-align:center; color:#6b7280;">Aucune réservation pour l'instant.</td></tr>
          <?php else: ?>
            <?php foreach ($reservations as $r): ?>
              <tr>
                <td><?= $r['id'] ?></td>
                <td><strong><?= htmlspecialchars($r['passager_nom']) ?></strong></td>
                <td><?= htmlspecialchars($r['depart']) ?> → <?= htmlspecialchars($r['arrivee']) ?></td>
                <td><small><?= htmlspecialchars(date('d/m/Y', strtotime($r['date']))) ?></small></td>
                <td><span class="role-tag role-tag-conducteur"><?= htmlspecialchars($r['statut']) ?></span></td>
                <td><small><?= htmlspecialchars(date('d/m/Y H:i', strtotime($r['cree_le']))) ?></small></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>

  <p style="text-align:center; margin-top:32px;">
    <a href="/debug.php" class="btn btn-ghost">🩺 Page de debug brute</a>
  </p>
</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
