<?php
/**
 * admin.php — Tableau de bord administrateur, en onglets
 *
 *   ?onglet=apercu       Croissance de la bêta (chiffres clés + graphiques 30 jours)
 *   ?onglet=membres      Activité des testeurs + gestion des comptes
 *   ?onglet=trajets      Trajets et réservations
 *   ?onglet=invitations  Codes d'invitation : qui a amené qui
 *   ?onglet=sante        Santé du site : signalements, e-mails, erreurs, sauvegardes
 *
 * NOTION : POST → REDIRECTION → GET
 *   Après une action (supprimer, créer un code…), on redirige vers la page.
 *   Recharger la page ne renvoie donc jamais le formulaire une 2e fois.
 *   Le message de résultat voyage dans la session (« message flash »).
 */

require_once __DIR__ . '/lib/init.php';
require_once __DIR__ . '/lib/tableau-de-bord.php';
requireAdmin();

$user = currentUser();

$onglets = [
  'apercu'      => 'Vue d\'ensemble',
  'membres'     => 'Membres',
  'trajets'     => 'Trajets',
  'invitations' => 'Invitations',
  'sante'       => 'Santé du site',
];
$onglet = $_GET['onglet'] ?? 'apercu';
if (!isset($onglets[$onglet])) {
  $onglet = 'apercu';
}


// =============================================================
// Actions POST
// =============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $message = null;
  $messageType = 'success';

  if (!csrfVerify($_POST['csrf_token'] ?? null)) {
    $message = 'La page avait expiré. Recommencez l\'action.';
    $messageType = 'error';
  } else {
    $action = $_POST['action'] ?? '';
    $targetId = (int) ($_POST['id'] ?? 0);

    switch ($action) {
      case 'toggle_conducteur':
        if (adminToggleConducteur($targetId)) {
          $message = 'Rôle conducteur modifié.';
        }
        break;

      case 'toggle_admin':
        $r = adminToggleAdmin($targetId, $user['id']);
        $message = $r['ok'] ? 'Rôle admin modifié.' : $r['error'];
        $messageType = $r['ok'] ? 'success' : 'error';
        break;

      case 'delete_user':
        $r = adminDeleteUser($targetId, $user['id']);
        $message = $r['ok'] ? 'Membre supprimé, avec ses trajets et réservations.' : $r['error'];
        $messageType = $r['ok'] ? 'success' : 'error';
        break;

      case 'delete_trajet':
        if (adminDeleteTrajet($targetId)) {
          $message = 'Trajet supprimé.';
        }
        break;

      case 'creer_invitation':
        $r = creerInvitation($_POST['invite_nom'] ?? '', (int) ($_POST['max_utilisations'] ?? 3));
        if ($r['ok']) {
          $message = 'Code créé : ' . $r['code'] . '. Lien à envoyer : ' . lienSite('/inscription.php?code=' . $r['code']);
        } else {
          $message = $r['error'];
          $messageType = 'error';
        }
        break;

      case 'basculer_invitation':
        basculerInvitation($targetId);
        $message = 'Code activé / désactivé.';
        break;

      case 'basculer_signalement':
        basculerSignalement($targetId);
        $message = 'Signalement mis à jour.';
        break;

      default:
        $message = 'Action inconnue.';
        $messageType = 'error';
    }
  }

  if ($message !== null) {
    $_SESSION['admin_flash'] = ['texte' => $message, 'type' => $messageType];
  }
  header('Location: /admin.php?onglet=' . $onglet);
  exit;
}

$flash = $_SESSION['admin_flash'] ?? null;
unset($_SESSION['admin_flash']);


// =============================================================
// Données (on ne calcule que ce dont l'onglet a besoin)
// =============================================================
$stats        = getAdminStats();
$signalements = listerSignalements();
$nbSignalementsOuverts = count(array_filter($signalements, fn($s) => !$s['traite']));
$membres      = activiteMembres();
$nbActifs     = count(array_filter($membres, fn($m) => $m['statut'] === 'actif'));

