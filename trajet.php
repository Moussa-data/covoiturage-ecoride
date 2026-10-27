<?php
/**
 * trajet.php — Page de détail d'un trajet
 *
 * Cette page reçoit un ID dans l'URL (ex: trajet.php?id=2)
 * et affiche les détails du trajet correspondant.
 *
 * Étapes du fichier :
 * 1. Récupérer l'ID depuis l'URL avec $_GET
 * 2. Charger le fichier JSON et le décoder
 * 3. Trouver le trajet correspondant à l'ID
 * 4. Si pas trouvé → afficher une page d'erreur
 * 5. Si trouvé → afficher la page complète
 */


require_once __DIR__ . '/lib/init.php';

// L'utilisateur connecté (ou null), pour personnaliser le bouton de réservation
$user = currentUser();


// =============================================================
// 1. Récupérer l'ID depuis l'URL
// =============================================================
// $_GET est un tableau qui contient les paramètres de l'URL.
// Pour trajet.php?id=2 → $_GET['id'] vaudra "2" (toujours en string).
// (int) convertit en entier — important pour comparer avec les ID du JSON.
$id = (int) ($_GET['id'] ?? 0);


// =============================================================
// 2. Chercher le trajet via notre lib
// =============================================================
// findTrajetById() est défini dans lib/trajets.php : il s'occupe
// de charger le JSON et de trouver le bon trajet.
$trajet = findTrajetById($id);


// =============================================================
// 4. Si pas trouvé, on affiche une page d'erreur 404
// =============================================================
if ($trajet === null) {
  // header() permet de renvoyer un statut HTTP. 404 = "Not Found".
  // Important pour le SEO et la sémantique.
  http_response_code(404);

  $pageTitle = 'Trajet introuvable — EcoRide';
  include __DIR__ . '/partials/header.php';
  ?>
  <main class="container detail-page">
    <div class="empty empty-large">
      <h1>Trajet introuvable</h1>
      <p>Le trajet n°<?= htmlspecialchars((string) $id) ?> n'existe pas ou a été supprimé.</p>
      <a href="/" class="btn btn-primary">Retour à l'accueil</a>
    </div>
  </main>
  <?php
  include __DIR__ . '/partials/footer.php';
  exit; // on arrête le script ici, on n'affiche pas la suite
}


// =============================================================
// 5. Tout est bon, on affiche la page de détail complète
// =============================================================

// Petites variables pratiques pour la suite (évite de répéter $trajet[...] partout)
$conducteur = $trajet['conducteur'];
$prefs      = $trajet['preferences'];

// Calcul du prix total (prix + petite commission EcoRide pour donner un exemple)
$commission = 1; // 1 € de commission EcoRide
$prixTotal  = $trajet['prix'] + $commission;

// Le titre de la page (vu dans l'onglet du navigateur)
$pageTitle  = $trajet['depart'] . ' → ' . $trajet['arrivee'] . ' — EcoRide';
$activePage = 'recherche';

include __DIR__ . '/partials/header.php';
?>

