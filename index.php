<?php
/**
 * index.php — Page d'accueil avec recherche server-side
 *
 * Nouveau dans cette version :
 *  - On lit les paramètres ?depart=Paris&arrivee=Lyon&date=... dans $_GET
 *  - On filtre les trajets en BDD via searchTrajets()
 *  - On rend les cartes EN PHP (plus besoin de JS pour ça)
 *  - Les filtres prix/véhicule en sidebar restent en JS (instantanés)
 *
 * Avantages du server-side :
 *  - SEO : Google peut lire les résultats
 *  - URL partageable : envoyer un lien avec une recherche déjà faite
 *  - Pas de "flash de chargement" : le contenu est là dès le premier paint
 */

require_once __DIR__ . '/lib/init.php';

// Récupération des filtres de recherche depuis l'URL
$filtres = [
  'depart'    => trim($_GET['depart']  ?? ''),
  'arrivee'   => trim($_GET['arrivee'] ?? ''),
  'date'      => $_GET['date']         ?? '',
  'passagers' => max(1, min(4, (int) ($_GET['passagers'] ?? 1))),
];
$dateRetour = $_GET['retour'] ?? '';

// Si l'utilisateur a soumis le formulaire au moins une fois, on filtre.
// Sinon, on affiche tous les trajets disponibles.
$rechercheFaite = ($filtres['depart'] !== '' || $filtres['arrivee'] !== '' || $filtres['date'] !== '' || $filtres['passagers'] > 1);
// Uniquement les vrais trajets à venir (les trajets de démo sont écartés).
$trajets = searchTrajets($filtres, true);

// Aller-retour : si une date de retour est donnée, on cherche aussi le trajet
// inverse (arrivée → départ) ce jour-là.
$trajetsRetour = null;
if ($dateRetour !== '' && ($filtres['depart'] !== '' || $filtres['arrivee'] !== '')) {
  $trajetsRetour = searchTrajets([
    'depart'    => $filtres['arrivee'],
    'arrivee'   => $filtres['depart'],
    'date'      => $dateRetour,
    'passagers' => $filtres['passagers'],
  ], true);
}

// Tant que personne n'a publié, on montre quelques exemples (non réservables).
$modeExemples = (!$rechercheFaite && empty($trajets));
if ($modeExemples) {
  require_once __DIR__ . '/lib/exemples.php';
  $trajets = trajetsExemples();
}

$pageTitle  = 'EcoRide — Covoiturage écologique';
$activePage = 'accueil';

include __DIR__ . '/partials/header.php';
?>

<!-- ========== HERO ==========
  Le hero est la continuité du header bleu nuit.
  La recherche est dessinée comme une ligne de trajet : un point de départ,
  une ligne, un point d'arrivée (comme sur un plan de métro).
