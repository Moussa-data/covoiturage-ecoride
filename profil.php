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
$messageBio      = null;
$messageSuppression = null;

// ====== Suppression du compte (RGPD) ======
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'supprimer') {
  if (!csrfVerify($_POST['csrf_token'] ?? null)) {
    $messageSuppression = 'Token CSRF invalide.';
  } elseif (($_POST['confirmation_texte'] ?? '') !== 'SUPPRIMER') {
    $messageSuppression = 'Tapez SUPPRIMER en majuscules pour confirmer.';
  } else {
    $r = supprimerMonCompte((int) $user['id'], (string) ($_POST['mot_de_passe_suppression'] ?? ''));
    if ($r['ok']) {
      logoutUser();
      header('Location: /?compte=supprime');
      exit;
    }
    $messageSuppression = $r['error'];
  }
}
$messageVerif    = null;

// ====== Présentation (bio) ======
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'bio') {
  if (!csrfVerify($_POST['csrf_token'] ?? null)) {
    $messageBio = ['type' => 'error', 'text' => 'Token CSRF invalide.'];
  } else {
    $r = majBio($user['id'], $_POST['bio'] ?? '');
    $messageBio = $r['ok']
      ? ['type' => 'success', 'text' => 'Présentation enregistrée. Elle apparaît sur votre profil public.']
      : ['type' => 'error', 'text' => $r['error']];
    $user = currentUser();
  }
}

// ====== Renvoyer le lien de vérification ======
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'verif') {
  if (csrfVerify($_POST['csrf_token'] ?? null)) {
    $lien = envoyerVerificationEmail($user['id']);
    $messageVerif = $lien
      ? ['type' => 'success', 'text' => 'Lien envoyé à ' . $user['email'] . '. Pensez à regarder dans les spams.']
      : ['type' => 'error', 'text' => 'Trop de demandes. Réessayez dans une heure.'];
  }
}

// ====== Formulaire profil (nom + email) ======
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'profil') {
  if (!csrfVerify($_POST['csrf_token'] ?? null)) {
    $messageProfil = ['type' => 'error', 'text' => 'Token CSRF invalide.'];
  } else {
    $r = updateUserProfile($user['id'], $_POST['nom'] ?? '', $_POST['email'] ?? '');
    if ($r['ok']) {
      $messageProfil = ['type' => 'success', 'text' => !empty($r['email_change'])
        ? 'Profil mis à jour. Votre nouvel e-mail doit être vérifié : un lien vient de vous être envoyé.'
        : 'Profil mis à jour avec succès.'];
      if (!empty($r['email_change'])) {
        envoyerVerificationEmail($user['id']);
      }
      $user = currentUser(); // recharge les infos à jour
    } else {
      $erreursProfil = $r['errors'];
      $messageProfil = ['type' => 'error', 'text' => 'Corrigez les erreurs ci-dessous.'];
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
      $messageMdp = ['type' => 'success', 'text' => 'Mot de passe changé. Gardez-le en lieu sûr 🔐'];
    } else {
      $erreursMdp = $r['errors'];
      $messageMdp = ['type' => 'error', 'text' => 'Corrigez les erreurs ci-dessous.'];
    }
  }
}

$pageTitle  = 'Mon profil — EcoRide';
$activePage = 'profil';
include __DIR__ . '/partials/header.php';
?>