if ($onglet === 'apercu') {
  $serieInscriptions = serieParJour('users');
  $serieTrajets      = serieParJour('trajets');
  $serieResas        = serieParJour('reservations');
  $entonnoir         = entonnoirTesteurs($membres);
  $nouveaux7j        = array_sum(array_slice($serieInscriptions, -7));
  $trajets7j         = array_sum(array_slice($serieTrajets, -7));
  $resas7j           = array_sum(array_slice($serieResas, -7));
}
if ($onglet === 'trajets') {
  $trajets      = listTrajetsAdmin();
  $reservations = listReservationsAdmin();
}
if ($onglet === 'invitations') {
  $invitations = invitationsAvecActivite(listerInvitations(), $membres);
}
if ($onglet === 'sante') {
  $emails    = santeEmails();
  $erreurs   = santeErreursPhp();
  $stockage  = santeStockage();
}

$libellesStatut = [
  'actif'   => ['● Actif', 'tdb-statut-actif', 'Passé sur le site ou a agi dans les 7 derniers jours'],
  'endormi' => ['◐ Endormi', 'tdb-statut-endormi', 'A déjà publié ou réservé, mais rien depuis plus de 7 jours'],
  'jamais'  => ['○ Pas encore lancé', 'tdb-statut-jamais', 'Inscrit, n\'a encore rien publié ni réservé'],
];


$pageTitle  = 'Tableau de bord — EcoRide';
$activePage = 'admin';
include __DIR__ . '/partials/header.php';
?>

<main class="container admin-page">

  <h1>Tableau de bord</h1>
  <p class="page-subtitle">La bêta en un coup d'œil : qui s'inscrit, qui revient, et si le site tient bon.</p>

  <?php if ($flash !== null): ?>
    <div class="form-banner form-banner-<?= htmlspecialchars($flash['type']) ?>" role="status">
      <?= htmlspecialchars($flash['texte']) ?>
    </div>
  <?php endif; ?>

  <nav class="tdb-onglets" aria-label="Rubriques du tableau de bord">
    <?php foreach ($onglets as $cle => $libelle): ?>
      <a href="/admin.php?onglet=<?= $cle ?>" class="tdb-onglet<?= $cle === $onglet ? ' is-actif' : '' ?>"<?= $cle === $onglet ? ' aria-current="page"' : '' ?>>
        <?= htmlspecialchars($libelle) ?>
        <?php if ($cle === 'membres'): ?><span class="tdb-pastille"><?= count($membres) ?></span><?php endif; ?>
        <?php if ($cle === 'sante' && $nbSignalementsOuverts > 0): ?><span class="tdb-pastille tdb-pastille-alerte" title="Signalements à traiter"><?= $nbSignalementsOuverts ?></span><?php endif; ?>
      </a>
    <?php endforeach; ?>
  </nav>


