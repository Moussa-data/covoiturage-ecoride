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
      Ce site est édité par <strong>Moussa</strong> dans un cadre exclusivement
      personnel et pédagogique (projet d'apprentissage de développement web).
      <br />
      Aucune activité commerciale n'est exercée via ce site.
    </p>
    <p>
      Contact : <a href="mailto:contact@ecoride.local">contact@ecoride.local</a> (adresse fictive — projet de démonstration)
    </p>
  </section>

  <section class="legal-section">
    <h2>Hébergement</h2>
    <p>
      Le site est hébergé par <strong>AlwaysData</strong>.
      <br />
      Adresse : 91 rue du Faubourg Saint-Honoré, 75008 Paris, France.
      <br />
      Site web : <a href="https://www.alwaysdata.com" target="_blank" rel="noopener">www.alwaysdata.com</a>
    </p>
  </section>

  <section class="legal-section">
    <h2>Données personnelles</h2>
    <p>
      Les seules données collectées sont celles que tu fournis volontairement à
      l'inscription (nom, email) et lors de la publication d'un trajet.
      Elles sont stockées dans une base de données SQLite hébergée chez AlwaysData
      et ne sont <strong>partagées avec aucun tiers</strong>.
    </p>
    <p>
      Conformément au RGPD, tu peux à tout moment :
    </p>
    <ul>
      <li>Modifier tes informations dans la section <a href="/profil.php">Mon profil</a></li>
      <li>Demander la suppression de ton compte par email</li>
      <li>Demander une copie de tes données par email</li>
    </ul>
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
