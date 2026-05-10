<?php
/**
 * partials/header.php
 *
 * En-tête partagé. On peut passer ces variables AVANT l'include :
 *   - $pageTitle  : le titre de l'onglet du navigateur
 *   - $activePage : la page active dans la nav (mise en surbrillance)
 *
 * Le header charge init.php pour s'assurer que la session est démarrée
 * et qu'on a accès à currentUser() pour personnaliser la nav.
 */

require_once __DIR__ . '/../lib/init.php';

$pageTitle  = $pageTitle  ?? 'EcoRide — Covoiturage écologique';
$activePage = $activePage ?? 'accueil';
$user       = currentUser(); // null si pas connecté
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= htmlspecialchars($pageTitle) ?></title>
  <meta name="description" content="EcoRide : trouve un covoiturage écologique, économise et réduis ton empreinte carbone." />

  <!-- Favicon SVG inline (vert, cohérent avec le logo) -->
  <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'><path d='M12 2C9 5 6 8 6 12a6 6 0 0 0 12 0c0-4-3-7-6-10z' fill='%2316a34a'/></svg>" />

  <!-- Open Graph (preview lors du partage sur Facebook, Slack, etc.) -->
  <meta property="og:title" content="<?= htmlspecialchars($pageTitle) ?>" />
  <meta property="og:description" content="Covoiturage écologique : économise, partage, réduis ton empreinte carbone." />
  <meta property="og:type" content="website" />
  <meta property="og:url" content="https://moussa.alwaysdata.net<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/') ?>" />
  <meta property="og:locale" content="fr_FR" />

  <!-- Twitter Card (preview lors du partage sur X/Twitter) -->
  <meta name="twitter:card" content="summary" />
  <meta name="twitter:title" content="<?= htmlspecialchars($pageTitle) ?>" />
  <meta name="twitter:description" content="Covoiturage écologique : économise, partage, réduis ton empreinte carbone." />

  <link rel="stylesheet" href="/assets/css/style.css" />
</head>
<body>

  <header class="site-header">
    <div class="container header-inner">

      <a href="/" class="logo" aria-label="Accueil EcoRide">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path d="M12 2C9 5 6 8 6 12a6 6 0 0 0 12 0c0-4-3-7-6-10z" fill="currentColor"/>
        </svg>
        <span>EcoRide</span>
      </a>

      <!-- Bouton menu burger (visible uniquement sur mobile) -->
      <button type="button" class="burger" id="burgerBtn" aria-label="Ouvrir le menu" aria-expanded="false">
        <span></span><span></span><span></span>
      </button>

      <?php $estConducteur = $user !== null && !empty($user['est_conducteur']); ?>

      <!--
        Navigation principale (visible par tous) :
        - Accueil
        - Rechercher (= page d'accueil aussi)
        - Publier (uniquement si conducteur)
        - À propos
        Tout ce qui est "Mon compte" est regroupé dans le dropdown à droite.
      -->
      <nav class="main-nav" aria-label="Navigation principale">
        <ul>
          <li><a href="/" class="<?= $activePage === 'accueil' ? 'active' : '' ?>">Accueil</a></li>
          <?php if ($estConducteur): ?>
            <li><a href="/publier.php" class="<?= $activePage === 'publier' ? 'active' : '' ?>">Publier un trajet</a></li>
          <?php endif; ?>
          <li><a href="/a-propos.php" class="<?= $activePage === 'a-propos' ? 'active' : '' ?>">À propos</a></li>
        </ul>
      </nav>

      <div class="auth-buttons">
        <?php if ($user !== null): ?>
          <?php $unread = getUnreadCount($user['id']); ?>

          <!--
            Menu utilisateur déroulant.
            Le pattern : un <details>/<summary> en CSS pur (pas besoin de JS),
            qui s'ouvre au clic et affiche les liens du compte.
          -->
          <details class="user-menu">
            <summary class="user-menu-trigger" aria-haspopup="menu">
              <span class="user-avatar"><?= strtoupper(mb_substr($user['nom'], 0, 1)) ?></span>
              <span class="user-name"><?= htmlspecialchars($user['nom']) ?></span>
              <?php if ($unread > 0): ?>
                <span class="nav-badge"><?= $unread ?></span>
              <?php endif; ?>
              <svg class="user-menu-chevron" width="12" height="12" viewBox="0 0 12 12" aria-hidden="true">
                <path d="M3 4.5l3 3 3-3" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </summary>

            <div class="user-menu-dropdown" role="menu">
              <div class="user-menu-header">
                <strong><?= htmlspecialchars($user['nom']) ?></strong>
                <span><?= htmlspecialchars($user['email']) ?></span>
              </div>

              <ul class="user-menu-list">
                <?php if ($estConducteur): ?>
                  <li><a href="/mes-trajets.php" class="<?= $activePage === 'mes-trajets' ? 'active' : '' ?>">🚗 Mes trajets</a></li>
                <?php endif; ?>
                <li><a href="/mes-reservations.php" class="<?= $activePage === 'mes-reservations' ? 'active' : '' ?>">🎟️ Mes réservations</a></li>
                <li>
                  <a href="/messagerie.php" class="<?= $activePage === 'messagerie' ? 'active' : '' ?>">
                    💬 Messagerie
                    <?php if ($unread > 0): ?><span class="nav-badge"><?= $unread ?></span><?php endif; ?>
                  </a>
                </li>
                <li><a href="/profil.php" class="<?= $activePage === 'profil' ? 'active' : '' ?>">⚙️ Profil</a></li>
                <?php if (!empty($user['est_admin'])): ?>
                  <li><a href="/admin.php" class="nav-admin <?= $activePage === 'admin' ? 'active' : '' ?>">👑 Admin</a></li>
                <?php endif; ?>
                <?php if (!$estConducteur): ?>
                  <li><a href="/devenir-conducteur.php" class="user-menu-cta">🚗 Devenir conducteur</a></li>
                <?php endif; ?>
              </ul>

              <div class="user-menu-footer">
                <form method="POST" action="/deconnexion.php" class="inline-form" style="width:100%;">
                  <?= csrfField() ?>
                  <button type="submit" class="user-menu-logout">Déconnexion</button>
                </form>
              </div>
            </div>
          </details>
        <?php else: ?>
          <a href="/connexion.php" class="btn btn-ghost">Se connecter</a>
          <a href="/inscription.php" class="btn btn-primary">S'inscrire</a>
        <?php endif; ?>
      </div>
    </div>
  </header>
