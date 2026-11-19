<?php
/**
 * confidentialite.php — Politique de confidentialité (RGPD)
 *
 * Version adaptée au site de démonstration. À faire relire par un
 * professionnel avant tout lancement commercial.
 */
require_once __DIR__ . '/lib/init.php';

$pageTitle  = 'Politique de confidentialité — EcoRide';
$activePage = '';
include __DIR__ . '/partials/header.php';
?>

<main class="container legal-page">

  <h1>Politique de confidentialité</h1>
  <p class="legal-intro">Mise à jour : 19/11/2026.</p>

  <section class="legal-section">
    <h2>Qui est responsable de vos données ?</h2>
    <p>
      Le responsable du traitement est <strong>Moussa Wane Camara</strong>, éditeur du site.
      Pour toute question sur vos données :
      <a href="mailto:m.wanecamara@gmail.com">m.wanecamara@gmail.com</a>.
    </p>
  </section>

  <section class="legal-section">
    <h2>Quelles données sont collectées, et pourquoi ?</h2>
    <ul>
      <li><strong>Votre compte</strong> : nom, adresse e-mail et mot de passe (stocké sous forme chiffrée, jamais en clair). Ils servent à créer votre compte et à vous connecter.</li>
      <li><strong>Vos trajets</strong> : villes, lieux, date, horaires, prix, véhicule et préférences. Ils sont affichés publiquement pour que les passagers puissent les trouver.</li>
      <li><strong>Vos échanges</strong> : réservations, messages et avis. Ils servent à organiser un trajet entre conducteur et passager.</li>
      <li><strong>La sécurité</strong> : les tentatives de connexion échouées (adresse IP sous forme chiffrée et heure), pour bloquer les attaques par force brute.</li>
    </ul>
    <p>
      Base légale : l'exécution des conditions d'utilisation que vous acceptez en créant un compte,
      et notre intérêt légitime à sécuriser le site pour les tentatives de connexion.
    </p>
  </section>

  <section class="legal-section">
    <h2>Qui peut voir vos données ?</h2>
    <ul>
      <li>Les autres utilisateurs voient votre nom, vos trajets publiés et vos avis.</li>
      <li>Votre adresse e-mail n'est jamais affichée aux autres utilisateurs.</li>
      <li>L'hébergeur <strong>AlwaysData</strong> (Paris, France) stocke les données sur ses serveurs. Aucune donnée n'est transférée hors de l'Union européenne.</li>
      <li>Aucune donnée n'est vendue ni utilisée pour de la publicité.</li>
    </ul>
  </section>

  <section class="legal-section">
    <h2>Combien de temps sont-elles gardées ?</h2>
    <ul>
      <li>Compte, trajets, messages et avis : tant que votre compte existe. Ils sont supprimés quand vous demandez la suppression de votre compte.</li>
      <li>Tentatives de connexion échouées : 24 heures.</li>
      <li>Liens de réinitialisation du mot de passe : 1 heure, puis ils ne fonctionnent plus.</li>
    </ul>
  </section>

  <section class="legal-section">
    <h2>Vos droits</h2>
    <p>
      Vous pouvez à tout moment accéder à vos données, les corriger, les supprimer, vous opposer
      à leur traitement ou en demander une copie (portabilité). Vous pouvez corriger votre nom et
      votre e-mail dans <a href="/profil.php">Mon profil</a>. Pour les autres demandes, écrivez à
      <a href="mailto:m.wanecamara@gmail.com">m.wanecamara@gmail.com</a> : une réponse vous est
      apportée sous un mois au maximum.
    </p>
    <p>
      Si vous estimez que vos droits ne sont pas respectés, vous pouvez déposer une réclamation
      auprès de la <a href="https://www.cnil.fr/fr/plaintes" target="_blank" rel="noopener">CNIL</a>.
    </p>
  </section>

  <section class="legal-section">
    <h2>Cookies</h2>
    <p>
      Le site utilise un seul cookie, <code>PHPSESSID</code>, indispensable pour rester connecté.
      Il n'y a ni cookie publicitaire ni outil de mesure d'audience : aucun bandeau de consentement
      n'est donc nécessaire. Les polices de caractères sont hébergées sur le site lui-même.
    </p>
  </section>

</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