<?php if ($onglet === 'apercu'): ?>
  <!-- ================================================================
       VUE D'ENSEMBLE — croissance de la bêta
       ================================================================ -->
  <section class="tdb-chiffres" aria-label="Chiffres clés">
    <div class="tdb-chiffre">
      <span class="tdb-chiffre-label">Membres</span>
      <span class="tdb-chiffre-valeur"><?= $stats['nb_users'] ?></span>
      <span class="tdb-chiffre-extra"><?= $nouveaux7j > 0 ? '+' . $nouveaux7j . ' cette semaine' : 'aucun nouveau cette semaine' ?></span>
    </div>
    <div class="tdb-chiffre">
      <span class="tdb-chiffre-label">Actifs (7 jours)</span>
      <span class="tdb-chiffre-valeur"><?= $nbActifs ?></span>
      <span class="tdb-chiffre-extra">
        <?= $stats['nb_users'] > 0 ? round($nbActifs / $stats['nb_users'] * 100) . ' % des membres' : '—' ?>
      </span>
    </div>
    <div class="tdb-chiffre">
      <span class="tdb-chiffre-label">Trajets publiés</span>
      <span class="tdb-chiffre-valeur"><?= $stats['nb_trajets'] ?></span>
      <span class="tdb-chiffre-extra"><?= $trajets7j ?> cette semaine</span>
    </div>
    <div class="tdb-chiffre">
      <span class="tdb-chiffre-label">Réservations confirmées</span>
      <span class="tdb-chiffre-valeur"><?= $stats['nb_reservations'] ?></span>
      <span class="tdb-chiffre-extra"><?= $resas7j ?> demande<?= $resas7j > 1 ? 's' : '' ?> cette semaine</span>
    </div>
    <div class="tdb-chiffre">
      <span class="tdb-chiffre-label">CO₂ évité</span>
      <span class="tdb-chiffre-valeur"><?= number_format($stats['co2_total_economise'], 1, ',', ' ') ?> kg</span>
      <span class="tdb-chiffre-extra">grâce aux passagers</span>
    </div>
  </section>

  <div class="tdb-grille-graphs">
    <?php
      $graphs = [
        ['Inscriptions par jour', $serieInscriptions, 'inscription', 'Inscriptions'],
        ['Trajets publiés par jour', $serieTrajets, 'trajet', 'Trajets'],
        ['Demandes de réservation par jour', $serieResas, 'demande', 'Demandes'],
      ];
      foreach ($graphs as [$titre, $serie, $unite, $colonne]):
        $total = array_sum($serie);
    ?>
      <article class="tdb-carte">
        <header class="tdb-carte-tete">
          <h2><?= $titre ?></h2>
          <p><?= $total ?> sur les 30 derniers jours</p>
        </header>
        <?php if ($total === 0): ?>
          <p class="tdb-vide">Rien sur les 30 derniers jours. Le graphique se remplira dès les premiers <?= $unite ?>s.</p>
        <?php else: ?>
          <?= graphiqueBarres($serie, $unite, $titre . ', 30 derniers jours, total ' . $total) ?>
          <?= tableauSerie($serie, $colonne) ?>
        <?php endif; ?>
      </article>
    <?php endforeach; ?>

    <article class="tdb-carte">
      <header class="tdb-carte-tete">
        <h2>Parcours des testeurs</h2>
        <p>Combien ont franchi chaque étape</p>
      </header>
      <ol class="tdb-entonnoir">
        <?php $base = max(1, $entonnoir[0]['n']); foreach ($entonnoir as $etape): $pct = round($etape['n'] / $base * 100); ?>
          <li>
            <div class="tdb-entonnoir-ligne">
              <span><?= htmlspecialchars($etape['label']) ?></span>
              <strong><?= $etape['n'] ?> <small>(<?= $pct ?> %)</small></strong>
            </div>
            <div class="tdb-jauge" aria-hidden="true"><span style="width: <?= max($etape['n'] > 0 ? 2 : 0, $pct) ?>%"></span></div>
          </li>
        <?php endforeach; ?>
      </ol>
      <p class="admin-aide">Si beaucoup d'inscrits ne vont pas plus loin, une relance par message peut les aider à publier ou réserver leur premier trajet.</p>
    </article>
  </div>
<?php endif; ?>