-->
<section class="hero">
  <div class="container hero-inner">

    <h1 class="hero-title">Le même trajet,<br />à plusieurs.</h1>
    <p class="hero-subtitle">
      Trouvez une place dans une voiture qui fait déjà votre route.
      Vous partagez les frais, et les émissions de CO₂ baissent pour tout le monde.
    </p>

    <!-- ========== RECHERCHE (méthode GET) ==========
      method="GET" : les valeurs partent dans l'URL (?depart=...&arrivee=...).
      Avantage : on peut garder ou partager le lien d'une recherche.
    -->
    <form class="search-route" method="GET" action="" autocomplete="off">

      <!-- Ligne 1 : le trajet (départ ● —— ● arrivée) -->
      <div class="search-stop search-stop-depart">
        <span class="stop-dot" aria-hidden="true"></span>
        <label for="depart">Départ</label>
        <input type="text" id="depart" name="depart" list="villes-idf" placeholder="Vaucresson, Versailles…"
               value="<?= htmlspecialchars($filtres['depart']) ?>" />
      </div>

      <div class="search-line-wrap">
        <span class="search-line" aria-hidden="true"></span>
        <!-- Inverse départ et arrivée (pratique pour préparer le retour) -->
        <button type="button" class="btn-inverser" id="btnInverser" aria-label="Inverser départ et arrivée" title="Inverser départ et arrivée">
          <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path d="M7 7h11l-3-3M17 17H6l3 3" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>
      </div>

      <div class="search-stop search-stop-arrivee">
        <span class="stop-dot" aria-hidden="true"></span>
        <label for="arrivee">Arrivée</label>
        <input type="text" id="arrivee" name="arrivee" list="villes-idf" placeholder="La Défense, Paris…"
               value="<?= htmlspecialchars($filtres['arrivee']) ?>" />
      </div>

      <!-- Ligne 2 : quand et combien -->
      <div class="search-ligne2">
      <div class="search-stop search-stop-date">
        <label for="date">Aller</label>
        <input type="date" id="date" name="date" min="<?= date('Y-m-d') ?>" value="<?= htmlspecialchars($filtres['date']) ?>" />
      </div>

      <div class="search-stop search-stop-retour">
        <label for="retour">Retour <span class="label-option">(optionnel)</span></label>
        <input type="date" id="retour" name="retour" min="<?= date('Y-m-d') ?>" value="<?= htmlspecialchars($dateRetour) ?>" />
      </div>

      <div class="search-stop search-stop-passagers">
        <span class="label-faux" id="labelPassagers">Passagers</span>
        <div class="stepper" role="group" aria-labelledby="labelPassagers">
          <button type="button" class="stepper-btn" data-delta="-1" aria-label="Un passager de moins">−</button>
          <output class="stepper-valeur" id="passagersAffiche" aria-live="polite"><?= $filtres['passagers'] ?></output>
          <button type="button" class="stepper-btn" data-delta="1" aria-label="Un passager de plus">+</button>
        </div>
        <input type="hidden" name="passagers" id="passagers" value="<?= $filtres['passagers'] ?>" />
      </div>

      <button type="submit" class="btn btn-primary btn-search">Rechercher</button>
      </div>
    </form>

    <?php include __DIR__ . '/partials/villes-idf.php'; ?>

    <ul class="hero-points">
      <li>Moins de CO₂ par passager</li>
      <li>Frais partagés avec le conducteur</li>
      <li>Avis laissés après chaque trajet</li>
    </ul>
  </div>
</section>

<!-- ========== STATS PUBLIQUES (avec seuil intelligent) ========== -->
<?php $stats = getPublicStats(); ?>
<?php if (shouldShowPublicStats($stats)): ?>
  <!-- Cas A : on a assez de données → preuve sociale chiffrée -->
  <section class="public-stats">
    <div class="container">
      <h2 class="public-stats-title">EcoRide en chiffres 🌱</h2>
      <div class="public-stats-grid">

        <div class="public-stat">
          <span class="public-stat-value"><?= $stats['trajets_dispo'] ?></span>
          <span class="public-stat-label">trajets à venir</span>
        </div>

        <div class="public-stat public-stat-eco">
          <span class="public-stat-value"><?= number_format($stats['co2_economise'], 0, ',', ' ') ?> kg</span>
          <span class="public-stat-label">de CO₂ économisé</span>
        </div>

        <div class="public-stat">
          <span class="public-stat-value"><?= $stats['conducteurs_actifs'] ?></span>
          <span class="public-stat-label">conducteurs actifs</span>
        </div>

        <div class="public-stat">
          <span class="public-stat-value">
            <?= $stats['nb_avis'] > 0 ? '★ ' . number_format($stats['note_moyenne'], 1) : '—' ?>
          </span>
          <span class="public-stat-label">
            <?= $stats['nb_avis'] > 0 ? $stats['nb_avis'] . ' avis' : 'pas encore d\'avis' ?>
          </span>
        </div>

      </div>
    </div>
  </section>
<?php endif; ?>

