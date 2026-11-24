<?php
/**
 * cgu.php — Conditions générales d'utilisation
 *
 * Version de démonstration. Avant tout lancement commercial, ces CGU
 * devront être complétées (CGV de la commission, paiement, annulations,
 * médiateur de la consommation…) et validées par un juriste.
 */
require_once __DIR__ . '/lib/init.php';

$pageTitle  = 'Conditions générales d\'utilisation — EcoRide';
$activePage = '';
include __DIR__ . '/partials/header.php';
?>

<main class="container legal-page">

  <h1>Conditions générales d'utilisation</h1>
  <p class="legal-intro">Mise à jour : 24/11/2026.</p>

  <?php if (DEMO_MODE): ?>
    <div class="legal-notice">
      EcoRide est actuellement une <strong>version de démonstration</strong> : les réservations sont
      désactivées et aucun trajet réel n'est organisé. Ces conditions seront complétées avant
      l'ouverture du service.
    </div>
  <?php elseif (BETA_MODE): ?>
    <div class="legal-notice">
      EcoRide est en <strong>phase de test (bêta)</strong>, ouverte sur invitation. Le service est
      gratuit et peut encore contenir des erreurs. Merci de les signaler depuis le lien
      « Signaler un problème » en bas de chaque page.
    </div>
  <?php endif; ?>

  <section class="legal-section">
    <h2>1. Le service</h2>
    <p>
      EcoRide met en relation des conducteurs et des passagers qui font le même trajet.
      EcoRide n'est pas un transporteur : le trajet est organisé entre utilisateurs,
      sous leur propre responsabilité.
    </p>
  </section>

  <section class="legal-section">
    <h2>2. Votre compte</h2>
    <ul>
      <li>Vous devez avoir au moins 18 ans pour créer un compte.</li>
      <li>Les informations de votre compte doivent être exactes.</li>
      <li>Votre mot de passe est personnel : ne le partagez pas.</li>
      <li>Vous pouvez supprimer votre compte à tout moment depuis <a href="/profil.php">Mon profil</a>, ou en écrivant à <a href="mailto:m.wanecamara@gmail.com">m.wanecamara@gmail.com</a>.</li>
    </ul>
  </section>

  <section class="legal-section">
    <h2>3. Règles pour les conducteurs</h2>
    <ul>
      <li>Avoir un permis de conduire valide et un véhicule assuré et en règle.</li>
      <li>Publier un trajet que vous faites réellement pour vous-même.</li>
      <li>Fixer un prix qui correspond à un <strong>partage des frais</strong> (carburant ou électricité, péages, stationnement), sans réaliser de bénéfice. C'est ce que prévoit la définition légale du covoiturage (article L3132-1 du Code des transports). Le site applique un prix maximum par passager selon la distance et le nombre de places.</li>
      <li>Vérifier auprès de votre assureur que vos passagers sont couverts : en covoiturage avec simple partage des frais, c'est en général le cas avec une assurance auto classique.</li>
      <li>Respecter le code de la route et la sécurité de vos passagers.</li>
    </ul>
  </section>

  <?php if (BETA_MODE): ?>
  <section class="legal-section">
    <h2>Phase de test : invitation et paiement</h2>
    <ul>
      <li><strong>Invitation</strong> : l'inscription se fait avec un code personnel. Ne le publiez pas sur les réseaux sociaux ; un code peut être désactivé à tout moment.</li>
      <li><strong>Paiement</strong> : pendant la bêta, aucune somme ne transite par EcoRide et aucun frais n'est prélevé. Le passager règle sa participation directement au conducteur (espèces, virement, application de paiement), selon le montant affiché.</li>
      <li><strong>Annulation</strong> : prévenez l'autre personne le plus tôt possible, par la messagerie du site, et annulez votre réservation ou votre trajet pour libérer la place.</li>
      <li><strong>Litiges</strong> : EcoRide n'est pas partie au trajet ni au paiement. En cas de désaccord, les membres règlent la situation entre eux ; vous pouvez nous signaler tout comportement problématique.</li>
      <li><strong>Évolution</strong> : la bêta peut être modifiée, interrompue ou remise à zéro. Les membres seront prévenus par e-mail avant toute suppression de données.</li>
    </ul>
  </section>
  <?php endif; ?>

  <section class="legal-section">
    <h2>4. Règles pour tous</h2>
    <ul>
      <li>Rester respectueux dans les messages et les avis.</li>
      <li>Publier des avis sincères, uniquement après un trajet effectué.</li>
      <li>Ne pas publier de contenu illégal, insultant ou trompeur.</li>
    </ul>
    <p>Un compte qui ne respecte pas ces règles peut être suspendu ou supprimé.</p>
  </section>

  <section class="legal-section">
    <h2>5. Classement des trajets</h2>
    <p>
      Les trajets sont affichés du plus proche au plus lointain, par date puis par heure de départ.
      Aucun conducteur ne paie pour apparaître plus haut. Seuls les trajets à venir sont affichés.
    </p>
  </section>

  <section class="legal-section">
    <h2>6. Avis</h2>
    <p>
      Seul un passager ayant réservé un trajet peut laisser un avis sur ce trajet, après sa date.
      Les avis ne sont ni achetés ni triés : ils sont affichés du plus récent au plus ancien.
    </p>
  </section>

  <section class="legal-section">
    <h2>7. Données personnelles</h2>
    <p>
      La façon dont vos données sont utilisées est expliquée dans la
      <a href="/confidentialite.php">politique de confidentialité</a>.
    </p>
  </section>

</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
