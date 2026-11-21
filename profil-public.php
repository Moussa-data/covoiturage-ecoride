<?php
/**
 * profil-public.php?id=N — Ce que les autres membres voient d'un conducteur
 * ou d'un passager : présentation, badges de confiance, avis, trajets à venir.
 * Jamais d'adresse e-mail ici.
 */
require_once __DIR__ . '/lib/init.php';

$id = (int) ($_GET['id'] ?? 0);
$p  = $id > 0 ? getProfilPublic($id) : null;

if (!$p) {
  http_response_code(404);
  $pageTitle = 'Profil introuvable — EcoRide';
  include __DIR__ . '/partials/header.php';
  echo '<main class="container form-page"><h1 class="form-title">Profil introuvable</h1>'
     . '<p class="form-subtitle">Ce membre n\'existe pas ou a supprimé son compte.</p>'
     . '<p><a class="btn btn-primary" href="/">Retour à l\'accueil</a></p></main>';
  include __DIR__ . '/partials/footer.php';
  exit;
}

$moi       = currentUser();
$cEstMoi   = $moi && (int) $moi['id'] === $p['id'];
$initiale  = mb_strtoupper(mb_substr($p['nom'], 0, 1));
$moyenne   = $p['note']['moyenne'];
$nbAvis    = (int) $p['note']['nombre'];

$pageTitle       = $p['nom'] . ' — Profil EcoRide';
$pageDescription = 'Profil de ' . $p['nom'] . ' sur EcoRide : avis, trajets proposés et badges de confiance.';
include __DIR__ . '/partials/header.php';
?>

<main class="container profil-public">

  <?php if ($cEstMoi): ?>
    <div class="form-banner form-banner-info">Vous voyez votre profil comme les autres membres. <a href="/profil.php">Modifier ma présentation</a></div>
  <?php endif; ?>

  <section class="pp-entete">
    <span class="pp-avatar" aria-hidden="true"><?= htmlspecialchars($initiale) ?></span>
    <div class="pp-identite">
      <h1><?= htmlspecialchars($p['nom']) ?></h1>
      <p class="pp-depuis">Membre depuis <?= htmlspecialchars(moisAnnee($p['membre_depuis'])) ?></p>
      <ul class="pp-badges">
        <?php if ($p['email_verifie'] || $p['est_demo']): ?>
          <li class="pp-badge pp-badge-ok">✓ E-mail vérifié</li>
        <?php else: ?>
          <li class="pp-badge pp-badge-vide">E-mail non vérifié</li>
        <?php endif; ?>
        <?php if ($p['est_conducteur']): ?>
          <li class="pp-badge">🚗 Conducteur</li>
        <?php endif; ?>
      </ul>
    </div>
  </section>

  <!-- Chiffres : la ligne de route relie les trois -->
  <section class="pp-chiffres" aria-label="En chiffres">
    <div class="pp-chiffre">
      <strong><?= $moyenne !== null ? number_format($moyenne, 1, ',', '') : '–' ?></strong>
      <span><?= $nbAvis > 0 ? '★ sur ' . $nbAvis . ' avis' : 'pas encore d\'avis' ?></span>
    </div>
    <div class="pp-chiffre">
      <strong><?= $p['nb_trajets'] ?></strong>
      <span>trajet<?= $p['nb_trajets'] > 1 ? 's' : '' ?> publié<?= $p['nb_trajets'] > 1 ? 's' : '' ?></span>
    </div>
    <div class="pp-chiffre">
      <strong><?= $p['nb_passagers'] ?></strong>
      <span>passager<?= $p['nb_passagers'] > 1 ? 's' : '' ?> transporté<?= $p['nb_passagers'] > 1 ? 's' : '' ?></span>
    </div>
  </section>

  <section class="pp-bloc">
    <h2>Présentation</h2>
    <?php if (!empty($p['bio'])): ?>
      <p class="pp-bio"><?= nl2br(htmlspecialchars($p['bio'])) ?></p>
    <?php else: ?>
      <p class="pp-vide"><?= htmlspecialchars($p['nom']) ?> n'a pas encore écrit de présentation.</p>
    <?php endif; ?>
  </section>

  <?php if (!empty($p['a_venir'])): ?>
    <section class="pp-bloc">
      <h2>Prochains trajets</h2>
      <ul class="pp-trajets">
        <?php foreach ($p['a_venir'] as $t): ?>
          <li>
            <a href="/trajet.php?id=<?= (int) $t['id'] ?>" class="pp-trajet">
              <span class="pp-trajet-date"><?= htmlspecialchars(date('d/m', strtotime($t['date']))) ?> · <?= htmlspecialchars(substr($t['heure_depart'], 0, 5)) ?></span>
              <span class="pp-trajet-route">
                <span><?= htmlspecialchars($t['depart']) ?></span>
                <span class="pp-trajet-ligne" aria-hidden="true"></span>
                <span><?= htmlspecialchars($t['arrivee']) ?></span>
              </span>
              <span class="pp-trajet-prix"><?= number_format((float) $t['prix'], 2, ',', '') ?> €</span>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    </section>
  <?php endif; ?>

  <section class="pp-bloc">
    <h2>Avis des passagers</h2>
    <?php if (empty($p['avis'])): ?>
      <p class="pp-vide">Aucun avis pour l'instant. Les passagers peuvent en laisser un après le trajet.</p>
    <?php else: ?>
      <ul class="pp-avis">
        <?php foreach ($p['avis'] as $a): ?>
          <li class="pp-avis-item">
            <div class="pp-avis-tete">
              <span class="pp-avis-note" aria-label="<?= (int) $a['note'] ?> sur 5"><?= str_repeat('★', (int) $a['note']) ?><span class="pp-avis-note-vide"><?= str_repeat('★', 5 - (int) $a['note']) ?></span></span>
              <strong><?= htmlspecialchars($a['passager_nom']) ?></strong>
              <span class="pp-avis-trajet"><?= htmlspecialchars($a['depart']) ?> → <?= htmlspecialchars($a['arrivee']) ?></span>
            </div>
            <?php if (!empty($a['commentaire'])): ?>
              <p><?= nl2br(htmlspecialchars($a['commentaire'])) ?></p>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>

</main>

<?php include __DIR__ . '/partials/footer.php'; ?>
