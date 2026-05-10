<?php
/**
 * messagerie.php — Liste des conversations de l'utilisateur
 */
require_once __DIR__ . '/lib/init.php';
requireLogin();

$user = currentUser();
$conversations = getConversations($user['id']);

$pageTitle  = 'Messagerie — EcoRide';
$activePage = 'messagerie';
include __DIR__ . '/partials/header.php';
?>

<main class="container mes-trajets-page">

  <div class="page-header">
    <h1>Messagerie</h1>
  </div>

  <?php if (empty($conversations)): ?>
    <div class="empty empty-large">
      <h2>Aucune conversation pour l'instant</h2>
      <p>Réserve un trajet ou laisse un passager te contacter pour démarrer une discussion 💬</p>
      <a href="/" class="btn btn-primary">Voir les trajets</a>
    </div>
  <?php else: ?>

    <div class="conv-list">
      <?php foreach ($conversations as $c): ?>
        <a href="/conversation.php?trajet=<?= $c['trajet_id'] ?>&avec=<?= $c['autre_user_id'] ?>"
           class="conv-item <?= $c['non_lus'] > 0 ? 'conv-unread' : '' ?>">

          <div class="conv-avatar">
            <?= strtoupper(mb_substr($c['autre_user_nom'], 0, 1)) ?>
          </div>

          <div class="conv-content">
            <div class="conv-top">
              <strong class="conv-name"><?= htmlspecialchars($c['autre_user_nom']) ?></strong>
              <span class="conv-date">
                <?= htmlspecialchars(date('d/m', strtotime($c['dernier_message_le']))) ?>
              </span>
            </div>
            <div class="conv-trip">
              🛣️ <?= htmlspecialchars($c['depart']) ?> → <?= htmlspecialchars($c['arrivee']) ?>
              · <?= htmlspecialchars(date('d/m/Y', strtotime($c['date']))) ?>
            </div>
            <p class="conv-preview"><?= htmlspecialchars(mb_substr($c['dernier_contenu'] ?? '', 0, 80)) ?><?= mb_strlen($c['dernier_contenu'] ?? '') > 80 ? '…' : '' ?></p>
          </div>

          <?php if ($c['non_lus'] > 0): ?>
            <span class="conv-badge"><?= $c['non_lus'] ?></span>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
