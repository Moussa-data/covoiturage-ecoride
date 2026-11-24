<?php
/**
 * comment-ca-marche.php — Fonctionnement du service + questions fréquentes
 */
require_once __DIR__ . '/lib/init.php';

$pageTitle  = 'Comment ça marche — EcoRide';
$activePage = 'comment';
include __DIR__ . '/partials/header.php';

// Questions fréquentes : [question, réponse HTML]
$faq = [
  ['Combien coûte un trajet ?',
   'Le conducteur fixe le prix par passager. Il doit correspondre à un <strong>partage des frais</strong> du trajet (carburant ou électricité, péages, stationnement) : en covoiturage, le conducteur ne doit pas gagner d\'argent. ' . (BETA_MODE ? 'Pendant la phase de test, EcoRide ne prend <strong>aucun frais</strong> : le passager règle sa participation directement au conducteur.' : 'EcoRide ajoute des <strong>frais de service de 15 %</strong> (0,30 € minimum), affichés avant la réservation. Exemple : trajet à 2,50 € → 2,88 € au total.')],
  ['Est-ce que je peux réserver dès maintenant ?',
   BETA_MODE
     ? 'Oui, si vous avez reçu un <strong>code d\'invitation</strong> : EcoRide est en phase de test entre proches. Les réservations fonctionnent, et la participation se règle directement au conducteur.'
     : 'Pas encore : EcoRide est en <strong>version de démonstration</strong>. Vous pouvez créer un compte, publier un trajet et découvrir le site, mais les réservations sont désactivées et aucun trajet réel n\'est organisé.'],
  ['Comment annuler une réservation ?',
   'Depuis « Mes réservations », à tout moment. La place est aussitôt remise en ligne pour d\'autres passagers.'],
  ['Comment devenir conducteur ?',
   'Créez un compte, puis activez le rôle conducteur depuis votre menu. Vous confirmez avoir un permis valide et un véhicule assuré et en règle.'],
  ['Qui peut voir mes informations ?',
   'Les autres utilisateurs voient votre nom, vos trajets et vos avis. Votre adresse e-mail n\'est jamais affichée. Tout est détaillé dans la <a href="/confidentialite.php">politique de confidentialité</a>.'],
  ['Comment est calculé le CO₂ économisé ?',
   'C\'est une estimation à partir de la durée du trajet et du type de véhicule. La méthode est expliquée sur la page <a href="/a-propos.php#methode-co2">À propos</a>.'],
  ['Comment supprimer mon compte ?',
   'Écrivez à <a href="mailto:m.wanecamara@gmail.com">m.wanecamara@gmail.com</a> depuis l\'adresse de votre compte : il est supprimé avec vos trajets, réservations, messages et avis.'],
];
?>

<main class="container about-page">

  <section class="about-hero">
    <h1>Comment ça marche</h1>
    <p class="about-tagline">De la recherche au trajet, en quelques étapes.</p>
  </section>

  <section class="etapes-grid" aria-label="Les étapes">
    <div class="etapes-col">
      <h2>Vous êtes passager</h2>
      <ol class="etapes">
        <li><strong>Cherchez un trajet</strong> en indiquant votre départ, votre arrivée et la date.</li>
        <li><strong>Choisissez</strong> selon l'horaire, le prix, le véhicule et les avis du conducteur.</li>
        <li><strong>Réservez votre place</strong> et échangez avec le conducteur par la messagerie.</li>
        <li><strong>Voyagez</strong>, puis laissez un avis pour aider les prochains passagers.</li>
      </ol>
    </div>
    <div class="etapes-col">
      <h2>Vous êtes conducteur</h2>
      <ol class="etapes">
        <li><strong>Activez le rôle conducteur</strong> depuis votre compte.</li>
        <li><strong>Publiez votre trajet</strong> : lieux, horaires, places et prix par passager.</li>
        <li><strong>Recevez les réservations</strong> et retrouvez vos passagers dans « Mes trajets ».</li>
        <li><strong>Partagez les frais</strong> du trajet que vous faisiez déjà.</li>
      </ol>
    </div>
  </section>

  <section class="about-section">
    <h2>Questions fréquentes</h2>
    <div class="faq">
      <?php foreach ($faq as [$question, $reponse]): ?>
        <details class="faq-item">
          <summary><?= htmlspecialchars($question) ?></summary>
          <p><?= $reponse /* HTML écrit par nous, pas par un utilisateur */ ?></p>
        </details>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="about-cta">
    <h2>Une autre question ?</h2>
    <p>Écrivez-nous, la réponse arrive en général sous 48 heures.</p>
    <div class="about-cta-buttons">
      <a href="/contact.php" class="btn btn-primary">Nous contacter</a>
    </div>
  </section>

</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