<main class="container form-page">

  <h1 class="form-title">Mon profil</h1>
  <p class="form-subtitle">Modifiez vos infos ou votre mot de passe.</p>

  <!-- ============== Carte rôle ============== -->
  <section class="form-section role-card">
    <h2>Mon rôle</h2>
    <?php if (!empty($user['est_admin'])): ?>
      <div class="role-badge role-badge-admin">
        <span class="role-icon">👑</span>
        <div>
          <strong>Administrateur</strong>
          <span class="role-desc">Vous avez accès à tout, dont la <a href="/debug.php">page de debug</a>.</span>
        </div>
      </div>
    <?php endif; ?>
    <?php if (!empty($user['est_conducteur'])): ?>
      <div class="role-badge role-badge-conducteur" style="margin-top: <?= !empty($user['est_admin']) ? '12px' : '0' ?>;">
        <span class="role-icon">🚗</span>
        <div>
          <strong>Conducteur</strong>
          <span class="role-desc">Vous pouvez publier et réserver des trajets.</span>
        </div>
      </div>
    <?php else: ?>
      <div class="role-badge role-badge-passager">
        <span class="role-icon">🎒</span>
        <div>
          <strong>Passager</strong>
          <span class="role-desc">Vous pouvez réserver des trajets. Activez le rôle conducteur pour aussi en publier.</span>
        </div>
      </div>
      <a href="/devenir-conducteur.php" class="btn btn-primary" style="margin-top:12px;">Devenir conducteur</a>
    <?php endif; ?>
  </section>

  <!-- ============== Confiance : e-mail + profil public ============== -->
  <section class="form-section confiance-card">
    <h2>Confiance</h2>
    <?php if ($messageVerif): ?>
      <div class="form-banner form-banner-<?= $messageVerif['type'] ?>"><?= htmlspecialchars($messageVerif['text']) ?></div>
    <?php endif; ?>
    <?php if (!empty($user['email_verifie'])): ?>
      <p class="confiance-ligne confiance-ok"><span class="confiance-pastille">✓</span> E-mail vérifié</p>
    <?php else: ?>
      <div class="confiance-ligne">
        <span class="confiance-pastille confiance-pastille-vide">!</span>
        <span>E-mail pas encore vérifié. Cliquez sur le lien reçu à l'inscription pour obtenir le badge.</span>
      </div>
      <form method="POST" action="" style="margin-top:10px;">
        <?= csrfField() ?>
        <input type="hidden" name="form" value="verif">
        <button type="submit" class="btn btn-ghost">Renvoyer le lien</button>
      </form>
    <?php endif; ?>
    <p class="confiance-lien"><a href="/profil-public.php?id=<?= (int) $user['id'] ?>">Voir mon profil public</a> : c'est ce que voient les autres membres (jamais votre e-mail).</p>
  </section>

  <!-- ============== Présentation ============== -->
  <?php if ($messageBio): ?>
    <div class="form-banner form-banner-<?= $messageBio['type'] ?>"><?= htmlspecialchars($messageBio['text']) ?></div>
  <?php endif; ?>
  <form method="POST" action="" class="form-trajet" novalidate>
    <?= csrfField() ?>
    <input type="hidden" name="form" value="bio">
    <fieldset class="form-section">
      <legend>Ma présentation</legend>
      <div class="form-field">
        <label for="bio">Quelques mots sur vous</label>
        <textarea id="bio" name="bio" rows="4" maxlength="400" placeholder="Ex. : Je fais Versailles → La Défense tous les matins. Ponctuel, musique douce, pas de détour."><?= htmlspecialchars($_POST['bio'] ?? ($user['bio'] ?? '')) ?></textarea>
        <span class="field-hint">400 caractères maximum. Visible sur votre profil public.</span>
      </div>
    </fieldset>
    <div class="form-actions">
      <button type="submit" class="btn btn-primary">Enregistrer la présentation</button>
    </div>
  </form>

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
        <label for="confirmation">Confirmez le nouveau mot de passe</label>
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

<section class="container zone-danger" aria-labelledby="titreSuppression">
  <h2 id="titreSuppression">Supprimer mon compte</h2>
  <p>Votre compte, vos trajets, vos réservations, vos avis et vos messages seront effacés définitivement. Cette action est irréversible.</p>
  <?php if ($messageSuppression): ?>
    <div class="form-banner form-banner-error"><?= htmlspecialchars($messageSuppression) ?></div>
  <?php endif; ?>
  <form method="POST" action="" class="zone-danger-form" novalidate>
    <?= csrfField() ?>
    <input type="hidden" name="form" value="supprimer">
    <div class="form-field">
      <label for="mot_de_passe_suppression">Votre mot de passe</label>
      <input type="password" id="mot_de_passe_suppression" name="mot_de_passe_suppression" autocomplete="current-password" required>
    </div>
    <div class="form-field">
      <label for="confirmation_texte">Tapez SUPPRIMER pour confirmer</label>
      <input type="text" id="confirmation_texte" name="confirmation_texte" autocomplete="off" required>
    </div>
    <button type="submit" class="btn btn-danger">Supprimer définitivement mon compte</button>
  </form>
</section>

<?php include __DIR__ . '/partials/footer.php'; ?>