<?php if ($onglet === 'membres'): ?>
  <!-- ================================================================
       MEMBRES — activité des testeurs + gestion
       ================================================================ -->
  <?php
    $parStatut = array_count_values(array_column($membres, 'statut'));
  ?>
  <ul class="tdb-resume-statuts">
    <?php foreach ($libellesStatut as $cle => [$libelle, $classe, $aide]): ?>
      <li><span class="tdb-statut <?= $classe ?>"><?= $libelle ?></span> <strong><?= $parStatut[$cle] ?? 0 ?></strong> <small><?= htmlspecialchars($aide) ?></small></li>
    <?php endforeach; ?>
  </ul>

  <section class="admin-section">
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Membre</th>
            <th>Statut</th>
            <th>Dernière activité</th>
            <th>Invité par le code de</th>
            <th>Inscrit le</th>
            <th>Activité</th>
            <th>Rôles</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($membres as $u): [$libelle, $classe] = $libellesStatut[$u['statut']]; ?>
            <tr <?= (int) $u['id'] === (int) $user['id'] ? 'class="row-self"' : '' ?>>
              <td>
                <strong><?= htmlspecialchars($u['nom']) ?></strong><br>
                <small><?= htmlspecialchars($u['email']) ?><?= empty($u['email_verifie']) ? ' · non vérifié' : '' ?></small>
              </td>
              <td><span class="tdb-statut <?= $classe ?>"><?= $libelle ?></span></td>
              <td><small title="<?= htmlspecialchars((string) $u['derniere_activite']) ?>"><?= ilYa($u['derniere_activite']) ?></small></td>
              <td><small><?= $u['invite_nom'] !== null ? htmlspecialchars($u['invite_nom']) : '—' ?></small></td>
              <td><small><?= htmlspecialchars(date('d/m/Y', strtotime($u['cree_le']))) ?></small></td>
              <td><small><?= $u['nb_trajets'] ?> trajet<?= $u['nb_trajets'] > 1 ? 's' : '' ?> · <?= $u['nb_reservations'] ?> résa</small></td>
              <td>
                <?php if ($u['est_admin']): ?><span class="role-tag role-tag-admin">Admin</span><?php endif; ?>
                <?php if ($u['est_conducteur']): ?>
                  <span class="role-tag role-tag-conducteur">Conducteur</span>
                <?php else: ?>
                  <span class="role-tag role-tag-passager">Passager</span>
                <?php endif; ?>
              </td>
              <td class="admin-actions">
                <form method="POST" action="" class="inline-form">
                  <?= csrfField() ?>
                  <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                  <button type="submit" name="action" value="toggle_conducteur" class="btn btn-ghost btn-sm">
                    <?= $u['est_conducteur'] ? 'Retirer conducteur' : 'Rendre conducteur' ?>
                  </button>
                </form>
                <?php if ((int) $u['id'] !== (int) $user['id']): ?>
                  <form method="POST" action="" class="inline-form">
                    <?= csrfField() ?>
                    <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                    <button type="submit" name="action" value="toggle_admin" class="btn btn-ghost btn-sm">
                      <?= $u['est_admin'] ? 'Retirer admin' : 'Rendre admin' ?>
                    </button>
                  </form>
                  <form method="POST" action="" class="inline-form" onsubmit="return confirm('Supprimer définitivement <?= htmlspecialchars(addslashes($u['nom'])) ?> ? Ses trajets et réservations partiront avec.');">
                    <?= csrfField() ?>
                    <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                    <button type="submit" name="action" value="delete_user" class="btn btn-danger btn-sm">Supprimer</button>
                  </form>
                <?php else: ?>
                  <span class="row-self-tag">toi</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
<?php endif; ?>


