<?php
/**
 * publier.php — Formulaire pour publier un nouveau trajet
 *
 * C'est LA page la plus formatrice du projet jusqu'ici.
 * Elle te montre le cycle complet d'un formulaire en PHP :
 *
 *   1. Première visite (GET)  → on affiche un formulaire vide
 *   2. L'utilisateur soumet (POST) → PHP reçoit les données dans $_POST
 *   3. On valide chaque champ
 *   4. Si erreurs → on ré-affiche le formulaire avec les messages
 *   5. Si OK → on sauvegarde et on redirige vers la page d'accueil
 *
 * Astuce pédagogique : le même fichier sert pour le GET ET le POST.
 * C'est ce qu'on appelle un "formulaire auto-référent" (self-referencing).
 * Tu verras ça partout en PHP.
 */

require_once __DIR__ . '/lib/init.php';

// Cette page est protégée : il faut être connecté ET conducteur
requireConducteur();

// On récupère l'utilisateur connecté pour pré-remplir son nom
$user = currentUser();

// Bannière de bienvenue après promotion en conducteur
$promu = (($_GET['promu'] ?? '') === '1');


// On prépare 2 tableaux qui vont vivre tout le long de la page :
//  - $valeurs : les valeurs actuelles des champs (pour les ré-afficher en cas d'erreur)
//  - $erreurs : les messages d'erreur par champ (vide si tout va bien)
$valeurs = [
  'nom_conducteur'   => $user['nom'],
  'depart'           => '',
  'depart_lieu'      => '',
  'arrivee'          => '',
  'arrivee_lieu'     => '',
  'date'             => '',
  'heure_depart'     => '',
  'heure_arrivee'    => '',
  'places_dispo'     => '2',
  'prix'             => '',
  'type_vehicule'    => 'electrique',
  'modele_vehicule'  => '',
  'description'      => '',
  'fumeur'           => false,
  'animaux'          => false,
  'musique'          => true,
  'discussion'       => 'selon humeur',
];
$erreurs = [];
$succes  = false;


