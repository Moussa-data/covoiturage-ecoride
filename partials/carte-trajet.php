<?php
/**
 * partials/carte-trajet.php — Carte d'un trajet dans une liste
 *
 * Variables attendues :
 *   $t             le trajet (tableau renvoyé par shapeTrajetRow)
 *   $modeExemples  true pour une carte d'exemple (non cliquable)
 *   $passagers     nombre de passagers recherchés (transmis à la page du trajet)
 */
$passagers = $passagers ?? 1;
?>
<?php
  // Carte d'exemple = simple bloc (pas de lien) ; vrai trajet = lien vers le détail
  $balise = $modeExemples ? 'div' : 'a';
  $lien   = $modeExemples ? '' : ' href="trajet.php?id=' . (int) $t['id'] . ($passagers > 1 ? '&places=' . (int) $passagers : '') . '"';
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
    <span class="trip-date"><?= htmlspecialchars(dateCourte($t['date'])) ?></span>
    <span class="time"><?= htmlspecialchars($t['heure_depart']) ?> → <?= htmlspecialchars($t['heure_arrivee']) ?></span>
    <span class="place"><?= htmlspecialchars($t['depart_lieu']) ?> <span class="place-sep" aria-label="vers">→</span> <?= htmlspecialchars($t['arrivee_lieu']) ?></span>
    <span class="duree">
      <?= htmlspecialchars($t['duree']) ?> ·
      <?php if ($t['places_dispo'] <= 0): ?>
        <strong class="badge-complet">Complet</strong>
      <?php else: ?>
        <?= $t['places_dispo'] ?> place<?= $t['places_dispo'] > 1 ? 's' : '' ?>
      <?php endif; ?>
    </span>
    <span class="trip-badges">
    <span class="badge-vehicule badge-<?= htmlspecialchars($t['type_vehicule']) ?>">
      <?= $labelsVehicule[$t['type_vehicule']] ?? htmlspecialchars($t['type_vehicule']) ?>
    </span>
    <?php if (!empty($t['serie_id'])): ?>
      <span class="badge-mini badge-regulier" title="Ce conducteur fait ce trajet régulièrement">↻ Régulier</span>
    <?php endif; ?>
    <?php if (isset($t['reservation_auto']) && !$t['reservation_auto']): ?>
      <span class="badge-mini badge-demande" title="Le conducteur accepte chaque réservation">Sur demande</span>
    <?php endif; ?>
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
