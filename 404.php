<?php
/**
 * 404.php — Page d'erreur "Non trouvé" personnalisée
 *
 * Activée via la directive ErrorDocument dans .htaccess.
 * Quand un visiteur tape une URL qui n'existe pas, il atterrit ici.
 */
require_once __DIR__ . '/lib/init.php';
http_response_code(404);

$pageTitle  = 'Page introuvable — EcoRide';
$activePage = '';
include __DIR__ . '/partials/header.php';
?>

<main class="container error-page">

  <div class="error-content">
    <span class="error-code">404</span>
    <h1 class="error-title">Cette page a pris la mauvaise route 🚗💨</h1>
    <p class="error-message">
      L'URL que tu cherches n'existe pas (ou plus). Pas de panique, on peut te ramener à bon port.
    </p>

    <div class="error-actions">
      <a href="/" class="btn btn-primary">Retour à l'accueil</a>
      <a href="/a-propos.php" class="btn btn-ghost">À propos d'EcoRide</a>
    </div>

    <div class="error-illustration" aria-hidden="true">
      <svg viewBox="0 0 200 140" xmlns="http://www.w3.org/2000/svg">
        <ellipse cx="100" cy="125" rx="90" ry="8" fill="#dcfce7"/>
        <path d="M0 110 Q 50 80 100 110 T 200 110 L 200 140 L 0 140 Z" fill="#bbf7d0"/>
        <circle cx="30" cy="95" r="14" fill="#16a34a"/>
        <rect x="28" y="100" width="4" height="14" fill="#166534"/>
        <circle cx="170" cy="92" r="16" fill="#16a34a"/>
        <rect x="168" y="98" width="4" height="16" fill="#166534"/>
        <rect x="65" y="85" width="70" height="22" rx="6" fill="#22c55e" transform="rotate(-5 100 96)"/>
        <rect x="80" y="72" width="40" height="18" rx="6" fill="#16a34a" transform="rotate(-5 100 81)"/>
        <circle cx="80" cy="110" r="7" fill="#1f2937"/>
        <circle cx="120" cy="110" r="7" fill="#1f2937"/>
      </svg>
    </div>
  </div>
</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
