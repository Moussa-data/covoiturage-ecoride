<?php
/**
 * debug.php — Page de diagnostic
 *
 * Réservée aux ADMINS (requireAdmin()).
 * Affiche l'état complet de la BDD pour comprendre les liens
 * entre users, trajets, réservations, messages.
 */
require_once __DIR__ . '/lib/init.php';
requireAdmin();

$pdo = db();

$pageTitle  = 'Debug — EcoRide';
$activePage = '';
include __DIR__ . '/partials/header.php';
?>

<main class="container" style="padding:32px 24px;">
  <h1>🩺 Debug — État de la BDD</h1>
  <p style="color:#6b7280; margin-bottom:24px;">Cette page sert à diagnostiquer les liens entre tes données. À supprimer une fois le bug réglé.</p>

  <!-- USERS -->
  <h2 style="margin-top:24px;">👤 Utilisateurs</h2>
  <table border="1" cellpadding="8" style="border-collapse:collapse; width:100%; background:#fff;">
    <thead style="background:#f3f4f6;">
      <tr><th>ID</th><th>Nom</th><th>Email</th><th>Conducteur ?</th><th>Créé le</th></tr>
    </thead>
    <tbody>
      <?php foreach ($pdo->query("SELECT * FROM users")->fetchAll() as $u): ?>
        <tr>
          <td><?= $u['id'] ?></td>
          <td><strong><?= htmlspecialchars($u['nom']) ?></strong></td>
          <td><?= htmlspecialchars($u['email']) ?></td>
          <td><?= $u['est_conducteur'] ? '🚗 OUI' : '🎒 non' ?></td>
          <td><?= htmlspecialchars($u['cree_le']) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <!-- TRAJETS -->
  <h2 style="margin-top:32px;">🛣️ Trajets</h2>
  <p style="color:#6b7280;">⚠️ La colonne <code>user_id</code> est cruciale : si elle est NULL, aucun conducteur ne "possède" ce trajet en BDD.</p>
  <table border="1" cellpadding="8" style="border-collapse:collapse; width:100%; background:#fff;">
    <thead style="background:#f3f4f6;">
      <tr>
        <th>ID</th>
        <th>Itinéraire</th>
        <th>Date</th>
        <th>Conducteur (nom dans le trajet)</th>
        <th>user_id</th>
        <th>→ Propriétaire en BDD</th>
        <th>Places</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($pdo->query("SELECT t.*, u.nom AS owner_name FROM trajets t LEFT JOIN users u ON u.id = t.user_id ORDER BY t.id")->fetchAll() as $t): ?>
        <tr>
          <td><?= $t['id'] ?></td>
          <td><?= htmlspecialchars($t['depart']) ?> → <?= htmlspecialchars($t['arrivee']) ?></td>
          <td><?= htmlspecialchars($t['date']) ?></td>
          <td><?= htmlspecialchars($t['conducteur_nom']) ?></td>
          <td>
            <?php if ($t['user_id']): ?>
              <strong style="color:#16a34a;"><?= $t['user_id'] ?></strong>
            <?php else: ?>
              <span style="color:#b91c1c;">❌ NULL</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($t['owner_name']): ?>
              <strong><?= htmlspecialchars($t['owner_name']) ?></strong>
            <?php else: ?>
              <em style="color:#b91c1c;">aucun (orphelin)</em>
            <?php endif; ?>
          </td>
          <td><?= $t['places_dispo'] ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <!-- RESERVATIONS -->
  <h2 style="margin-top:32px;">🎟️ Réservations</h2>
  <table border="1" cellpadding="8" style="border-collapse:collapse; width:100%; background:#fff;">
    <thead style="background:#f3f4f6;">
      <tr>
        <th>ID</th>
        <th>Passager</th>
        <th>Trajet</th>
        <th>Propriétaire du trajet</th>
        <th>Statut</th>
        <th>Réservé le</th>
      </tr>
    </thead>
    <tbody>
      <?php
      $reservations = $pdo->query("
        SELECT r.*,
               u_pass.nom AS passager_nom,
               t.depart, t.arrivee, t.user_id AS proprietaire_id,
               u_owner.nom AS proprietaire_nom
        FROM reservations r
        INNER JOIN users u_pass ON u_pass.id = r.user_id
        INNER JOIN trajets t ON t.id = r.trajet_id
        LEFT JOIN users u_owner ON u_owner.id = t.user_id
        ORDER BY r.cree_le DESC
      ")->fetchAll();
      ?>
      <?php if (empty($reservations)): ?>
        <tr><td colspan="6" style="text-align:center; color:#6b7280;">Aucune réservation pour l'instant.</td></tr>
      <?php else: ?>
        <?php foreach ($reservations as $r): ?>
          <tr>
            <td><?= $r['id'] ?></td>
            <td><strong><?= htmlspecialchars($r['passager_nom']) ?></strong></td>
            <td><?= htmlspecialchars($r['depart']) ?> → <?= htmlspecialchars($r['arrivee']) ?> (#<?= $r['trajet_id'] ?>)</td>
            <td>
              <?php if ($r['proprietaire_nom']): ?>
                <strong style="color:#16a34a;"><?= htmlspecialchars($r['proprietaire_nom']) ?></strong>
              <?php else: ?>
                <em style="color:#b91c1c;">❌ trajet orphelin (user_id NULL)</em>
              <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($r['statut']) ?></td>
            <td><?= htmlspecialchars($r['cree_le']) ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>

  <!-- MESSAGES -->
  <h2 style="margin-top:32px;">💬 Messages</h2>
  <table border="1" cellpadding="8" style="border-collapse:collapse; width:100%; background:#fff;">
    <thead style="background:#f3f4f6;">
      <tr>
        <th>ID</th>
        <th>Trajet</th>
        <th>Expéditeur (id → nom)</th>
        <th>Destinataire (id → nom)</th>
        <th>Auto-message ?</th>
        <th>Contenu</th>
        <th>Lu ?</th>
        <th>Créé le</th>
      </tr>
    </thead>
    <tbody>
      <?php
      $messages = $pdo->query("
        SELECT m.*,
               u_e.nom AS expediteur_nom,
               u_d.nom AS destinataire_nom
        FROM messages m
        LEFT JOIN users u_e ON u_e.id = m.expediteur_id
        LEFT JOIN users u_d ON u_d.id = m.destinataire_id
        ORDER BY m.cree_le DESC
      ")->fetchAll();
      ?>
      <?php if (empty($messages)): ?>
        <tr><td colspan="8" style="text-align:center; color:#6b7280;">Aucun message en BDD.</td></tr>
      <?php else: ?>
        <?php foreach ($messages as $m): ?>
          <?php $self = $m['expediteur_id'] === $m['destinataire_id']; ?>
          <tr style="<?= $self ? 'background:#fef2f2;' : '' ?>">
            <td><?= $m['id'] ?></td>
            <td>#<?= $m['trajet_id'] ?></td>
            <td><?= $m['expediteur_id'] ?> → <strong><?= htmlspecialchars($m['expediteur_nom'] ?? '?') ?></strong></td>
            <td><?= $m['destinataire_id'] ?> → <strong><?= htmlspecialchars($m['destinataire_nom'] ?? '?') ?></strong></td>
            <td><?= $self ? '⚠️ OUI (à supprimer)' : '✓ non' ?></td>
            <td style="max-width:200px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?= htmlspecialchars(mb_substr($m['contenu'], 0, 50)) ?></td>
            <td><?= $m['lu'] ? 'lu' : 'non lu' ?></td>
            <td><?= htmlspecialchars($m['cree_le']) ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>

  <!-- DIAGNOSTIC -->
  <h2 style="margin-top:32px;">🔬 Diagnostic</h2>
  <?php
  $orphelins = $pdo->query("SELECT COUNT(*) FROM trajets WHERE user_id IS NULL")->fetchColumn();
  $reservOnOrphelin = $pdo->query("SELECT COUNT(*) FROM reservations r JOIN trajets t ON t.id = r.trajet_id WHERE t.user_id IS NULL")->fetchColumn();
  ?>
  <ul>
    <li><strong><?= $orphelins ?></strong> trajets sont "orphelins" (user_id NULL) → ils n'apparaîtront jamais dans "Mes trajets"</li>
    <li><strong><?= $reservOnOrphelin ?></strong> réservations sont sur des trajets orphelins → elles ne seront visibles côté conducteur que si on lie ces trajets à un user</li>
  </ul>

  <?php if ($orphelins > 0 || $reservOnOrphelin > 0): ?>
    <div style="background:#fef2f2; padding:16px; border-radius:8px; border:1px solid #fecaca; color:#b91c1c; margin-top:12px;">
      ⚠️ <strong>Tu as des trajets orphelins.</strong> Ce sont les trajets fictifs importés du JSON (Thomas R., Marie L., etc.) qui n'ont pas été créés via l'interface. Si ton 2e compte a réservé un de ces trajets, c'est normal qu'aucun conducteur ne le voie.
      <br><br>
      <strong>Solution :</strong> connecte-toi avec un compte conducteur, publie un trajet via <a href="/publier.php">/publier.php</a>, déconnecte-toi, connecte-toi avec ton 2e compte passager, réserve ce nouveau trajet précis. Puis reviens sur ce debug pour voir le résultat.
    </div>
  <?php endif; ?>
</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