<?php if ($onglet === 'trajets'): ?>
  <!-- ================================================================
       TRAJETS ET RÉSERVATIONS
       ================================================================ -->
  <section class="admin-section">
    <h2>Trajets (<?= count($trajets) ?>)</h2>
    <?php if (empty($trajets)): ?>
      <p class="tdb-vide">Aucun trajet publié pour l'instant.</p>
    <?php else: ?>
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr><th>ID</th><th>Itinéraire</th><th>Date</th><th>Conducteur</th><th>Places</th><th>Prix</th><th>Résas</th><th>Action</th></tr>
        </thead>
        <tbody>
          <?php foreach ($trajets as $t): ?>
            <tr>
              <td><?= $t['id'] ?></td>
              <td><a href="/trajet.php?id=<?= (int) $t['id'] ?>"><strong><?= htmlspecialchars($t['depart']) ?> → <?= htmlspecialchars($t['arrivee']) ?></strong></a></td>
              <td><small><?= htmlspecialchars(date('d/m/Y H:i', strtotime($t['date'] . ' ' . $t['heure_depart']))) ?></small></td>
              <td>
                <?php if ($t['conducteur_nom_user']): ?>
                  <?= htmlspecialchars($t['conducteur_nom_user']) ?>
                <?php else: ?>
                  <em class="tdb-orphelin">sans conducteur</em>
                <?php endif; ?>
              </td>
              <td><?= $t['places_dispo'] ?></td>
              <td><?= formatPrix((float) $t['prix']) ?> €</td>
              <td><?= $t['nb_reservations'] ?></td>
              <td>
                <form method="POST" action="" class="inline-form" onsubmit="return confirm('Supprimer ce trajet et toutes ses réservations ?');">
                  <?= csrfField() ?>
                  <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                  <button type="submit" name="action" value="delete_trajet" class="btn btn-danger btn-sm">Supprimer</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </section>

  <section class="admin-section">
    <h2>Réservations récentes (<?= count($reservations) ?>)</h2>
    <?php if (empty($reservations)): ?>
      <p class="tdb-vide">Aucune réservation pour l'instant.</p>
    <?php else: ?>
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr><th>ID</th><th>Passager</th><th>Trajet</th><th>Date du trajet</th><th>Statut</th><th>Demandée le</th></tr>
        </thead>
        <tbody>
          <?php foreach ($reservations as $r): ?>
            <tr>
              <td><?= $r['id'] ?></td>
              <td><strong><?= htmlspecialchars($r['passager_nom']) ?></strong></td>
              <td><?= htmlspecialchars($r['depart']) ?> → <?= htmlspecialchars($r['arrivee']) ?></td>
              <td><small><?= htmlspecialchars(date('d/m/Y', strtotime($r['date']))) ?></small></td>
              <td><span class="role-tag role-tag-conducteur"><?= htmlspecialchars($r['statut']) ?></span></td>
              <td><small><?= htmlspecialchars(date('d/m/Y H:i', strtotime($r['cree_le']))) ?></small></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </section>
<?php endif; ?>


<?php if ($onglet === 'invitations'): ?>
  <!-- ================================================================
       INVITATIONS — qui a amené qui
       ================================================================ -->
  <section class="admin-section">
    <h2>Créer un code</h2>
    <p class="admin-aide">Un code par ami. Envoyez-lui le lien : son code sera déjà rempli à l'inscription. Désactivez un code s'il circule trop.</p>

    <form method="POST" action="" class="admin-form-invitation">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="creer_invitation">
      <div class="form-field">
        <label for="invite_nom">Pour qui ?</label>
        <input type="text" id="invite_nom" name="invite_nom" maxlength="60" required placeholder="Ex. : Karim (collègue)">
      </div>
      <div class="form-field">
        <label for="max_utilisations">Inscriptions possibles</label>
        <select id="max_utilisations" name="max_utilisations">
          <option value="1">1 (la personne seule)</option>
          <option value="3" selected>3 (elle + 2 proches)</option>
          <option value="5">5</option>
          <option value="10">10</option>
        </select>
      </div>
      <button type="submit" class="btn btn-primary">Créer le code</button>
    </form>
  </section>

  <section class="admin-section">
    <h2>Codes envoyés (<?= count($invitations) ?>)</h2>
    <?php if (empty($invitations)): ?>
      <p class="tdb-vide">Aucun code pour l'instant. Créez-en un ci-dessus pour inviter votre premier testeur.</p>
    <?php else: ?>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <tr><th>Code</th><th>Pour</th><th>Inscriptions</th><th>Actifs cette semaine</th><th>Inscrits avec ce code</th><th>Lien</th><th>État</th></tr>
          </thead>
          <tbody>
            <?php foreach ($invitations as $inv): $lien = lienSite('/inscription.php?code=' . $inv['code']); ?>
              <tr class="<?= $inv['actif'] ? '' : 'row-inactif' ?>">
                <td><code class="code-invitation"><?= htmlspecialchars($inv['code']) ?></code></td>
                <td><?= htmlspecialchars($inv['invite_nom']) ?></td>
                <td>
                  <?= (int) $inv['utilisations'] ?> / <?= (int) $inv['max_utilisations'] ?>
                  <div class="tdb-jauge tdb-jauge-mini" aria-hidden="true"><span style="width: <?= round(min(1, $inv['utilisations'] / max(1, $inv['max_utilisations'])) * 100) ?>%"></span></div>
                </td>
                <td><?= (int) $inv['nb_actifs'] ?></td>
                <td>
                  <?php if (empty($inv['inscrits'])): ?>
                    <small>personne pour l'instant</small>
                  <?php else: ?>
                    <small><?= htmlspecialchars(implode(', ', array_column($inv['inscrits'], 'nom'))) ?></small>
                  <?php endif; ?>
                </td>
                <td>
                  <button type="button" class="btn btn-ghost btn-sm btn-copier" data-copier="<?= htmlspecialchars($lien) ?>">Copier le lien</button>
                </td>
                <td>
                  <form method="POST" action="" class="inline-form">
                    <?= csrfField() ?>
                    <input type="hidden" name="id" value="<?= (int) $inv['id'] ?>">
                    <button type="submit" name="action" value="basculer_invitation" class="btn btn-ghost btn-sm">
                      <?= $inv['actif'] ? 'Actif · désactiver' : 'Désactivé · réactiver' ?>
                    </button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>
