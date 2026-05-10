<?php
/**
 * modifier-trajet.php — Édition d'un trajet existant
 *
 * Très proche de publier.php mais avec UPDATE au lieu d'INSERT.
 * Sécurité : on récupère le trajet via findTrajetById(), puis on
 * VÉRIFIE que user_id correspond à l'utilisateur connecté avant
 * d'afficher le formulaire ou d'enregistrer.
 *
 * On pourrait factoriser le code commun avec publier.php dans un
 * fichier partagé. Pour l'instant on assume la duplication, c'est
 * pédagogiquement plus clair.
 */

require_once __DIR__ . '/lib/init.php';
requireConducteur();

$user = currentUser();
$id   = (int) ($_GET['id'] ?? 0);

// On charge le trajet existant
$trajet = findTrajetById($id);

if ($trajet === null) {
  http_response_code(404);
  $pageTitle = 'Trajet introuvable — EcoRide';
  include __DIR__ . '/partials/header.php';
  echo '<main class="container detail-page"><div class="empty empty-large"><h1>Trajet introuvable</h1><a href="/mes-trajets.php" class="btn btn-primary">Retour</a></div></main>';
  include __DIR__ . '/partials/footer.php';
  exit;
}

// Vérifie que c'est BIEN le propriétaire
$stmt = db()->prepare("SELECT user_id FROM trajets WHERE id = :id");
$stmt->execute(['id' => $id]);
$trajetUserId = (int) ($stmt->fetchColumn() ?: 0);

if ($trajetUserId !== $user['id']) {
  http_response_code(403);
  $pageTitle = 'Accès refusé — EcoRide';
  include __DIR__ . '/partials/header.php';
  echo '<main class="container detail-page"><div class="empty empty-large"><h1>Accès refusé</h1><p>Tu ne peux modifier que tes propres trajets.</p><a href="/mes-trajets.php" class="btn btn-primary">Retour</a></div></main>';
  include __DIR__ . '/partials/footer.php';
  exit;
}


