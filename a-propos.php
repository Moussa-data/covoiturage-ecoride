<?php
/**
 * a-propos.php — Page de présentation du projet
 */
require_once __DIR__ . '/lib/init.php';

$pageTitle  = 'À propos — EcoRide';
$activePage = 'a-propos';
include __DIR__ . '/partials/header.php';
?>

<main class="container about-page">

  <section class="about-hero">
    <h1>EcoRide, le covoiturage du quotidien</h1>
    <p class="about-tagline">
      Partager la voiture qui fait déjà votre trajet : moins de voitures sur la route,
      des frais partagés et moins de CO₂.
    </p>
  </section>

  <section class="about-section">
    <h2>Notre mission</h2>
    <p>
      Beaucoup de trajets du quotidien, comme aller au travail, à la gare ou à l'aéroport,
      se font seul en voiture. EcoRide met en relation les conducteurs qui font déjà ce
      trajet et les passagers qui vont au même endroit.
    </p>
    <p>
      Chaque trajet affiche une estimation du CO₂ économisé, pour que chacun voie
      l'effet de son choix. <a href="#methode-co2">Comment est-ce calculé ?</a>
    </p>
  </section>

  <section class="about-grid">
    <div class="about-card">
      <span class="about-icon">🌍</span>
      <h3>Écologique</h3>
      <p>Chaque passager en plus, c'est une voiture en moins sur la route. Le type de véhicule (électrique, hybride, essence) est affiché sur chaque trajet.</p>
    </div>
    <div class="about-card">
      <span class="about-icon">💰</span>
      <h3>Économique</h3>
      <p>Le passager participe aux frais du trajet (carburant, péages). Le conducteur dépense moins, le passager paie souvent moins qu'en transports.</p>
    </div>
    <div class="about-card">
      <span class="about-icon">🤝</span>
      <h3>Convivial</h3>
      <p>Une messagerie pour s'organiser, des avis après chaque trajet et des préférences (musique, animaux, discussion) pour bien s'accorder.</p>
    </div>
    <div class="about-card">
      <span class="about-icon">🛡️</span>
      <h3>Sécurisé</h3>
      <p>Mots de passe chiffrés, connexion protégée contre les tentatives répétées, et données hébergées en France.</p>
    </div>
  </section>

  <section class="about-section" id="methode-co2">
    <h2>Comment le CO₂ économisé est-il calculé ?</h2>
    <p>
      C'est une <strong>estimation simple</strong>, pas une mesure exacte :
    </p>
    <ol class="methode-liste">
      <li>
        <strong>La distance</strong> est estimée à partir de la durée du trajet : environ 35 km/h
        pendant la première heure (ville, périphérique), puis environ 85 km/h (autoroute).
      </li>
      <li>
        <strong>L'économie par kilomètre</strong> dépend du véhicule du conducteur : 0,10 kg de CO₂/km
        pour une électrique, 0,07 kg pour une hybride, 0,05 kg pour une essence. Plus la voiture
        partagée est propre, plus le passager évite d'émissions par rapport à un trajet seul
        dans sa propre voiture.
      </li>
      <li>
        <strong>Le résultat</strong> est la distance multipliée par ce facteur, pour un passager.
      </li>
    </ol>
    <p>
      Exemple : 25 minutes en voiture électrique donnent environ 15 km, soit environ 1,5 kg de CO₂ évité.
      Cette méthode sera remplacée par un calcul basé sur la distance réelle de l'itinéraire.
    </p>
  </section>

  <section class="about-section">
    <h2>Le projet</h2>
    <p>
      EcoRide est conçu et développé par <strong>Moussa Wane Camara</strong>, développeur web
      fullstack freelance. Le site est entièrement codé à la main, sans framework :
      PHP, SQLite, HTML, CSS et JavaScript.
    </p>
    <p>
      <a href="https://github.com/Moussa-data/covoiturage-ecoride" class="btn btn-ghost" target="_blank" rel="noopener">Voir le code source sur GitHub</a>
    </p>
  </section>

  <section class="about-cta">
    <h2>Rejoignez la communauté</h2>
    <p>Conducteurs et passagers : partagez vos trajets du quotidien.</p>
    <div class="about-cta-buttons">
      <a href="/inscription.php" class="btn btn-primary">Créer mon compte</a>
      <a href="/" class="btn btn-ghost">Voir les trajets</a>
    </div>
  </section>

</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