<?php if (!$rechercheFaite): ?>
  <!-- ========== TRAJETS POPULAIRES ==========
    Chaque carte est un mini-trajet : point de départ, ligne, point d'arrivée.
    Un clic lance la recherche correspondante.
  -->
  <?php $populaires = trajetsPopulaires(6); $avecPrix = $populaires && $populaires[0]['prix_min'] !== null; ?>
  <section class="populaires">
    <div class="container">
      <div class="populaires-entete">
        <h2><?= $avecPrix ? 'Trajets populaires' : 'Où allez-vous cette semaine ?' ?></h2>
        <p><?= $avecPrix ? 'Les liaisons avec le plus de départs en ce moment.' : 'Le trajet de tous les jours en Île-de-France, ou une escapade le week-end : quelques idées pour commencer.' ?></p>
      </div>
      <div class="populaires-grille">
        <?php foreach ($populaires as $p): ?>
          <a class="populaire" href="/?depart=<?= urlencode($p['depart']) ?>&amp;arrivee=<?= urlencode($p['arrivee']) ?>">
            <span class="populaire-trace" aria-hidden="true">
              <span class="populaire-point"></span>
              <span class="populaire-ligne"></span>
              <span class="populaire-point populaire-point-arrivee"></span>
            </span>
            <span class="populaire-villes">
              <span class="populaire-ville"><?= htmlspecialchars($p['depart']) ?></span>
              <span class="populaire-ville"><?= htmlspecialchars($p['arrivee']) ?></span>
            </span>
            <span class="populaire-info">
              <?php if ($p['prix_min'] !== null): ?>
                <span class="populaire-prix">dès <?= formatPrix($p['prix_min']) ?> €</span>
                <span class="populaire-nb"><?= $p['nb'] ?> départ<?= $p['nb'] > 1 ? 's' : '' ?></span>
              <?php else: ?>
                <span class="populaire-nb">Voir les trajets</span>
              <?php endif; ?>
            </span>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
<?php endif; ?>

<?php if (($_GET['compte'] ?? '') === 'supprime'): ?>
  <div class="container"><div class="form-banner form-banner-success" style="margin-top:20px">Votre compte et toutes vos données ont été supprimés.</div></div>
<?php endif; ?>