// Pré-remplissage du formulaire avec les valeurs actuelles
$valeurs = [
  'nom_conducteur'   => $trajet['conducteur']['nom'],
  'depart'           => $trajet['depart'],
  'depart_lieu'      => $trajet['depart_lieu'],
  'arrivee'          => $trajet['arrivee'],
  'arrivee_lieu'     => $trajet['arrivee_lieu'],
  'date'             => $trajet['date'],
  'heure_depart'     => $trajet['heure_depart'],
  'heure_arrivee'    => $trajet['heure_arrivee'],
  'places_dispo'     => (string) $trajet['places_dispo'],
  'prix'             => (string) $trajet['prix'],
  'type_vehicule'    => $trajet['type_vehicule'],
  'modele_vehicule'  => $trajet['modele_vehicule'],
  'description'      => $trajet['description'],
  'fumeur'           => $trajet['preferences']['fumeur'],
  'animaux'          => $trajet['preferences']['animaux'],
  'musique'          => $trajet['preferences']['musique'],
  'discussion'       => $trajet['preferences']['discussion'],
];
$erreurs = [];


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!csrfVerify($_POST['csrf_token'] ?? null)) {
    $erreurs['general'] = 'Token CSRF invalide.';
  } else {
    // Récupération + nettoyage (idem publier.php)
    $valeurs['depart']          = trim($_POST['depart']          ?? '');
    $valeurs['depart_lieu']     = trim($_POST['depart_lieu']     ?? '');
    $valeurs['arrivee']         = trim($_POST['arrivee']         ?? '');
    $valeurs['arrivee_lieu']    = trim($_POST['arrivee_lieu']    ?? '');
    $valeurs['date']            = $_POST['date']                 ?? '';
    $valeurs['heure_depart']    = $_POST['heure_depart']         ?? '';
    $valeurs['heure_arrivee']   = $_POST['heure_arrivee']        ?? '';
    $valeurs['places_dispo']    = $_POST['places_dispo']         ?? '';
    $valeurs['prix']            = $_POST['prix']                 ?? '';
    $valeurs['type_vehicule']   = $_POST['type_vehicule']        ?? '';
    $valeurs['modele_vehicule'] = trim($_POST['modele_vehicule'] ?? '');
    $valeurs['description']     = trim($_POST['description']     ?? '');
    $valeurs['fumeur']          = isset($_POST['fumeur']);
    $valeurs['animaux']         = isset($_POST['animaux']);
    $valeurs['musique']         = isset($_POST['musique']);
    $valeurs['discussion']      = $_POST['discussion'] ?? 'selon humeur';

    // Validations (mêmes règles que publier.php)
    if ($valeurs['depart'] === '')        $erreurs['depart'] = 'Requis.';
    if ($valeurs['depart_lieu'] === '')   $erreurs['depart_lieu'] = 'Requis.';
    if ($valeurs['arrivee'] === '')       $erreurs['arrivee'] = 'Requis.';
    if ($valeurs['arrivee_lieu'] === '')  $erreurs['arrivee_lieu'] = 'Requis.';
    if ($valeurs['date'] === '')          $erreurs['date'] = 'Requise.';
    if ($valeurs['heure_depart'] === '')  $erreurs['heure_depart'] = 'Requise.';
    if ($valeurs['heure_arrivee'] === '') $erreurs['heure_arrivee'] = 'Requise.';
    if ($valeurs['heure_depart'] !== '' && $valeurs['heure_arrivee'] !== ''
        && $valeurs['heure_depart'] >= $valeurs['heure_arrivee']) {
      $erreurs['heure_arrivee'] = 'L\'arrivée doit être après le départ.';
    }
    $places = (int) $valeurs['places_dispo'];
    if ($places < 1 || $places > 7) $erreurs['places_dispo'] = 'Entre 1 et 7.';
    $prix = (int) $valeurs['prix'];
    if ($prix <= 0 || $prix > 100) $erreurs['prix'] = 'Entre 1 et 100 €.';
    if (!in_array($valeurs['type_vehicule'], ['electrique','hybride','essence'], true))
      $erreurs['type_vehicule'] = 'Invalide.';
    if ($valeurs['modele_vehicule'] === '') $erreurs['modele_vehicule'] = 'Requis.';

    if (empty($erreurs)) {
      // Recalcul auto de la durée et du CO2
      $co2ParType = ['electrique' => 18.5, 'hybride' => 14.0, 'essence' => 9.5];

      $trajetMaj = [
        'conducteur' => [
          'nom' => $trajet['conducteur']['nom'], // on garde le nom d'origine
        ],
        'depart'         => $valeurs['depart'],
        'depart_lieu'    => $valeurs['depart_lieu'],
        'arrivee'        => $valeurs['arrivee'],
        'arrivee_lieu'   => $valeurs['arrivee_lieu'],
        'heure_depart'   => $valeurs['heure_depart'],
        'heure_arrivee'  => $valeurs['heure_arrivee'],
        'duree'          => calculerDuree($valeurs['heure_depart'], $valeurs['heure_arrivee']),
        'date'           => $valeurs['date'],
        'prix'           => $prix,
        'places_dispo'   => $places,
        'type_vehicule'  => $valeurs['type_vehicule'],
        'modele_vehicule'=> $valeurs['modele_vehicule'],
        'co2_economise_kg' => $co2ParType[$valeurs['type_vehicule']] ?? 10,
        'description'    => $valeurs['description'] ?: 'Pas de description fournie.',
        'preferences'    => [
          'fumeur'     => $valeurs['fumeur'],
          'animaux'    => $valeurs['animaux'],
          'musique'    => $valeurs['musique'],
          'discussion' => $valeurs['discussion'],
        ],
      ];

      if (updateTrajet($id, $user['id'], $trajetMaj)) {
        header('Location: /mes-trajets.php?modifie=1');
        exit;
      } else {
        $erreurs['general'] = 'Aucune modification enregistrée.';
      }
    }
  }
}


$pageTitle  = 'Modifier le trajet — EcoRide';
$activePage = 'mes-trajets';
include __DIR__ . '/partials/header.php';
?>

