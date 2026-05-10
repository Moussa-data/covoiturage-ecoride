<?php
/**
 * conversation.php — Fil de discussion entre 2 users pour un trajet
 *
 * URL : /conversation.php?trajet=X&avec=Y
 *
 * Au chargement :
 *  - Vérifie que le user a le droit de voir cette conversation (canMessage)
 *  - Marque tous les messages reçus comme lus
 *  - Affiche les messages dans l'ordre chronologique
 *  - Auto-scroll en bas (côté JS)
 *
 * À la soumission du formulaire :
 *  - Envoie le message via sendMessage()
 *  - Redirection (POST/Redirect/GET) → on reste sur la conversation
 */

require_once __DIR__ . '/lib/init.php';
requireLogin();

$user      = currentUser();
$trajetId  = (int) ($_GET['trajet'] ?? 0);
$autreId   = (int) ($_GET['avec']   ?? 0);

// Vérifie l'autorisation avant tout
if (!canMessage($user['id'], $trajetId, $autreId)) {
  http_response_code(403);

  // === DIAGNOSTIC DÉTAILLÉ ===
  // On regarde précisément pourquoi canMessage a refusé.
  $stmtDiag = db()->prepare("SELECT user_id FROM trajets WHERE id = :id");
  $stmtDiag->execute(['id' => $trajetId]);
  $cond = $stmtDiag->fetchColumn();
  $conducteurId = $cond !== false ? (int) $cond : null;

  $resa1 = $conducteurId !== null ? reservationExiste($user['id'], $trajetId)  : false; // moi ?
  $resa2 = $conducteurId !== null ? reservationExiste($autreId, $trajetId)     : false; // l'autre ?

  $pageTitle = 'Conversation indisponible — EcoRide';
  include __DIR__ . '/partials/header.php';
  ?>
  <main class="container detail-page">
    <div class="empty empty-large">
      <h1>Conversation indisponible</h1>
      <p>Tu n'as pas accès à cette conversation. Tu peux discuter uniquement avec les conducteurs des trajets que tu as réservés (ou tes passagers si tu es conducteur).</p>

      <details style="margin:24px auto; max-width:560px; text-align:left; background:#fef2f2; padding:16px; border-radius:8px; border:1px solid #fecaca;">
        <summary style="cursor:pointer; font-weight:600; color:#b91c1c;">🔍 Détails techniques (debug)</summary>
        <ul style="margin-top:12px; font-size:0.9rem; line-height:1.7; color:#374151;">
          <li>Trajet ID dans l'URL : <code><?= htmlspecialchars((string) $trajetId) ?></code></li>
          <li>Autre user ID dans l'URL : <code><?= htmlspecialchars((string) $autreId) ?></code></li>
          <li>Mon user ID : <code><?= htmlspecialchars((string) $user['id']) ?></code></li>
          <li>user_id du trajet en BDD : <code><?= $conducteurId === null ? 'NULL ❌ trajet orphelin' : $conducteurId ?></code></li>
          <li>Suis-je le conducteur ? <strong><?= $conducteurId === $user['id'] ? 'oui' : 'non' ?></strong></li>
          <li>L'autre user est-il le conducteur ? <strong><?= $conducteurId === $autreId ? 'oui' : 'non' ?></strong></li>
          <li>J'ai réservé ce trajet ? <strong><?= $resa1 ? 'oui' : 'NON ❌' ?></strong></li>
          <li>L'autre a réservé ce trajet ? <strong><?= $resa2 ? 'oui' : 'NON ❌' ?></strong></li>
        </ul>
      </details>

      <a href="/messagerie.php" class="btn btn-primary">Retour</a>
    </div>
  </main>
  <?php
  include __DIR__ . '/partials/footer.php';
  exit;
}

// Récupère l'autre user et le trajet (pour afficher le contexte)
$autreUser = findUserById($autreId);
$trajet    = findTrajetById($trajetId);

$erreur = null;

// Envoi d'un nouveau message
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!csrfVerify($_POST['csrf_token'] ?? null)) {
    $erreur = 'Token CSRF invalide.';
  } else {
    $r = sendMessage($user['id'], $autreId, $trajetId, $_POST['contenu'] ?? '');
    if ($r['ok']) {
      // POST/Redirect/GET — on évite la double-soumission au refresh
      header('Location: /conversation.php?trajet=' . $trajetId . '&avec=' . $autreId);
      exit;
    } else {
      $erreur = $r['error'];
    }
  }
}

// Marque les messages reçus comme lus (avant le SELECT, pour ne pas
// voir un message comme "unread" puis "read" dans la même page)
markMessagesAsRead($user['id'], $autreId, $trajetId);

// Charge les messages
$messages = getMessages($user['id'], $autreId, $trajetId);

$pageTitle  = 'Discussion avec ' . $autreUser['nom'] . ' — EcoRide';
$activePage = 'messagerie';
include __DIR__ . '/partials/header.php';
?>

<main class="container conversation-page">

  <div class="conv-header">
    <a href="/messagerie.php" class="conv-back" aria-label="Retour">←</a>
    <div class="conv-header-info">
      <strong><?= htmlspecialchars($autreUser['nom']) ?></strong>
      <span class="conv-header-trip">
        🛣️ <?= htmlspecialchars($trajet['depart']) ?> → <?= htmlspecialchars($trajet['arrivee']) ?>
        · <?= htmlspecialchars(date('d/m/Y', strtotime($trajet['date']))) ?>
        · <a href="/trajet.php?id=<?= $trajetId ?>">Voir le trajet</a>
      </span>
    </div>
  </div>

  <?php if ($erreur !== null): ?>
    <div class="form-banner form-banner-error">⚠️ <?= htmlspecialchars($erreur) ?></div>
  <?php endif; ?>

  <div class="chat-window" id="chatWindow">
    <?php if (empty($messages)): ?>
      <div class="chat-empty">Aucun message pour l'instant. Lance la conversation 👋</div>
    <?php else: ?>
      <?php foreach ($messages as $m): ?>
        <?php $estMoi = (int) $m['expediteur_id'] === $user['id']; ?>
        <div class="chat-msg <?= $estMoi ? 'chat-msg-mine' : 'chat-msg-other' ?>">
          <div class="chat-bubble"><?= nl2br(htmlspecialchars($m['contenu'])) ?></div>
          <div class="chat-time">
            <?= htmlspecialchars(date('H:i', strtotime($m['cree_le']))) ?>
            <?php if (!$estMoi && (int) $m['lu'] === 0): ?>
              <span class="chat-unread-dot" title="Nouveau"></span>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <form method="POST" action="" class="chat-form">
    <?= csrfField() ?>
    <textarea name="contenu" placeholder="Écris ton message..." rows="2" required maxlength="2000"></textarea>
    <button type="submit" class="btn btn-primary">Envoyer</button>
  </form>
</main>

<script>
  // Auto-scroll en bas pour voir le dernier message
  const chat = document.getElementById('chatWindow');
  if (chat) chat.scrollTop = chat.scrollHeight;

  // Cmd/Ctrl + Entrée pour envoyer (raccourci pratique)
  document.querySelector('.chat-form textarea').addEventListener('keydown', (e) => {
    if ((e.metaKey || e.ctrlKey) && e.key === 'Enter') {
      e.target.form.submit();
    }
  });
</script>

<?php include __DIR__ . '/partials/footer.php'; ?>
