<?php
/**
 * contact.php — Page de contact
 *
 * Volontairement sans formulaire : un lien e-mail évite le spam
 * et ne nécessite pas de serveur d'envoi d'e-mails.
 */
require_once __DIR__ . '/lib/init.php';

$pageTitle  = 'Contact — EcoRide';
$activePage = 'contact';
include __DIR__ . '/partials/header.php';
?>

<main class="container legal-page">

  <h1>Contact</h1>
  <p class="legal-intro">Une question, un problème sur un trajet ou une remarque sur le site ?</p>

  <section class="legal-section">
    <h2>Par e-mail</h2>
    <p>
      <a href="mailto:m.wanecamara@gmail.com?subject=EcoRide" class="btn btn-primary">m.wanecamara@gmail.com</a>
    </p>
    <p>Réponse en général sous 48 heures. Pour une demande sur votre compte, écrivez depuis l'adresse e-mail de ce compte.</p>
  </section>

  <section class="legal-section">
    <h2>Avant d'écrire</h2>
    <p>
      La réponse est peut-être déjà dans <a href="/comment-ca-marche.php">Comment ça marche</a>
      (questions fréquentes).
    </p>
  </section>

  <section class="legal-section">
    <h2>En cas d'urgence pendant un trajet</h2>
    <p>
      Appelez le <strong>112</strong> (numéro d'urgence européen) ou le <strong>17</strong> (police).
      EcoRide n'est pas un service d'urgence.
    </p>
  </section>

</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
