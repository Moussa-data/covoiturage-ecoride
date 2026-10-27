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
$trajets = $rechercheFaite ? searchTrajets($filtres) : loadTrajets();

$pageTitle  = 'EcoRide — Covoiturage écologique';
$activePage = 'accueil';

include __DIR__ . '/partials/header.php';
?>

<!-- ========== HERO ========== -->
<section class="hero">
  <div class="container hero-inner">

    <div class="hero-text">
      <h1>
        Covoiturez. Économisez.<br />
        Agissez pour la planète.
      </h1>
      <p class="hero-subtitle">
        EcoRide vous met en relation avec des conducteurs et passagers
        pour des trajets plus économiques et écologiques.
      </p>
    </div>

    <div class="hero-illustration" aria-hidden="true">
      <svg viewBox="0 0 200 140" xmlns="http://www.w3.org/2000/svg">
        <ellipse cx="100" cy="125" rx="90" ry="8" fill="#dcfce7"/>
        <path d="M0 110 Q 50 80 100 110 T 200 110 L 200 140 L 0 140 Z" fill="#bbf7d0"/>
        <circle cx="30" cy="95" r="14" fill="#16a34a"/>
        <rect x="28" y="100" width="4" height="14" fill="#166534"/>
        <circle cx="170" cy="92" r="16" fill="#16a34a"/>
        <rect x="168" y="98" width="4" height="16" fill="#166534"/>
        <rect x="65" y="85" width="70" height="22" rx="6" fill="#22c55e"/>
        <rect x="80" y="72" width="40" height="18" rx="6" fill="#16a34a"/>
        <circle cx="80" cy="110" r="7" fill="#1f2937"/>
        <circle cx="120" cy="110" r="7" fill="#1f2937"/>
        <rect x="83" y="76" width="14" height="11" rx="2" fill="#dcfce7"/>
        <rect x="103" y="76" width="14" height="11" rx="2" fill="#dcfce7"/>
      </svg>
    </div>
  </div>

  <!-- ========== BARRE DE RECHERCHE (méthode GET) ========== -->
  <!--
    method="GET" : les valeurs partent dans l'URL (?depart=...&arrivee=...).
    action=""    : envoie sur la même page.
    Avantage GET : on peut bookmarker / partager l'URL.
  -->
  <div class="container">
    <form class="search-bar" method="GET" action="" autocomplete="off">

      <div class="field">
        <label for="depart">Départ</label>
        <input type="text" id="depart" name="depart" placeholder="Ville de départ"
               value="<?= htmlspecialchars($filtres['depart']) ?>" />
      </div>

      <div class="field">
        <label for="arrivee">Arrivée</label>
        <input type="text" id="arrivee" name="arrivee" placeholder="Ville d'arrivée"
               value="<?= htmlspecialchars($filtres['arrivee']) ?>" />
      </div>

      <div class="field">
        <label for="date">Date</label>
        <input type="date" id="date" name="date" value="<?= htmlspecialchars($filtres['date']) ?>" />
      </div>

      <button type="submit" class="btn btn-primary btn-search">Rechercher</button>
    </form>

    <ul class="badges">
      <li><span class="dot"></span> Moins d'émissions de CO₂</li>
      <li><span class="dot"></span> Économique</li>
      <li><span class="dot"></span> Convivial et sécurisé</li>
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
        Sois parmi les premiers à covoiturer écolo. Les pionniers font la communauté.
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
      <?php else: ?>
        Tous les trajets disponibles
      <?php endif; ?>
      <small><?= count($trajets) ?> trajet<?= count($trajets) > 1 ? 's' : '' ?></small>
    </h2>

    <div class="results-layout">

      <!-- Filtres à gauche (client-side : JS instantané) -->
      <aside class="filters" aria-label="Filtres">
        <h3>Filtres</h3>

        <div class="filter-group">
          <label for="prixMax">Prix maximum : <span id="prixMaxValue">20</span> €</label>
          <input type="range" id="prixMax" min="5" max="20" value="20" />
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
              ? 'Aucun trajet ne correspond à ta recherche. Essaie d\'élargir les critères.'
              : 'Aucun trajet disponible pour l\'instant.' ?>
          </div>
        <?php else: ?>
          <?php foreach ($trajets as $t): ?>
            <a href="trajet.php?id=<?= $t['id'] ?>" class="trip-card"
               data-prix="<?= $t['prix'] ?>"
               data-type="<?= htmlspecialchars($t['type_vehicule']) ?>">

              <div class="trip-driver">
                <span class="avatar-initiale" aria-hidden="true"><?= htmlspecialchars(mb_strtoupper(mb_substr($t['conducteur']['nom'], 0, 1))) ?></span>
                <div>
                  <div class="name"><?= htmlspecialchars($t['conducteur']['nom']) ?></div>
                  <div class="rating">★ <?= number_format($t['conducteur']['note'], 1) ?></div>
                </div>
              </div>

              <div class="trip-route">
                <span class="time"><?= htmlspecialchars($t['heure_depart']) ?> → <?= htmlspecialchars($t['heure_arrivee']) ?></span>
                <span class="place"><?= htmlspecialchars($t['depart_lieu']) ?> · <?= htmlspecialchars($t['arrivee_lieu']) ?></span>
                <span class="duree"><?= htmlspecialchars($t['duree']) ?> · <?= $t['places_dispo'] ?> place<?= $t['places_dispo'] > 1 ? 's' : '' ?></span>
              </div>

              <div class="trip-eco" title="CO₂ économisé">
                <strong>−<?= htmlspecialchars((string) $t['co2_economise_kg']) ?> kg</strong>
                de CO₂
              </div>

              <div class="trip-action">
                <span class="price"><?= $t['prix'] ?> €</span>
                <span class="btn btn-primary btn-card-cta">Voir le trajet</span>
              </div>
            </a>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

    </div>
  </div>
</section>

<script src="/assets/js/script.js"></script>

<?php include __DIR__ . '/partials/footer.php'; ?>
