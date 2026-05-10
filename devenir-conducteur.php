<?php
/**
 * devenir-conducteur.php — Page d'activation du rôle conducteur
 *
 * Workflow :
 *  - GET  : on affiche les infos + le formulaire de confirmation
 *  - POST : on vérifie les engagements, on active le flag, redirection
 *
 * Si l'utilisateur est DÉJÀ conducteur, on le redirige vers /publier.php
 * (il n'a pas besoin de revenir ici).
 */

require_once __DIR__ . '/lib/init.php';
requireLogin();

$user = currentUser();

// Si déjà conducteur, on n'a rien à faire ici
if (isConducteur()) {
  header('Location: /publier.php');
  exit;
}

$erreur = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!csrfVerify($_POST['csrf_token'] ?? null)) {
    $erreur = 'Token CSRF invalide.';
  } elseif (empty($_POST['engagement'])) {
    // L'utilisateur doit cocher la case d'engagement
    $erreur = 'Tu dois confirmer remplir les conditions pour devenir conducteur.';
  } else {
    if (becomeConducteur($user['id'])) {
      header('Location: /publier.php?promu=1');
      exit;
    } else {
      $erreur = 'Une erreur est survenue. Réessaie.';
    }
  }
}

$pageTitle  = 'Devenir conducteur — EcoRide';
$activePage = '';
include __DIR__ . '/partials/header.php';
?>

<main class="container form-page">

  <h1 class="form-title">Devenir conducteur 🚗</h1>
  <p class="form-subtitle">Active ton rôle conducteur pour publier des trajets et accueillir des passagers.</p>

  <?php if ($erreur !== null): ?>
    <div class="form-banner form-banner-error">⚠️ <?= htmlspecialchars($erreur) ?></div>
  <?php endif; ?>

  <section class="form-section conducteur-explainer">
    <h2>Ce que ça change pour toi</h2>
    <ul class="explainer-list">
      <li>✅ Tu pourras <strong>publier des trajets</strong> et fixer le prix</li>
      <li>✅ Tu auras accès à la page <strong>Mes trajets</strong> et la liste des passagers</li>
      <li>✅ Tu pourras <strong>recevoir des avis</strong> de tes passagers</li>
      <li>📌 Tu peux toujours <strong>réserver</strong> des trajets en tant que passager</li>
      <li>🌱 Tu rejoins la communauté des conducteurs écologiques</li>
    </ul>
  </section>

  <form method="POST" action="" class="form-trajet" novalidate>
    <?= csrfField() ?>

    <fieldset class="form-section">
      <legend>Engagement du conducteur</legend>

      <p class="form-label">
        En activant ton rôle conducteur, tu confirmes :
      </p>

      <ul class="conducteur-conditions">
        <li>Posséder un permis de conduire valide</li>
        <li>Disposer d'une voiture en règle (assurance, contrôle technique)</li>
        <li>Respecter le code de la route et la sécurité de tes passagers</li>
        <li>Être ponctuel et fournir des informations exactes sur tes trajets</li>
      </ul>

      <label class="check check-large">
        <input type="checkbox" name="engagement" value="1" required />
        <span>Je confirme remplir toutes les conditions ci-dessus.</span>
      </label>
    </fieldset>

    <div class="form-actions">
      <a href="/" class="btn btn-ghost">Plus tard</a>
      <button type="submit" class="btn btn-primary">Activer mon rôle conducteur</button>
    </div>
  </form>

</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
