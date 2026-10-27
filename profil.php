<?php
/**
 * profil.php — Page de gestion du compte
 *
 * Deux formulaires séparés :
 *  1. Modifier nom / email (sans demander le mot de passe)
 *  2. Changer le mot de passe (demande l'ancien)
 *
 * Pourquoi 2 formulaires séparés ? Parce que demander l'ancien
 * mot de passe pour changer son nom serait pénible. On demande
 * l'ancien UNIQUEMENT pour les changements vraiment sensibles.
 */
require_once __DIR__ . '/lib/init.php';
requireLogin();

$user = currentUser();

$messageProfil   = null;
$messageMdp      = null;
$erreursProfil   = [];
$erreursMdp      = [];

// ====== Formulaire profil (nom + email) ======
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'profil') {
  if (!csrfVerify($_POST['csrf_token'] ?? null)) {
    $messageProfil = ['type' => 'error', 'text' => 'Token CSRF invalide.'];
  } else {
    $r = updateUserProfile($user['id'], $_POST['nom'] ?? '', $_POST['email'] ?? '');
    if ($r['ok']) {
      $messageProfil = ['type' => 'success', 'text' => 'Profil mis à jour avec succès.'];
      $user = currentUser(); // recharge les infos à jour
    } else {
      $erreursProfil = $r['errors'];
      $messageProfil = ['type' => 'error', 'text' => 'Corrige les erreurs ci-dessous.'];
    }
  }
}

// ====== Formulaire mot de passe ======
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'mdp') {
  if (!csrfVerify($_POST['csrf_token'] ?? null)) {
    $messageMdp = ['type' => 'error', 'text' => 'Token CSRF invalide.'];
  } else {
    $r = changePassword(
      $user['id'],
      $_POST['ancien_mdp']   ?? '',
      $_POST['nouveau_mdp']  ?? '',
      $_POST['confirmation'] ?? ''
    );
    if ($r['ok']) {
      $messageMdp = ['type' => 'success', 'text' => 'Mot de passe changé. Garde-le en lieu sûr 🔐'];
    } else {
      $erreursMdp = $r['errors'];
      $messageMdp = ['type' => 'error', 'text' => 'Corrige les erreurs ci-dessous.'];
    }
  }
}

$pageTitle  = 'Mon profil — EcoRide';
$activePage = 'profil';
include __DIR__ . '/partials/header.php';
?>

<main class="container form-page">

  <h1 class="form-title">Mon profil</h1>
  <p class="form-subtitle">Modifie tes infos ou ton mot de passe.</p>

  <!-- ============== Carte rôle ============== -->
  <section class="form-section role-card">
    <h2>Mon rôle</h2>
    <?php if (!empty($user['est_admin'])): ?>
      <div class="role-badge role-badge-admin">
        <span class="role-icon">👑</span>
        <div>
          <strong>Administrateur</strong>
          <span class="role-desc">Tu as accès à tout, dont la <a href="/admin.php">page d'administration</a>.</span>
        </div>
      </div>
    <?php endif; ?>
    <?php if (!empty($user['est_conducteur'])): ?>
      <div class="role-badge role-badge-conducteur" style="margin-top: <?= !empty($user['est_admin']) ? '12px' : '0' ?>;">
        <span class="role-icon">🚗</span>
        <div>
          <strong>Conducteur</strong>
          <span class="role-desc">Tu peux publier et réserver des trajets.</span>
        </div>
      </div>
    <?php else: ?>
      <div class="role-badge role-badge-passager">
        <span class="role-icon">🎒</span>
        <div>
          <strong>Passager</strong>
          <span class="role-desc">Tu peux réserver des trajets. Active le rôle conducteur pour aussi en publier.</span>
        </div>
      </div>
      <a href="/devenir-conducteur.php" class="btn btn-primary" style="margin-top:12px;">Devenir conducteur</a>
    <?php endif; ?>
  </section>

  <!-- ============== Formulaire 1 : profil ============== -->
  <?php if ($messageProfil): ?>
    <div class="form-banner form-banner-<?= $messageProfil['type'] ?>">
      <?= htmlspecialchars($messageProfil['text']) ?>
    </div>
  <?php endif; ?>

  <form method="POST" action="" class="form-trajet" novalidate>
    <?= csrfField() ?>
    <input type="hidden" name="form" value="profil">

    <fieldset class="form-section">
      <legend>Informations personnelles</legend>

      <div class="form-field <?= isset($erreursProfil['nom']) ? 'has-error' : '' ?>">
        <label for="nom">Nom</label>
        <input type="text" id="nom" name="nom" value="<?= htmlspecialchars($_POST['nom'] ?? $user['nom']) ?>" />
        <?php if (isset($erreursProfil['nom'])): ?>
          <span class="error-msg"><?= htmlspecialchars($erreursProfil['nom']) ?></span>
        <?php endif; ?>
      </div>

      <div class="form-field <?= isset($erreursProfil['email']) ? 'has-error' : '' ?>">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? $user['email']) ?>" />
        <?php if (isset($erreursProfil['email'])): ?>
          <span class="error-msg"><?= htmlspecialchars($erreursProfil['email']) ?></span>
        <?php endif; ?>
      </div>
    </fieldset>

    <div class="form-actions">
      <button type="submit" class="btn btn-primary">Enregistrer</button>
    </div>
  </form>


  <!-- ============== Formulaire 2 : mot de passe ============== -->
  <?php if ($messageMdp): ?>
    <div class="form-banner form-banner-<?= $messageMdp['type'] ?>">
      <?= htmlspecialchars($messageMdp['text']) ?>
    </div>
  <?php endif; ?>

  <form method="POST" action="" class="form-trajet" novalidate style="margin-top:32px;">
    <?= csrfField() ?>
    <input type="hidden" name="form" value="mdp">

    <fieldset class="form-section">
      <legend>Changer mon mot de passe</legend>

      <div class="form-field <?= isset($erreursMdp['ancien']) ? 'has-error' : '' ?>">
        <label for="ancien_mdp">Mot de passe actuel</label>
        <input type="password" id="ancien_mdp" name="ancien_mdp" autocomplete="current-password" />
        <?php if (isset($erreursMdp['ancien'])): ?>
          <span class="error-msg"><?= htmlspecialchars($erreursMdp['ancien']) ?></span>
        <?php endif; ?>
      </div>

      <div class="form-field <?= isset($erreursMdp['nouveau']) ? 'has-error' : '' ?>">
        <label for="nouveau_mdp">Nouveau mot de passe</label>
        <input type="password" id="nouveau_mdp" name="nouveau_mdp" autocomplete="new-password" placeholder="8 caractères minimum" />
        <?php if (isset($erreursMdp['nouveau'])): ?>
          <span class="error-msg"><?= htmlspecialchars($erreursMdp['nouveau']) ?></span>
        <?php endif; ?>
      </div>

      <div class="form-field <?= isset($erreursMdp['confirmation']) ? 'has-error' : '' ?>">
        <label for="confirmation">Confirme le nouveau mot de passe</label>
        <input type="password" id="confirmation" name="confirmation" autocomplete="new-password" />
        <?php if (isset($erreursMdp['confirmation'])): ?>
          <span class="error-msg"><?= htmlspecialchars($erreursMdp['confirmation']) ?></span>
        <?php endif; ?>
      </div>
    </fieldset>

    <div class="form-actions">
      <button type="submit" class="btn btn-primary">Changer le mot de passe</button>
    </div>
  </form>
</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
