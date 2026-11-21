<?php
/**
 * verifier-email.php — Lien reçu par e-mail : confirme l'adresse.
 */
require_once __DIR__ . '/lib/init.php';

$ok = verifierEmail((string) ($_GET['token'] ?? ''));

$pageTitle = 'Vérification de l\'e-mail — EcoRide';
include __DIR__ . '/partials/header.php';
?>
<main class="container form-page">
  <?php if ($ok): ?>
    <h1 class="form-title">E-mail vérifié</h1>
    <p class="form-subtitle">Le badge « E-mail vérifié » est maintenant visible sur votre profil public. Merci !</p>
    <p><a href="/" class="btn btn-primary">Chercher un trajet</a></p>
  <?php else: ?>
    <h1 class="form-title">Lien invalide ou expiré</h1>
    <p class="form-subtitle">Ce lien ne fonctionne plus (il est valable 7 jours et ne sert qu'une fois). Vous pouvez en demander un nouveau depuis votre profil.</p>
    <p><a href="/profil.php" class="btn btn-primary">Aller à mon profil</a></p>
  <?php endif; ?>
</main>
<?php include __DIR__ . '/partials/footer.php'; ?>
