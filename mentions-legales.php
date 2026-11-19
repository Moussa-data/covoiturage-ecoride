<?php
/**
 * mentions-legales.php — Page de mentions légales
 *
 * Page basique requise pour tout site français,
 * même sans usage commercial.
 */
require_once __DIR__ . '/lib/init.php';

$pageTitle  = 'Mentions légales — EcoRide';
$activePage = '';
include __DIR__ . '/partials/header.php';
?>

<main class="container legal-page">

  <h1>Mentions légales</h1>
  <p class="legal-intro">
    Mise à jour : <?= date('d/m/Y') ?>.
  </p>

  <section class="legal-section">
    <h2>Éditeur du site</h2>
    <p>
      Ce site est édité par <strong>Moussa Wane Camara</strong> dans un cadre exclusivement
      personnel et pédagogique (projet d'apprentissage de développement web).
      <br />
      Aucune activité commerciale n'est exercée via ce site.
    </p>
    <p>
      Contact : <a href="mailto:m.wanecamara@gmail.com">m.wanecamara@gmail.com</a>
    </p>
  </section>

  <section class="legal-section">
    <h2>Hébergement</h2>
    <p>
      Le site est hébergé par <strong>AlwaysData</strong> (société ALWAYSDATA SARL).
      <br />
      Adresse : 91 rue du Faubourg Saint-Honoré, 75008 Paris, France.
      <br />
      Site web : <a href="https://www.alwaysdata.com" target="_blank" rel="noopener">www.alwaysdata.com</a>
    </p>
  </section>

  <section class="legal-section">
    <h2>Données personnelles</h2>
    <p>
      La collecte et l'utilisation de vos données sont détaillées dans la
      <a href="/confidentialite.php">politique de confidentialité</a>.
    </p>
  </section>

  <section class="legal-section">
    <h2>Cookies</h2>
    <p>
      Ce site utilise un seul cookie technique (<code>PHPSESSID</code>) nécessaire
      au fonctionnement de l'authentification. Aucun cookie de tracking ou de
      publicité n'est utilisé.
    </p>
  </section>

  <section class="legal-section">
    <h2>Propriété intellectuelle</h2>
    <p>
      Le code source d'EcoRide est mis à disposition dans un objectif pédagogique.
      Les contenus produits par les utilisateurs (trajets, avis, messages) restent
      leur propriété.
    </p>
  </section>

  <section class="legal-section">
    <h2>Avertissement</h2>
    <p>
      <strong>EcoRide est un projet de démonstration.</strong>
      Aucun trajet réel n'est organisé via cette plateforme. Les données affichées
      sont à but illustratif uniquement.
    </p>
  </section>

</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