// =============================================================
// Traitement du POST (quand l'utilisateur soumet le formulaire)
// =============================================================
// $_SERVER['REQUEST_METHOD'] vaut "GET" à la première visite, "POST" à la soumission.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  // -------- 1. On récupère et nettoie les valeurs envoyées --------
  // trim() enlève les espaces avant/après. Toujours penser à nettoyer.
  $valeurs['nom_conducteur']  = trim($_POST['nom_conducteur']  ?? '');
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
  // Les checkboxes : présentes dans $_POST si cochées, absentes sinon.
  $valeurs['fumeur']          = isset($_POST['fumeur']);
  $valeurs['animaux']         = isset($_POST['animaux']);
  $valeurs['musique']         = isset($_POST['musique']);
  $valeurs['discussion']      = $_POST['discussion'] ?? 'selon humeur';


  // -------- 2. Validation --------
  // On remplit $erreurs avec les problèmes trouvés.

  if ($valeurs['nom_conducteur'] === '') {
    $erreurs['nom_conducteur'] = 'Ton nom est requis.';
  } elseif (mb_strlen($valeurs['nom_conducteur']) > 50) {
    $erreurs['nom_conducteur'] = 'Le nom est trop long (50 caractères max).';
  }

  if ($valeurs['depart'] === '') {
    $erreurs['depart'] = 'La ville de départ est requise.';
  }
  if ($valeurs['depart_lieu'] === '') {
    $erreurs['depart_lieu'] = 'Le lieu précis de départ est requis (gare, place, etc.).';
  }
  if ($valeurs['arrivee'] === '') {
    $erreurs['arrivee'] = 'La ville d\'arrivée est requise.';
  }
  if ($valeurs['arrivee_lieu'] === '') {
    $erreurs['arrivee_lieu'] = 'Le lieu précis d\'arrivée est requis.';
  }

  // Date : doit être aujourd'hui ou plus tard
  if ($valeurs['date'] === '') {
    $erreurs['date'] = 'La date est requise.';
  } else {
    $aujourdhui = date('Y-m-d');
    if ($valeurs['date'] < $aujourdhui) {
      $erreurs['date'] = 'La date ne peut pas être dans le passé.';
    }
  }

  // Heures
  if ($valeurs['heure_depart'] === '') {
    $erreurs['heure_depart'] = 'L\'heure de départ est requise.';
  }
  if ($valeurs['heure_arrivee'] === '') {
    $erreurs['heure_arrivee'] = 'L\'heure d\'arrivée est requise.';
  }
  if ($valeurs['heure_depart'] !== '' && $valeurs['heure_arrivee'] !== ''
      && $valeurs['heure_depart'] >= $valeurs['heure_arrivee']) {
    $erreurs['heure_arrivee'] = 'L\'arrivée doit être après le départ.';
  }

  // Places : entier entre 1 et 7
  $places = (int) $valeurs['places_dispo'];
  if ($places < 1 || $places > 7) {
    $erreurs['places_dispo'] = 'Le nombre de places doit être entre 1 et 7.';
  }

  // Prix : entier > 0 et raisonnable
  $prix = (int) $valeurs['prix'];
  if ($prix <= 0) {
    $erreurs['prix'] = 'Le prix doit être supérieur à 0.';
  } elseif ($prix > 100) {
    $erreurs['prix'] = 'Le prix semble trop élevé pour un covoiturage (max 100 €).';
  }

  // Type de véhicule : doit être dans la liste autorisée
  $typesAutorises = ['electrique', 'hybride', 'essence'];
  if (!in_array($valeurs['type_vehicule'], $typesAutorises, true)) {
    $erreurs['type_vehicule'] = 'Type de véhicule invalide.';
  }

  if ($valeurs['modele_vehicule'] === '') {
    $erreurs['modele_vehicule'] = 'Le modèle de la voiture est requis.';
  }

  if ($valeurs['description'] !== '' && mb_strlen($valeurs['description']) > 500) {
    $erreurs['description'] = 'La description est trop longue (500 caractères max).';
  }


  // -------- 3. Si tout est bon, on enregistre --------
  if (empty($erreurs)) {
    // On construit l'objet trajet au format attendu par le JSON.
    $nouveauTrajet = [
      // pas d'id ici, addTrajet() s'en charge
      'conducteur' => [
        'nom'                => $valeurs['nom_conducteur'],
        'note'               => 5.0,                // nouveau conducteur, pas encore de note
        'avatar'             => null,               // pas de photo : on affiche l'initiale du prénom
        'trajets_effectues'  => 0,
        'verifie'            => false,
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
      // co2_economise_kg : addTrajet() le calcule selon le type de véhicule
      'description'    => $valeurs['description'] ?: 'Pas de description fournie.',
      'preferences'    => [
        'fumeur'     => $valeurs['fumeur'],
        'animaux'    => $valeurs['animaux'],
        'musique'    => $valeurs['musique'],
        'discussion' => $valeurs['discussion'],
      ],
      // On lie le trajet à l'utilisateur connecté.
      // Plus tard, "Mes trajets" filtrera là-dessus.
      'user_id'        => $user['id'],
    ];

    $nouveauId = addTrajet($nouveauTrajet);

    // Redirection vers la page de détail du nouveau trajet.
    // header('Location: ...') doit être appelée AVANT toute sortie HTML.
    // exit pour stopper le script (sinon le HTML qui suit serait quand même envoyé).
    header('Location: /trajet.php?id=' . $nouveauId . '&publie=1');
    exit;
  }
}


// =============================================================
// Affichage du formulaire (GET + POST en cas d'erreurs)
// =============================================================
$pageTitle  = 'Publier un trajet — EcoRide';
$activePage = 'publier';

include __DIR__ . '/partials/header.php';
?>