<main class="container form-page">

  <h1 class="form-title">Modifier mon trajet</h1>
  <p class="form-subtitle">Mets à jour les infos. Les passagers déjà inscrits seront notifiés (à venir).</p>

  <?php if (!empty($erreurs)): ?>
    <div class="form-banner form-banner-error">
      ⚠️ <?= htmlspecialchars($erreurs['general'] ?? 'Le formulaire contient des erreurs.') ?>
    </div>
  <?php endif; ?>

  <form method="POST" action="" class="form-trajet" novalidate>
    <?= csrfField() ?>

    <fieldset class="form-section">
      <legend>Itinéraire</legend>
      <div class="form-row">
        <div class="form-field <?= isset($erreurs['depart']) ? 'has-error' : '' ?>">
          <label for="depart">Ville de départ *</label>
          <input type="text" id="depart" name="depart" value="<?= htmlspecialchars($valeurs['depart']) ?>" />
          <?php if (isset($erreurs['depart'])): ?><span class="error-msg"><?= $erreurs['depart'] ?></span><?php endif; ?>
        </div>
        <div class="form-field <?= isset($erreurs['depart_lieu']) ? 'has-error' : '' ?>">
          <label for="depart_lieu">Lieu précis de départ *</label>
          <input type="text" id="depart_lieu" name="depart_lieu" value="<?= htmlspecialchars($valeurs['depart_lieu']) ?>" />
          <?php if (isset($erreurs['depart_lieu'])): ?><span class="error-msg"><?= $erreurs['depart_lieu'] ?></span><?php endif; ?>
        </div>
      </div>
      <div class="form-row">
        <div class="form-field <?= isset($erreurs['arrivee']) ? 'has-error' : '' ?>">
          <label for="arrivee">Ville d'arrivée *</label>
          <input type="text" id="arrivee" name="arrivee" value="<?= htmlspecialchars($valeurs['arrivee']) ?>" />
          <?php if (isset($erreurs['arrivee'])): ?><span class="error-msg"><?= $erreurs['arrivee'] ?></span><?php endif; ?>
        </div>
        <div class="form-field <?= isset($erreurs['arrivee_lieu']) ? 'has-error' : '' ?>">
          <label for="arrivee_lieu">Lieu précis d'arrivée *</label>
          <input type="text" id="arrivee_lieu" name="arrivee_lieu" value="<?= htmlspecialchars($valeurs['arrivee_lieu']) ?>" />
          <?php if (isset($erreurs['arrivee_lieu'])): ?><span class="error-msg"><?= $erreurs['arrivee_lieu'] ?></span><?php endif; ?>
        </div>
      </div>
    </fieldset>

    <fieldset class="form-section">
      <legend>Date et horaires</legend>
      <div class="form-row">
        <div class="form-field <?= isset($erreurs['date']) ? 'has-error' : '' ?>">
          <label for="date">Date *</label>
          <input type="date" id="date" name="date" value="<?= htmlspecialchars($valeurs['date']) ?>" min="<?= date('Y-m-d') ?>" />
          <?php if (isset($erreurs['date'])): ?><span class="error-msg"><?= $erreurs['date'] ?></span><?php endif; ?>
        </div>
        <div class="form-field <?= isset($erreurs['heure_depart']) ? 'has-error' : '' ?>">
          <label for="heure_depart">Heure de départ *</label>
          <input type="time" id="heure_depart" name="heure_depart" value="<?= htmlspecialchars($valeurs['heure_depart']) ?>" />
          <?php if (isset($erreurs['heure_depart'])): ?><span class="error-msg"><?= $erreurs['heure_depart'] ?></span><?php endif; ?>
        </div>
        <div class="form-field <?= isset($erreurs['heure_arrivee']) ? 'has-error' : '' ?>">
          <label for="heure_arrivee">Heure d'arrivée *</label>
          <input type="time" id="heure_arrivee" name="heure_arrivee" value="<?= htmlspecialchars($valeurs['heure_arrivee']) ?>" />
          <?php if (isset($erreurs['heure_arrivee'])): ?><span class="error-msg"><?= $erreurs['heure_arrivee'] ?></span><?php endif; ?>
        </div>
      </div>
    </fieldset>

    <fieldset class="form-section">
      <legend>Places et prix</legend>
      <div class="form-row">
        <div class="form-field <?= isset($erreurs['places_dispo']) ? 'has-error' : '' ?>">
          <label for="places_dispo">Places disponibles *</label>
          <input type="number" id="places_dispo" name="places_dispo" value="<?= htmlspecialchars($valeurs['places_dispo']) ?>" min="1" max="7" />
          <?php if (isset($erreurs['places_dispo'])): ?><span class="error-msg"><?= $erreurs['places_dispo'] ?></span><?php endif; ?>
        </div>
        <div class="form-field <?= isset($erreurs['prix']) ? 'has-error' : '' ?>">
          <label for="prix">Prix par personne (€) *</label>
          <input type="number" id="prix" name="prix" value="<?= htmlspecialchars($valeurs['prix']) ?>" min="1" max="100" />
          <?php if (isset($erreurs['prix'])): ?><span class="error-msg"><?= $erreurs['prix'] ?></span><?php endif; ?>
        </div>
      </div>
    </fieldset>

    <fieldset class="form-section">
      <legend>Véhicule</legend>
      <div class="form-row">
        <div class="form-field <?= isset($erreurs['type_vehicule']) ? 'has-error' : '' ?>">
          <label for="type_vehicule">Type *</label>
          <select id="type_vehicule" name="type_vehicule">
            <option value="electrique" <?= $valeurs['type_vehicule'] === 'electrique' ? 'selected' : '' ?>>Électrique</option>
            <option value="hybride"    <?= $valeurs['type_vehicule'] === 'hybride'    ? 'selected' : '' ?>>Hybride</option>
            <option value="essence"    <?= $valeurs['type_vehicule'] === 'essence'    ? 'selected' : '' ?>>Essence</option>
          </select>
        </div>
        <div class="form-field <?= isset($erreurs['modele_vehicule']) ? 'has-error' : '' ?>">
          <label for="modele_vehicule">Modèle *</label>
          <input type="text" id="modele_vehicule" name="modele_vehicule" value="<?= htmlspecialchars($valeurs['modele_vehicule']) ?>" />
          <?php if (isset($erreurs['modele_vehicule'])): ?><span class="error-msg"><?= $erreurs['modele_vehicule'] ?></span><?php endif; ?>
        </div>
      </div>
    </fieldset>

    <fieldset class="form-section">
      <legend>Détails et préférences</legend>
      <div class="form-field">
        <label for="description">Description</label>
        <textarea id="description" name="description" rows="4"><?= htmlspecialchars($valeurs['description']) ?></textarea>
      </div>
      <p class="form-label">Préférences</p>
      <div class="checks-grid">
        <label class="check"><input type="checkbox" name="fumeur"  <?= $valeurs['fumeur']  ? 'checked' : '' ?>> Fumeur</label>
        <label class="check"><input type="checkbox" name="animaux" <?= $valeurs['animaux'] ? 'checked' : '' ?>> Animaux</label>
        <label class="check"><input type="checkbox" name="musique" <?= $valeurs['musique'] ? 'checked' : '' ?>> Musique</label>
      </div>
      <div class="form-field">
        <label for="discussion">Discussion</label>
        <select id="discussion" name="discussion">
          <?php foreach (['tranquille', 'selon humeur', 'j\'aime parler'] as $opt): ?>
            <option value="<?= htmlspecialchars($opt) ?>" <?= $valeurs['discussion'] === $opt ? 'selected' : '' ?>><?= htmlspecialchars(ucfirst($opt)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </fieldset>

    <div class="form-actions">
      <a href="/mes-trajets.php" class="btn btn-ghost">Annuler</a>
      <button type="submit" class="btn btn-primary btn-publish">Enregistrer les modifications</button>
    </div>
  </form>
</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