<main class="container detail-page">

  <?php if (isset($_GET['publie']) && $_GET['publie'] === '1'): ?>
    <div class="form-banner form-banner-success">
      ✅ Bravo ! Ton trajet est publié et visible par les passagers.
    </div>
  <?php endif; ?>

  <?php if (($_GET['avis'] ?? '') === 'ok'): ?>
    <div class="form-banner form-banner-success">
      ⭐ Merci ! Ton avis a bien été publié.
    </div>
  <?php endif; ?>

  <?php if (($_GET['reservation'] ?? '') === 'ok'): ?>
    <div class="form-banner form-banner-success">
      🎉 Réservation confirmée ! Retrouve-la dans <a href="/mes-reservations.php">Mes réservations</a>.
    </div>
  <?php elseif (($_GET['reservation'] ?? '') === 'ko'): ?>
    <?php
    $msgErreur = $_SESSION['flash_error'] ?? 'Une erreur est survenue.';
    unset($_SESSION['flash_error']);
    ?>
    <div class="form-banner form-banner-error">
      ⚠️ <?= htmlspecialchars($msgErreur) ?>
    </div>
  <?php endif; ?>

  <!-- Fil d'Ariane (breadcrumb) -->
  <nav class="breadcrumb" aria-label="Fil d'Ariane">
    <a href="/">Accueil</a>
    <span>›</span>
    <a href="/">Résultats</a>
    <span>›</span>
    <span><?= htmlspecialchars($trajet['depart']) ?> → <?= htmlspecialchars($trajet['arrivee']) ?></span>
  </nav>

  <div class="detail-layout">

    <!-- ========== Colonne gauche : infos du trajet ========== -->
    <div class="detail-main">

      <!-- En-tête du trajet -->
      <section class="detail-header">
        <div class="detail-route">
          <div class="route-block">
            <span class="route-time"><?= htmlspecialchars($trajet['heure_depart']) ?></span>
            <span class="route-city"><?= htmlspecialchars($trajet['depart']) ?></span>
            <span class="route-place"><?= htmlspecialchars($trajet['depart_lieu']) ?></span>
          </div>
          <div class="route-arrow">
            <span class="duree">— <?= htmlspecialchars($trajet['duree']) ?> —</span>
          </div>
          <div class="route-block">
            <span class="route-time"><?= htmlspecialchars($trajet['heure_arrivee']) ?></span>
            <span class="route-city"><?= htmlspecialchars($trajet['arrivee']) ?></span>
            <span class="route-place"><?= htmlspecialchars($trajet['arrivee_lieu']) ?></span>
          </div>
        </div>

        <div class="detail-date">
          <?php
          // On formate la date en français : "vendredi 15 mai 2026"
          // strftime est déprécié en PHP 8.1+, on utilise IntlDateFormatter à la place.
          $date = new DateTime($trajet['date']);
          $formatter = new IntlDateFormatter('fr_FR', IntlDateFormatter::FULL, IntlDateFormatter::NONE);
          echo htmlspecialchars($formatter->format($date));
          ?>
        </div>
      </section>

      <!-- Conducteur -->
      <section class="detail-driver-card">
        <h2>Le conducteur</h2>
        <?php
        // Si le trajet est lié à un user, on calcule sa vraie note moyenne (AVG des avis).
        $stmt = db()->prepare("SELECT user_id FROM trajets WHERE id = :id");
        $stmt->execute(['id' => $trajet['id']]);
        $cuid = (int) ($stmt->fetchColumn() ?: 0);
        $stats = $cuid > 0 ? getNoteMoyenneConducteur($cuid) : ['moyenne' => null, 'nombre' => 0];
        ?>
        <div class="driver-row">
          <span class="avatar-initiale driver-avatar" aria-hidden="true"><?= htmlspecialchars(mb_strtoupper(mb_substr($conducteur['nom'], 0, 1))) ?></span>
          <div class="driver-info">
            <div class="driver-name">
              <?= htmlspecialchars($conducteur['nom']) ?>
              <?php if ($conducteur['verifie']): ?>
                <span class="verified-badge" title="Profil vérifié">✓ Vérifié</span>
              <?php endif; ?>
            </div>
            <div class="driver-meta">
              <?php if ($stats['moyenne'] !== null): ?>
                ★ <?= number_format($stats['moyenne'], 1) ?>
                · <?= $stats['nombre'] ?> avis
              <?php else: ?>
                ★ <?= number_format($conducteur['note'], 1) ?>
                <?php if ($cuid > 0): ?><span class="driver-meta-hint">(pas encore d'avis)</span><?php endif; ?>
              <?php endif; ?>
              · <?= htmlspecialchars((string) $conducteur['trajets_effectues']) ?> trajets effectués
            </div>
          </div>
        </div>
        <p class="driver-description"><?= nl2br(htmlspecialchars($trajet['description'])) ?></p>
      </section>

      <!-- Véhicule + préférences -->
      <section class="detail-vehicle-card">
        <h2>Le véhicule et les préférences</h2>

        <div class="info-grid">
          <div class="info-item">
            <span class="info-label">Modèle</span>
            <span class="info-value"><?= htmlspecialchars($trajet['modele_vehicule']) ?></span>
          </div>
          <div class="info-item">
            <span class="info-label">Type</span>
            <span class="info-value"><?= htmlspecialchars(ucfirst($trajet['type_vehicule'])) ?></span>
          </div>
          <div class="info-item">
            <span class="info-label">Places disponibles</span>
            <span class="info-value"><?= htmlspecialchars((string) $trajet['places_dispo']) ?></span>
          </div>
        </div>

        <ul class="prefs-list">
          <li class="<?= $prefs['fumeur'] ? 'pref-yes' : 'pref-no' ?>">
            <?= $prefs['fumeur'] ? '✓' : '✗' ?> Fumeur <?= $prefs['fumeur'] ? 'autorisé' : 'non autorisé' ?>
          </li>
          <li class="<?= $prefs['animaux'] ? 'pref-yes' : 'pref-no' ?>">
            <?= $prefs['animaux'] ? '✓' : '✗' ?> Animaux <?= $prefs['animaux'] ? 'acceptés' : 'non acceptés' ?>
          </li>
          <li class="<?= $prefs['musique'] ? 'pref-yes' : 'pref-no' ?>">
            <?= $prefs['musique'] ? '✓' : '✗' ?> Musique
          </li>
          <li class="pref-info">
            💬 Discussion : <?= htmlspecialchars($prefs['discussion']) ?>
          </li>
        </ul>
      </section>

      <!-- Impact écologique -->
      <section class="detail-eco-card">
        <h2>Impact écologique</h2>
        <p>
          En covoiturant ce trajet, vous économisez
          <strong><?= htmlspecialchars((string) $trajet['co2_economise_kg']) ?> kg de CO₂</strong>
          par rapport à un trajet en voiture individuelle 🌱
        </p>
      </section>

      <!-- Avis sur ce trajet -->
      <?php $avis = getAvisByTrajet($trajet['id']); ?>
      <?php if (!empty($avis)): ?>
        <section class="detail-avis-card">
          <h2>Avis des passagers (<?= count($avis) ?>)</h2>
          <ul class="avis-list">
            <?php foreach ($avis as $a): ?>
              <li class="avis-item">
                <div class="avis-header">
                  <strong><?= htmlspecialchars($a['passager_nom']) ?></strong>
                  <span class="avis-stars" aria-label="<?= $a['note'] ?> sur 5">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                      <?= $i <= $a['note'] ? '★' : '☆' ?>
                    <?php endfor; ?>
                  </span>
                  <span class="avis-date">
                    <?= htmlspecialchars(date('d/m/Y', strtotime($a['cree_le']))) ?>
                  </span>
                </div>
                <?php if (!empty($a['commentaire'])): ?>
                  <p class="avis-comment"><?= nl2br(htmlspecialchars($a['commentaire'])) ?></p>
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
        </section>
      <?php endif; ?>
    </div>

    <!-- ========== Colonne droite : récapitulatif & réservation ========== -->
    <aside class="detail-sidebar">
      <div class="recap">
        <h2>Récapitulatif</h2>

        <div class="recap-row">
          <span>Prix du trajet</span>
          <span><?= htmlspecialchars((string) $trajet['prix']) ?> €</span>
        </div>

        <div class="recap-row">
          <span>Commission EcoRide</span>
          <span><?= htmlspecialchars((string) $commission) ?> €</span>
        </div>

        <div class="recap-row recap-total">
          <span>Total</span>
          <span><?= htmlspecialchars((string) $prixTotal) ?> €</span>
        </div>

        <?php
        // ====== Logique du bouton de réservation ======
        // On affiche un état différent selon la situation :
        //  1. pas connecté    → bouton "Connecte-toi pour réserver"
        //  2. propre trajet   → message "Tu es le conducteur"
        //  3. déjà réservé    → message "Tu as réservé"
        //  4. plus de places  → bouton "Complet" désactivé
        //  5. tout va bien    → vrai formulaire POST de réservation

        // On va chercher le user_id du conducteur depuis la BDD
        $stmt = db()->prepare("SELECT user_id FROM trajets WHERE id = :id");
        $stmt->execute(['id' => $trajet['id']]);
        $trajetUserId = (int) ($stmt->fetchColumn() ?: 0);

        $estLeConducteur = $user && $user['id'] === $trajetUserId;
        $aDejaReserve    = $user && userHasReserved($user['id'], $trajet['id']);
        $estComplet      = $trajet['places_dispo'] <= 0;
        ?>

        <?php if (!$user): ?>
          <a href="/connexion.php" class="btn btn-primary btn-reserve">
            Connecte-toi pour réserver
          </a>
        <?php elseif ($estLeConducteur): ?>
          <div class="reserve-info">
            🚗 Tu es le conducteur de ce trajet.
          </div>
        <?php elseif ($aDejaReserve): ?>
          <div class="reserve-info reserve-info-success">
            ✅ Tu as déjà réservé ce trajet.
          </div>
          <?php if ($trajetUserId > 0): ?>
            <a href="/conversation.php?trajet=<?= $trajet['id'] ?>&avec=<?= $trajetUserId ?>"
               class="btn btn-ghost" style="width:100%;margin-top:8px;">
              💬 Contacter le conducteur
            </a>
          <?php endif; ?>
        <?php elseif ($estComplet): ?>
          <button type="button" class="btn btn-reserve" disabled>
            Trajet complet
          </button>
        <?php else: ?>
          <form method="POST" action="/reserver.php" onsubmit="return confirm('Confirmer la réservation pour <?= $prixTotal ?> € ?');">
            <?= csrfField() ?>
            <input type="hidden" name="trajet_id" value="<?= $trajet['id'] ?>">
            <button type="submit" class="btn btn-primary btn-reserve">
              Réserver ce trajet
            </button>
          </form>
        <?php endif; ?>

        <p class="recap-note">
          🛡️ Annulation gratuite jusqu'à 24h avant le départ.
        </p>
      </div>
    </aside>
  </div>
</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
