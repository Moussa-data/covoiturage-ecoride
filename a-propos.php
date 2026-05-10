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
    <h1>EcoRide, le covoiturage écologique 🌱</h1>
    <p class="about-tagline">
      Un trajet à la fois, on réinvente la mobilité — moins de voitures, moins de CO₂, plus de rencontres.
    </p>
  </section>

  <section class="about-section">
    <h2>Notre mission</h2>
    <p>
      Chaque jour, des millions de Français parcourent les mêmes routes seuls dans leur voiture.
      EcoRide met en relation conducteurs et passagers pour réduire le nombre de véhicules
      sur les routes — et avec lui, les émissions de CO₂.
    </p>
    <p>
      Notre différence : on met l'<strong>impact écologique au cœur</strong> de l'expérience.
      Chaque trajet affiche ses kilos de CO₂ économisés. Vous voyez, en temps réel,
      l'impact de votre choix.
    </p>
  </section>

  <section class="about-grid">
    <div class="about-card">
      <span class="about-icon">🌍</span>
      <h3>Écologique</h3>
      <p>Privilégie les véhicules électriques et hybrides. Calcule et affiche le CO₂ économisé.</p>
    </div>
    <div class="about-card">
      <span class="about-icon">💰</span>
      <h3>Économique</h3>
      <p>Partager les frais d'un trajet, c'est jusqu'à 4× moins cher qu'un train ou une voiture seule.</p>
    </div>
    <div class="about-card">
      <span class="about-icon">🤝</span>
      <h3>Convivial</h3>
      <p>Une messagerie pour discuter, des avis pour rassurer, des préférences pour s'accorder.</p>
    </div>
    <div class="about-card">
      <span class="about-icon">🛡️</span>
      <h3>Sécurisé</h3>
      <p>Authentification chiffrée, mots de passe hashés, comptes vérifiés. Tes données sont protégées.</p>
    </div>
  </section>

  <section class="about-section">
    <h2>Le projet</h2>
    <p>
      EcoRide est un projet d'apprentissage construit par <strong>Moussa</strong>, dev fullstack
      en formation autodidacte. Le site est codé entièrement à la main : pas de framework,
      pas de générateur, juste du PHP, du SQL et du JavaScript vanilla — pour vraiment
      comprendre comment fonctionne une vraie application web.
    </p>
    <p>
      <a href="https://github.com" class="btn btn-ghost">Voir le code source sur GitHub</a>
    </p>
  </section>

  <section class="about-cta">
    <h2>Rejoins la communauté</h2>
    <p>Pionniers, passagers, conducteurs : ensemble on roule plus écolo.</p>
    <div class="about-cta-buttons">
      <a href="/inscription.php" class="btn btn-primary">Créer mon compte</a>
      <a href="/" class="btn btn-ghost">Voir les trajets</a>
    </div>
  </section>

</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