<main class="container form-page">

  <h1 class="form-title">Publier un trajet</h1>
  <p class="form-subtitle">Partage ton trajet, économise et aide la planète. Remplis les infos ci-dessous.</p>

  <?php if ($promu): ?>
    <div class="form-banner form-banner-success">
      🎉 Bienvenue parmi les conducteurs ! Tu peux maintenant publier ton premier trajet.
    </div>
  <?php endif; ?>

  <?php if (!empty($erreurs)): ?>
    <div class="form-banner form-banner-error">
      ⚠️ Le formulaire contient des erreurs. Corrige-les avant de soumettre.
    </div>
  <?php endif; ?>

  <!--
    method="POST"  → les données vont dans $_POST (et pas dans l'URL)
    action=""      → le formulaire se soumet à la même page (publier.php)
    novalidate     → on désactive la validation HTML5 native pour montrer
                     la validation PHP (sinon le navigateur bloque avant)
  -->
  <form method="POST" action="" class="form-trajet" novalidate>

    <!-- =========== Section 1 : itinéraire =========== -->
    <fieldset class="form-section">
      <legend>Itinéraire</legend>

      <div class="form-row">
        <div class="form-field <?= isset($erreurs['depart']) ? 'has-error' : '' ?>">
          <label for="depart">Ville de départ *</label>
          <input type="text" id="depart" name="depart" value="<?= htmlspecialchars($valeurs['depart']) ?>" placeholder="Paris" />
          <?php if (isset($erreurs['depart'])): ?>
            <span class="error-msg"><?= htmlspecialchars($erreurs['depart']) ?></span>
          <?php endif; ?>
        </div>

        <div class="form-field <?= isset($erreurs['depart_lieu']) ? 'has-error' : '' ?>">
          <label for="depart_lieu">Lieu précis de départ *</label>
          <input type="text" id="depart_lieu" name="depart_lieu" value="<?= htmlspecialchars($valeurs['depart_lieu']) ?>" placeholder="Gare de Lyon" />
          <?php if (isset($erreurs['depart_lieu'])): ?>
            <span class="error-msg"><?= htmlspecialchars($erreurs['depart_lieu']) ?></span>
          <?php endif; ?>
        </div>
      </div>

      <div class="form-row">
        <div class="form-field <?= isset($erreurs['arrivee']) ? 'has-error' : '' ?>">
          <label for="arrivee">Ville d'arrivée *</label>
          <input type="text" id="arrivee" name="arrivee" value="<?= htmlspecialchars($valeurs['arrivee']) ?>" placeholder="Lyon" />
          <?php if (isset($erreurs['arrivee'])): ?>
            <span class="error-msg"><?= htmlspecialchars($erreurs['arrivee']) ?></span>
          <?php endif; ?>
        </div>

        <div class="form-field <?= isset($erreurs['arrivee_lieu']) ? 'has-error' : '' ?>">
          <label for="arrivee_lieu">Lieu précis d'arrivée *</label>
          <input type="text" id="arrivee_lieu" name="arrivee_lieu" value="<?= htmlspecialchars($valeurs['arrivee_lieu']) ?>" placeholder="Part-Dieu" />
          <?php if (isset($erreurs['arrivee_lieu'])): ?>
            <span class="error-msg"><?= htmlspecialchars($erreurs['arrivee_lieu']) ?></span>
          <?php endif; ?>
        </div>
      </div>
    </fieldset>


    <!-- =========== Section 2 : date et heures =========== -->
    <fieldset class="form-section">
      <legend>Date et horaires</legend>

      <div class="form-row">
        <div class="form-field <?= isset($erreurs['date']) ? 'has-error' : '' ?>">
          <label for="date">Date *</label>
          <input type="date" id="date" name="date" value="<?= htmlspecialchars($valeurs['date']) ?>" min="<?= date('Y-m-d') ?>" />
          <?php if (isset($erreurs['date'])): ?>
            <span class="error-msg"><?= htmlspecialchars($erreurs['date']) ?></span>
          <?php endif; ?>
        </div>

        <div class="form-field <?= isset($erreurs['heure_depart']) ? 'has-error' : '' ?>">
          <label for="heure_depart">Heure de départ *</label>
          <input type="time" id="heure_depart" name="heure_depart" value="<?= htmlspecialchars($valeurs['heure_depart']) ?>" />
          <?php if (isset($erreurs['heure_depart'])): ?>
            <span class="error-msg"><?= htmlspecialchars($erreurs['heure_depart']) ?></span>
          <?php endif; ?>
        </div>

        <div class="form-field <?= isset($erreurs['heure_arrivee']) ? 'has-error' : '' ?>">
          <label for="heure_arrivee">Heure d'arrivée *</label>
          <input type="time" id="heure_arrivee" name="heure_arrivee" value="<?= htmlspecialchars($valeurs['heure_arrivee']) ?>" />
          <?php if (isset($erreurs['heure_arrivee'])): ?>
            <span class="error-msg"><?= htmlspecialchars($erreurs['heure_arrivee']) ?></span>
          <?php endif; ?>
        </div>
      </div>
    </fieldset>


    <!-- =========== Section 3 : places et prix =========== -->
    <fieldset class="form-section">
      <legend>Places et prix</legend>

      <div class="form-row">
        <div class="form-field <?= isset($erreurs['places_dispo']) ? 'has-error' : '' ?>">
          <label for="places_dispo">Places disponibles *</label>
          <input type="number" id="places_dispo" name="places_dispo" value="<?= htmlspecialchars((string) $valeurs['places_dispo']) ?>" min="1" max="7" />
          <?php if (isset($erreurs['places_dispo'])): ?>
            <span class="error-msg"><?= htmlspecialchars($erreurs['places_dispo']) ?></span>
          <?php endif; ?>
        </div>

        <div class="form-field <?= isset($erreurs['prix']) ? 'has-error' : '' ?>">
          <label for="prix">Prix par personne (€) *</label>
          <input type="number" id="prix" name="prix" value="<?= htmlspecialchars((string) $valeurs['prix']) ?>" min="1" max="100" />
          <?php if (isset($erreurs['prix'])): ?>
            <span class="error-msg"><?= htmlspecialchars($erreurs['prix']) ?></span>
          <?php endif; ?>
        </div>
      </div>
    </fieldset>


    <!-- =========== Section 4 : conducteur et véhicule =========== -->
    <fieldset class="form-section">
      <legend>Toi et ta voiture</legend>

      <div class="form-row">
        <div class="form-field <?= isset($erreurs['nom_conducteur']) ? 'has-error' : '' ?>">
          <label for="nom_conducteur">Ton nom (ou prénom + initiale) *</label>
          <input type="text" id="nom_conducteur" name="nom_conducteur" value="<?= htmlspecialchars($valeurs['nom_conducteur']) ?>" placeholder="Moussa W." />
          <?php if (isset($erreurs['nom_conducteur'])): ?>
            <span class="error-msg"><?= htmlspecialchars($erreurs['nom_conducteur']) ?></span>
          <?php endif; ?>
        </div>

        <div class="form-field <?= isset($erreurs['type_vehicule']) ? 'has-error' : '' ?>">
          <label for="type_vehicule">Type de véhicule *</label>
          <select id="type_vehicule" name="type_vehicule">
            <option value="electrique" <?= $valeurs['type_vehicule'] === 'electrique' ? 'selected' : '' ?>>Électrique</option>
            <option value="hybride"    <?= $valeurs['type_vehicule'] === 'hybride'    ? 'selected' : '' ?>>Hybride</option>
            <option value="essence"    <?= $valeurs['type_vehicule'] === 'essence'    ? 'selected' : '' ?>>Essence</option>
          </select>
          <?php if (isset($erreurs['type_vehicule'])): ?>
            <span class="error-msg"><?= htmlspecialchars($erreurs['type_vehicule']) ?></span>
          <?php endif; ?>
        </div>
      </div>

      <div class="form-field <?= isset($erreurs['modele_vehicule']) ? 'has-error' : '' ?>">
        <label for="modele_vehicule">Modèle de la voiture *</label>
        <input type="text" id="modele_vehicule" name="modele_vehicule" value="<?= htmlspecialchars($valeurs['modele_vehicule']) ?>" placeholder="Renault Zoé" />
        <?php if (isset($erreurs['modele_vehicule'])): ?>
          <span class="error-msg"><?= htmlspecialchars($erreurs['modele_vehicule']) ?></span>
        <?php endif; ?>
      </div>
    </fieldset>


    <!-- =========== Section 5 : description et préférences =========== -->
    <fieldset class="form-section">
      <legend>Détails et préférences</legend>

      <div class="form-field <?= isset($erreurs['description']) ? 'has-error' : '' ?>">
        <label for="description">Description du trajet (optionnel)</label>
        <textarea id="description" name="description" rows="4" placeholder="Quelques mots sur ton trajet, le confort, les pauses prévues..."><?= htmlspecialchars($valeurs['description']) ?></textarea>
        <?php if (isset($erreurs['description'])): ?>
          <span class="error-msg"><?= htmlspecialchars($erreurs['description']) ?></span>
        <?php endif; ?>
      </div>

      <p class="form-label">Préférences</p>
      <div class="checks-grid">
        <label class="check"><input type="checkbox" name="fumeur"  <?= $valeurs['fumeur']  ? 'checked' : '' ?>> Fumeur autorisé</label>
        <label class="check"><input type="checkbox" name="animaux" <?= $valeurs['animaux'] ? 'checked' : '' ?>> Animaux acceptés</label>
        <label class="check"><input type="checkbox" name="musique" <?= $valeurs['musique'] ? 'checked' : '' ?>> Musique pendant le trajet</label>
      </div>

      <div class="form-field">
        <label for="discussion">Niveau de discussion</label>
        <select id="discussion" name="discussion">
          <?php foreach (['tranquille', 'selon humeur', 'j\'aime parler'] as $option): ?>
            <option value="<?= htmlspecialchars($option) ?>" <?= $valeurs['discussion'] === $option ? 'selected' : '' ?>>
              <?= htmlspecialchars(ucfirst($option)) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </fieldset>


    <!-- =========== Boutons =========== -->
    <div class="form-actions">
      <a href="/" class="btn btn-ghost">Annuler</a>
      <button type="submit" class="btn btn-primary btn-publish">Publier mon trajet</button>
    </div>

  </form>
</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