<?php endif; ?>


<?php if ($onglet === 'sante'): ?>
  <!-- ================================================================
       SANTÉ DU SITE
       ================================================================ -->
  <?php
    $etat = function (bool $ok, string $texteOk, string $texteKo): string {
      return $ok
        ? '<span class="tdb-statut tdb-statut-actif">✓ ' . $texteOk . '</span>'
        : '<span class="tdb-statut tdb-statut-alerte">! ' . $texteKo . '</span>';
    };
    $sauvegardeRecente = $stockage['derniere_sauvegarde'] !== null && $stockage['derniere_sauvegarde'] > strtotime('-8 days');
  ?>
  <section class="tdb-chiffres" aria-label="État du site">
    <div class="tdb-chiffre">
      <span class="tdb-chiffre-label">Signalements à traiter</span>
      <span class="tdb-chiffre-valeur"><?= $nbSignalementsOuverts ?></span>
      <?= $etat($nbSignalementsOuverts === 0, 'rien en attente', 'à lire ci-dessous') ?>
    </div>
    <div class="tdb-chiffre">
      <span class="tdb-chiffre-label">E-mails (30 jours)</span>
      <span class="tdb-chiffre-valeur"><?= $emails['envoyes'] ?></span>
      <?= $etat($emails['echecs'] === 0, 'aucun échec', $emails['echecs'] . ' échec' . ($emails['echecs'] > 1 ? 's' : '')) ?>
    </div>
    <div class="tdb-chiffre">
      <span class="tdb-chiffre-label">Erreurs PHP (7 jours)</span>
      <span class="tdb-chiffre-valeur"><?= $erreurs['sur_7_jours'] ?></span>
      <?= $etat($erreurs['sur_7_jours'] === 0, 'aucune', 'voir le détail') ?>
    </div>
    <div class="tdb-chiffre">
      <span class="tdb-chiffre-label">Dernière sauvegarde</span>
      <span class="tdb-chiffre-valeur tdb-chiffre-valeur-texte">
        <?= $stockage['derniere_sauvegarde'] ? ilYa(date('Y-m-d H:i:s', $stockage['derniere_sauvegarde'])) : '—' ?>
      </span>
      <?php if ($stockage['derniere_sauvegarde'] === null): ?>
        <span class="tdb-chiffre-extra">faite à chaque mise en ligne (pas visible en local)</span>
      <?php else: ?>
        <?= $etat($sauvegardeRecente, 'faite à la dernière mise en ligne', 'plus d\'une semaine') ?>
      <?php endif; ?>
    </div>
    <div class="tdb-chiffre">
      <span class="tdb-chiffre-label">Taille de la base</span>
      <span class="tdb-chiffre-valeur tdb-chiffre-valeur-texte"><?= tailleLisible($stockage['taille_base']) ?></span>
      <span class="tdb-chiffre-extra"><?= $stats['nb_messages'] ?> messages · <?= $stats['nb_avis'] ?> avis</span>
    </div>
  </section>

  <section class="admin-section" id="signalements">
    <h2>Signalements des testeurs</h2>
    <?php if (empty($signalements)): ?>
      <p class="admin-aide">Aucun signalement pour l'instant. Les testeurs peuvent en envoyer depuis le bouton « Signaler un problème » en bas de chaque page.</p>
    <?php else: ?>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead><tr><th>Date</th><th>De</th><th>Page</th><th>Message</th><th>État</th></tr></thead>
          <tbody>
            <?php foreach ($signalements as $sig): ?>
              <tr class="<?= $sig['traite'] ? 'row-inactif' : '' ?>">
                <td><small><?= htmlspecialchars(date('d/m H:i', strtotime($sig['cree_le']))) ?></small></td>
                <td><small><?= htmlspecialchars($sig['user_nom'] ?? 'visiteur') ?></small></td>
                <td><small><?= htmlspecialchars($sig['page']) ?></small></td>
                <td class="cellule-message"><?= nl2br(htmlspecialchars($sig['message'])) ?></td>
                <td>
                  <form method="POST" action="" class="inline-form">
                    <?= csrfField() ?>
                    <input type="hidden" name="id" value="<?= (int) $sig['id'] ?>">
                    <button type="submit" name="action" value="basculer_signalement" class="btn btn-ghost btn-sm">
                      <?= $sig['traite'] ? '✓ Traité' : 'Marquer traité' ?>
                    </button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

  <div class="tdb-grille-graphs">
    <article class="tdb-carte">
      <header class="tdb-carte-tete">
        <h2>E-mails en échec</h2>
        <p>Vérification d'adresse, nouvelles réservations…</p>
      </header>
      <?php if (empty($emails['derniers_echecs'])): ?>
        <p class="tdb-vide">Aucun e-mail en échec. Tout part bien.</p>
      <?php else: ?>
        <ul class="tdb-liste">
          <?php foreach ($emails['derniers_echecs'] as $e): ?>
            <li><small><?= htmlspecialchars(date('d/m H:i', strtotime($e['cree_le']))) ?></small> <?= htmlspecialchars($e['sujet']) ?> <small>(<?= htmlspecialchars((string) $e['methode']) ?>)</small></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <p><a href="/diagnostic-email.php" class="btn btn-ghost btn-sm">Tester l'envoi d'e-mails</a></p>
    </article>

    <article class="tdb-carte">
      <header class="tdb-carte-tete">
        <h2>Dernières erreurs PHP</h2>
        <p>Les plus récentes en premier</p>
      </header>
      <?php if (empty($erreurs['dernieres'])): ?>
        <p class="tdb-vide"><?= $erreurs['existe'] ? 'Aucune erreur récente.' : 'Aucune erreur enregistrée.' ?></p>
      <?php else: ?>
        <ul class="tdb-liste tdb-liste-code">
          <?php foreach ($erreurs['dernieres'] as $err): ?>
            <li><small><?= $err['quand'] ? htmlspecialchars(date('d/m H:i', $err['quand'])) : '' ?></small> <?= htmlspecialchars($err['texte']) ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <p><a href="/debug.php" class="btn btn-ghost btn-sm">Page de debug complète</a></p>
    </article>
  </div>
<?php endif; ?>

</main>

<script src="<?= asset('/assets/js/admin.js') ?>"></script>
<?php include __DIR__ . '/partials/footer.php'; ?>
