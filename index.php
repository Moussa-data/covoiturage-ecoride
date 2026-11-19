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
  'depart'  => trim($_GET['depart']  ?? ''),
  'arrivee' => trim($_GET['arrivee'] ?? ''),
  'date'    => $_GET['date']         ?? '',
];

// Si l'utilisateur a soumis le formulaire au moins une fois, on filtre.
// Sinon, on affiche tous les trajets disponibles.
$rechercheFaite = ($filtres['depart'] !== '' || $filtres['arrivee'] !== '' || $filtres['date'] !== '');
// Uniquement les vrais trajets à venir (les trajets de démo sont écartés).
$trajets = searchTrajets($filtres, true);

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

      <div class="search-stop search-stop-depart">
        <span class="stop-dot" aria-hidden="true"></span>
        <label for="depart">Départ</label>
        <input type="text" id="depart" name="depart" list="villes-idf" placeholder="Vaucresson, Versailles…"
               value="<?= htmlspecialchars($filtres['depart']) ?>" />
      </div>

      <span class="search-line" aria-hidden="true"></span>

      <div class="search-stop search-stop-arrivee">
        <span class="stop-dot" aria-hidden="true"></span>
        <label for="arrivee">Arrivée</label>
        <input type="text" id="arrivee" name="arrivee" list="villes-idf" placeholder="La Défense, Paris…"
               value="<?= htmlspecialchars($filtres['arrivee']) ?>" />
      </div>

      <div class="search-stop search-stop-date">
        <label for="date">Date</label>
        <input type="date" id="date" name="date" value="<?= htmlspecialchars($filtres['date']) ?>" />
      </div>

      <button type="submit" class="btn btn-primary btn-search">Rechercher</button>
    </form>

    <?php include __DIR__ . '/partials/villes-idf.php'; ?>

    <ul class="hero-points">
      <li>Moins de CO₂ par passager</li>
      <li>Frais partagés avec le conducteur</li>
      <li>Profils et avis vérifiés</li>
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
<?php else: ?>
  <!-- Cas B : pas encore assez de données → message qualitatif (preuve sociale "narrative") -->
  <section class="public-stats">
    <div class="container public-stats-launch">
      <h2 class="public-stats-title">Une communauté qui grandit chaque jour 🌱</h2>
      <p class="public-stats-tagline">
        Soyez parmi les premiers à covoiturer écolo. Les pionniers font la communauté.
      </p>
      <div class="public-stats-cta">
        <a href="/inscription.php" class="btn btn-primary">Créer mon compte</a>
        <a href="/devenir-conducteur.php" class="btn btn-ghost">Proposer un trajet</a>
      </div>
    </div>
  </section>
<?php endif; ?>

<!-- ========== RÉSULTATS ========== -->
<section class="results">
  <div class="container results-inner">

    <h2 class="results-title">
      <?php if ($rechercheFaite): ?>
        Résultats pour
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
          <?php foreach ($trajets as $t): ?>
            <?php
              // Carte d'exemple = simple bloc (pas de lien) ; vrai trajet = lien vers le détail
              $balise = $modeExemples ? 'div' : 'a';
              $lien   = $modeExemples ? '' : ' href="trajet.php?id=' . (int) $t['id'] . '"';
            ?>
            <<?= $balise ?><?= $lien ?> class="trip-card<?= $modeExemples ? ' trip-card-exemple' : '' ?>"
               data-prix="<?= $t['prix'] ?>"
               data-type="<?= htmlspecialchars($t['type_vehicule']) ?>">

              <?php
                // Nom affiché avec une majuscule ("moussa" → "Moussa")
                $nomConducteur = mb_strtoupper(mb_substr($t['conducteur']['nom'], 0, 1)) . mb_substr($t['conducteur']['nom'], 1);
                $avatar = $t['conducteur']['avatar'] ?? '';
                $nouveau = ($t['conducteur']['trajets_effectues'] ?? 0) === 0;
                $labelsVehicule = ['electrique' => '⚡ Électrique', 'hybride' => '🔋 Hybride', 'essence' => '⛽ Essence'];
              ?>
              <div class="trip-driver">
                <?php if ($avatar): ?>
                  <!-- alt vide : le nom est déjà écrit juste à côté (évite qu'il soit lu deux fois) -->
                  <img src="<?= htmlspecialchars($avatar) ?>" alt="" />
                <?php else: ?>
                  <span class="avatar-initiale" aria-hidden="true"><?= htmlspecialchars(mb_substr($nomConducteur, 0, 1)) ?></span>
                <?php endif; ?>
                <div>
                  <div class="name"><?= htmlspecialchars($nomConducteur) ?></div>
                  <?php if ($nouveau): ?>
                    <div class="rating rating-nouveau">Nouveau conducteur</div>
                  <?php else: ?>
                    <div class="rating">★ <?= number_format($t['conducteur']['note'], 1) ?></div>
                  <?php endif; ?>
                </div>
              </div>

              <div class="trip-route">
                <span class="time"><?= htmlspecialchars($t['heure_depart']) ?> → <?= htmlspecialchars($t['heure_arrivee']) ?></span>
                <span class="place"><?= htmlspecialchars($t['depart_lieu']) ?> <span class="place-sep" aria-label="vers">→</span> <?= htmlspecialchars($t['arrivee_lieu']) ?></span>
                <span class="duree"><?= htmlspecialchars($t['duree']) ?> · <?= $t['places_dispo'] ?> place<?= $t['places_dispo'] > 1 ? 's' : '' ?></span>
                <span class="badge-vehicule badge-<?= htmlspecialchars($t['type_vehicule']) ?>">
                  <?= $labelsVehicule[$t['type_vehicule']] ?? htmlspecialchars($t['type_vehicule']) ?>
                </span>
              </div>

              <div class="trip-eco" title="CO₂ économisé">
                <strong>−<?= str_replace('.', ',', (string) round((float) $t['co2_economise_kg'], 1)) ?> kg</strong>
                de CO₂
              </div>

              <div class="trip-action">
                <span class="price"><?= formatPrix((float) $t['prix']) ?> €</span>
                <?php if ($modeExemples): ?>
                  <span class="badge-exemple">Exemple</span>
                <?php else: ?>
                  <span class="btn btn-primary btn-card-cta">Voir le trajet</span>
                <?php endif; ?>
              </div>
            </<?= $balise ?>>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

    </div>
  </div>
</section>

<script src="/assets/js/script.js"></script>

<?php include __DIR__ . '/partials/footer.php'; ?>
