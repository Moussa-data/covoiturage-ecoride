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

      case 'creer_invitation':
        $r = creerInvitation($_POST['invite_nom'] ?? '', (int) ($_POST['max_utilisations'] ?? 3));
        if ($r['ok']) {
          $message = 'Code créé : ' . $r['code'] . '. Lien à envoyer : ' . lienSite('/inscription.php?code=' . $r['code']);
        } else {
          $message = $r['error'];
          $messageType = 'error';
        }
        break;

      case 'basculer_invitation':
        basculerInvitation($targetId);
        $message = 'Code activé / désactivé.';
        break;

      case 'basculer_signalement':
        basculerSignalement($targetId);
        $message = 'Signalement mis à jour.';
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
$invitations  = listerInvitations();
$signalements = listerSignalements();
$nbSignalementsOuverts = count(array_filter($signalements, fn($s) => !$s['traite']));


$pageTitle  = 'Admin — EcoRide';
$activePage = 'admin';
include __DIR__ . '/partials/header.php';
?>

<main class="container admin-page">

  <h1>👑 Tableau de bord</h1>
  <p class="page-subtitle">Vue globale de la plateforme. Manipule avec attention 🤓.</p>
  <p><a href="/diagnostic-email.php" class="btn btn-ghost">✉️ Tester l'envoi d'e-mails</a></p>

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


  <!-- ============== Bêta : codes d'invitation ============== -->
  <section class="admin-section" id="invitations">
    <h2>🎟️ Codes d'invitation (<?= count($invitations) ?>)</h2>
    <p class="admin-aide">Un code par ami. Envoyez-lui le lien : son code sera déjà rempli à l'inscription. Désactivez un code s'il circule trop.</p>

    <form method="POST" action="" class="admin-form-invitation">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="creer_invitation">
      <div class="form-field">
        <label for="invite_nom">Pour qui ?</label>
        <input type="text" id="invite_nom" name="invite_nom" maxlength="60" required placeholder="Ex. : Karim (collègue)">
      </div>
      <div class="form-field">
        <label for="max_utilisations">Inscriptions possibles</label>
        <select id="max_utilisations" name="max_utilisations">
          <option value="1">1 (la personne seule)</option>
          <option value="3" selected>3 (elle + 2 proches)</option>
          <option value="5">5</option>
          <option value="10">10</option>
        </select>
      </div>
      <button type="submit" class="btn btn-primary">Créer le code</button>
    </form>

    <?php if (!empty($invitations)): ?>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <tr><th>Code</th><th>Pour</th><th>Utilisé</th><th>Inscrits avec ce code</th><th>Lien d'invitation</th><th>État</th></tr>
          </thead>
          <tbody>
            <?php foreach ($invitations as $inv): $lien = lienSite('/inscription.php?code=' . $inv['code']); ?>
              <tr class="<?= $inv['actif'] ? '' : 'row-inactif' ?>">
                <td><code class="code-invitation"><?= htmlspecialchars($inv['code']) ?></code></td>
                <td><?= htmlspecialchars($inv['invite_nom']) ?></td>
                <td><?= (int) $inv['utilisations'] ?> / <?= (int) $inv['max_utilisations'] ?></td>
                <td>
                  <?php if (empty($inv['inscrits'])): ?>
                    <small>personne pour l'instant</small>
                  <?php else: ?>
                    <small><?= htmlspecialchars(implode(', ', array_column($inv['inscrits'], 'nom'))) ?></small>
                  <?php endif; ?>
                </td>
                <td>
                  <button type="button" class="btn btn-ghost btn-sm btn-copier" data-copier="<?= htmlspecialchars($lien) ?>">Copier le lien</button>
                </td>
                <td>
                  <form method="POST" action="" class="inline-form">
                    <?= csrfField() ?>
                    <input type="hidden" name="id" value="<?= (int) $inv['id'] ?>">
                    <button type="submit" name="action" value="basculer_invitation" class="btn btn-ghost btn-sm">
                      <?= $inv['actif'] ? 'Actif · désactiver' : 'Désactivé · réactiver' ?>
                    </button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>


  <!-- ============== Bêta : signalements des testeurs ============== -->
  <section class="admin-section" id="signalements">
    <h2>🐞 Signalements (<?= $nbSignalementsOuverts ?> à traiter)</h2>
    <?php if (empty($signalements)): ?>
      <p class="admin-aide">Aucun signalement pour l'instant. Les testeurs peuvent en envoyer depuis le bouton « Signaler un problème » en bas de chaque page.</p>
    <?php else: ?>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead><tr><th>Date</th><th>De</th><th>Page</th><th>Message</th><th>État</th></tr></thead>
          <tbody>
            <?php foreach ($signalements as $sig): ?>
              <tr class="<?= $sig['traite'] ? 'row-inactif' : '' ?>">
                <td><small><?= htmlspecialchars(date('d/m H:i', strtotime($sig['cree_le']))) ?></small></td>
                <td><small><?= htmlspecialchars($sig['user_nom'] ?? 'visiteur') ?></small></td>
                <td><small><?= htmlspecialchars($sig['page']) ?></small></td>
                <td class="cellule-message"><?= nl2br(htmlspecialchars($sig['message'])) ?></td>
                <td>
                  <form method="POST" action="" class="inline-form">
                    <?= csrfField() ?>
                    <input type="hidden" name="id" value="<?= (int) $sig['id'] ?>">
                    <button type="submit" name="action" value="basculer_signalement" class="btn btn-ghost btn-sm">
                      <?= $sig['traite'] ? '✓ Traité' : 'Marquer traité' ?>
                    </button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
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
              <td><?= formatPrix((float) $t['prix']) ?> €</td>
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

<script src="<?= asset('/assets/js/admin.js') ?>"></script>
<?php include __DIR__ . '/partials/footer.php'; ?>