<!-- ========== RÉSULTATS ========== -->
<section class="results">
  <div class="container results-inner">

    <h2 class="results-title">
      <?php if ($rechercheFaite): ?>
        <?= $trajetsRetour !== null ? 'Aller' : 'Résultats' ?> pour
        <?php if ($filtres['depart']): ?>« <?= htmlspecialchars($filtres['depart']) ?> » → <?php endif; ?>
        <?php if ($filtres['arrivee']): ?>« <?= htmlspecialchars($filtres['arrivee']) ?> »<?php endif; ?>
      <?php elseif ($modeExemples): ?>
        Exemples de trajets
      <?php else: ?>
        Tous les trajets disponibles
      <?php endif; ?>
      <?php if (!$modeExemples): ?>
        <small><?= count($trajets) ?> trajet<?= count($trajets) > 1 ? 's' : '' ?></small>
      <?php endif; ?>
    </h2>

    <?php if ($modeExemples): ?>
      <p class="exemples-info">
        Aucun trajet n'a encore été publié. Voici à quoi ressembleront les annonces.
        <a href="/devenir-conducteur.php">Publier le premier trajet</a>
      </p>
    <?php endif; ?>

    <div class="results-layout">

      <!-- Filtres à gauche (client-side : JS instantané) -->
      <aside class="filters" aria-label="Filtres">
        <h3>Filtres</h3>

        <div class="filter-group">
          <?php
            // Le curseur va jusqu'au prix le plus élevé affiché (au moins 20 €)
            $prixPlafond = max(20, (int) ceil(max(array_map(fn($t) => (float) $t['prix'], $trajets ?: [['prix' => 0]]))));
          ?>
          <label for="prixMax">Prix maximum : <span id="prixMaxValue"><?= $prixPlafond ?></span> €</label>
          <input type="range" id="prixMax" min="1" max="<?= $prixPlafond ?>" step="0.5" value="<?= $prixPlafond ?>" />
        </div>

        <div class="filter-group">
          <p class="filter-label">Type de véhicule</p>
          <label class="check"><input type="checkbox" name="vehicule" value="electrique" checked /> Électrique</label>
          <label class="check"><input type="checkbox" name="vehicule" value="hybride" checked /> Hybride</label>
          <label class="check"><input type="checkbox" name="vehicule" value="essence" checked /> Essence</label>
        </div>

        <button type="button" class="btn btn-ghost btn-reset" id="resetFilters">Réinitialiser</button>
      </aside>

      <!-- Liste des trajets, rendue côté PHP -->
      <div class="trips" id="trips">
        <?php if (empty($trajets)): ?>
          <div class="empty">
            <?= $rechercheFaite
              ? 'Aucun trajet ne correspond à votre recherche. Essayez d\'élargir les critères.'
              : 'Aucun trajet disponible pour l\'instant.' ?>
          </div>
        <?php else: ?>
          <?php $passagers = $filtres['passagers']; ?>
          <?php foreach ($trajets as $t): ?>
            <?php include __DIR__ . '/partials/carte-trajet.php'; ?>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

    </div>

    <?php if ($trajetsRetour !== null): ?>
      <!-- Aller-retour : les trajets du retour (arrivée → départ) -->
      <h2 class="results-title results-title-retour">
        Retour le <?= htmlspecialchars(dateCourte($dateRetour)) ?>
        <small><?= count($trajetsRetour) ?> trajet<?= count($trajetsRetour) > 1 ? 's' : '' ?></small>
      </h2>
      <div class="trips trips-retour">
        <?php if (empty($trajetsRetour)): ?>
          <div class="empty">Aucun trajet retour ce jour-là pour l'instant.</div>
        <?php else: ?>
          <?php $passagers = $filtres['passagers']; ?>
          <?php foreach ($trajetsRetour as $t): ?>
            <?php include __DIR__ . '/partials/carte-trajet.php'; ?>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php if (!$rechercheFaite): ?>
  <!-- ========== CALCULATEUR CONDUCTEUR ==========
    Estimation simple du partage des frais. Le chiffre se met à jour en direct
    (assets/js/calculateur.js). Le prix conseillé reste un partage des frais :
    le conducteur ne doit pas gagner d'argent (définition légale du covoiturage).
  -->
  <section class="calculateur" id="calculateur">
    <div class="container calculateur-inner">
      <div class="calculateur-texte">
        <h2>Vous conduisez déjà ce trajet ?</h2>
        <p>Partagez vos frais avec des passagers qui vont au même endroit. Réglez votre trajet pour voir ce que vous économisez.</p>

        <div class="calc-champ">
          <label for="calcKm">Distance d'un trajet <output id="calcKmAffiche">20</output> km</label>
          <input type="range" id="calcKm" min="5" max="600" step="5" value="20" />
        </div>

        <div class="calc-champ">
          <span class="label-faux">Passagers</span>
          <div class="calc-segments" role="radiogroup" aria-label="Nombre de passagers">
            <?php foreach ([1, 2, 3] as $n): ?>
              <label><input type="radio" name="calcPassagers" value="<?= $n ?>" <?= $n === 2 ? 'checked' : '' ?> /><span><?= $n ?></span></label>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="calc-champ">
          <span class="label-faux">Votre voiture</span>
          <div class="calc-segments" role="radiogroup" aria-label="Type de voiture">
            <label><input type="radio" name="calcVoiture" value="0.13" checked /><span>Essence</span></label>
            <label><input type="radio" name="calcVoiture" value="0.09" /><span>Hybride</span></label>
            <label><input type="radio" name="calcVoiture" value="0.05" /><span>Électrique</span></label>
          </div>
        </div>

        <div class="calc-champ">
          <span class="label-faux">Fréquence</span>
          <div class="calc-segments" role="radiogroup" aria-label="Fréquence">
            <label><input type="radio" name="calcFrequence" value="1" /><span>Une fois</span></label>
            <label><input type="radio" name="calcFrequence" value="40" checked /><span>Aller-retour en semaine</span></label>
          </div>
        </div>
      </div>

      <div class="calculateur-resultat" aria-live="polite">
        <span class="calc-resultat-label" id="calcLabel">Vous économisez environ</span>
        <span class="calc-montant"><span id="calcMontant">0</span> €</span>
        <span class="calc-periode" id="calcPeriode">par mois</span>
        <dl class="calc-detail">
          <div><dt>Prix conseillé par passager</dt><dd id="calcPrix">—</dd></div>
          <div><dt>Coût du trajet (carburant ou électricité)</dt><dd id="calcCout">—</dd></div>
        </dl>
        <a href="/devenir-conducteur.php" class="btn btn-primary">Proposer un trajet</a>
        <p class="calc-note">Estimation hors péages et stationnement. En covoiturage, le prix doit rester un partage des frais : le conducteur ne gagne pas d'argent, il en dépense moins.</p>
      </div>
    </div>
  </section>
<?php endif; ?>

<script src="<?= asset('/assets/js/script.js') ?>"></script>
<script src="<?= asset('/assets/js/recherche.js') ?>"></script>
<script src="<?= asset('/assets/js/calculateur.js') ?>"></script>

<?php include __DIR__ . '/partials/footer.php'; ?>
