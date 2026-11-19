<?php
/**
 * diagnostic-email.php — Test d'envoi d'e-mail (réservé à l'admin)
 *
 * Envoie un e-mail de test à l'adresse de l'admin connecté et affiche
 * chaque étape de l'échange avec le serveur mail. Sert à comprendre
 * pourquoi un e-mail n'arrive pas.
 */
require_once __DIR__ . '/lib/init.php';
requireAdmin();

$user     = currentUser();
$resultat = null;
$destinataire = $user['email'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrfVerify($_POST['csrf_token'] ?? null)) {
  $destinataire = trim($_POST['destinataire'] ?? $user['email']);
  $resultat = envoyerEmail(
    $destinataire,
    'Test d\'envoi EcoRide',
    "Bonjour,\n\nCeci est un e-mail de test envoyé depuis la page diagnostic d'EcoRide.\nSi vous le lisez, l'envoi d'e-mails fonctionne.\n\nL'équipe EcoRide"
  );
}

// Demandes de réinitialisation de la dernière heure (limite : 3 par compte)
$pdo = db();
initSecuriteSchema($pdo);
$stmt = $pdo->prepare("
  SELECT u.email, COUNT(*) AS nb FROM reset_mdp r JOIN users u ON u.id = r.user_id
  WHERE r.cree_le > :depuis GROUP BY u.email ORDER BY nb DESC
");
$stmt->execute(['depuis' => time() - 3600]);
$demandes = $stmt->fetchAll();

$pageTitle  = 'Diagnostic e-mail — EcoRide';
$activePage = 'admin';
include __DIR__ . '/partials/header.php';
?>

<main class="container legal-page">
  <h1>Diagnostic e-mail</h1>
  <p class="legal-intro">Expéditeur utilisé par le site : <strong><?= htmlspecialchars(MAIL_EXPEDITEUR) ?></strong> (doit exister dans l'admin AlwaysData, E-mails &gt; Adresses).</p>

  <section class="legal-section">
    <h2>Envoyer un e-mail de test</h2>
    <form method="POST" class="auth-form">
      <?= csrfField() ?>
      <div class="form-field">
        <label for="destinataire">Envoyer à</label>
        <input type="email" id="destinataire" name="destinataire" value="<?= htmlspecialchars($destinataire) ?>" required />
      </div>
      <button type="submit" class="btn btn-primary">Envoyer le test</button>
    </form>

    <?php if ($resultat): ?>
      <div class="form-banner <?= $resultat['ok'] ? 'form-banner-success' : 'form-banner-error' ?>" style="margin-top:16px;">
        <?= $resultat['ok']
          ? 'Le serveur a accepté l\'e-mail (méthode : ' . htmlspecialchars($resultat['methode']) . '). Regardez votre boîte de réception et vos spams.'
          : 'L\'envoi a échoué. Copiez le journal ci-dessous et envoyez-le à votre développeur.' ?>
      </div>
      <pre class="journal-smtp"><?php foreach ($resultat['journal'] as $ligne) echo htmlspecialchars($ligne) . "\n"; ?></pre>
    <?php endif; ?>
  </section>

  <section class="legal-section">
    <h2>Demandes « mot de passe oublié » de la dernière heure</h2>
    <?php if (!$demandes): ?>
      <p>Aucune demande. (Si vous venez d'essayer, l'e-mail saisi ne correspond peut-être à aucun compte.)</p>
    <?php else: ?>
      <ul>
        <?php foreach ($demandes as $d): ?>
          <li><?= htmlspecialchars($d['email']) ?> : <?= (int) $d['nb'] ?> demande(s)<?= $d['nb'] >= 3 ? ' — <strong>limite atteinte</strong>, nouvel essai possible dans moins d\'une heure' : '' ?></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
