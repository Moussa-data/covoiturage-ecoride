<?php
/**
 * lib/tableau-de-bord.php — Chiffres et graphiques du tableau de bord admin
 *
 * NOTIONS CLÉS :
 *
 * 1. SÉRIE PAR JOUR : on compte les lignes créées chaque jour
 *    (GROUP BY substr(cree_le, 1, 10) → "2026-11-24"), puis on remplit
 *    les jours sans rien avec 0. Sans ça, le graphique sauterait des jours.
 *
 * 2. GRAPHIQUE EN SVG CÔTÉ SERVEUR : le site interdit les scripts externes
 *    (CSP), donc pas de bibliothèque de graphiques. On dessine nous-mêmes
 *    des rectangles <rect> en SVG. Chaque barre a un <title> : le navigateur
 *    l'affiche en infobulle au survol, sans une ligne de JavaScript.
 *
 * 3. « ACTIF » = le membre est passé sur le site (derniere_visite),
 *    ou a publié / réservé / écrit, dans les 7 derniers jours.
 */

require_once __DIR__ . '/db.php';

const TDB_JOURS = 30;


// =============================================================
// 1. Séries par jour (30 derniers jours)
// =============================================================

/** Liste des 30 derniers jours, du plus ancien à aujourd'hui : ['2026-10-26' => 0, …]. */
function joursVides(int $nb = TDB_JOURS): array
{
  $jours = [];
  for ($i = $nb - 1; $i >= 0; $i--) {
    $jours[date('Y-m-d', strtotime("-$i days"))] = 0;
  }
  return $jours;
}

/** Nombre de lignes créées par jour dans une table (nom de table fixé dans le code, jamais par l'utilisateur). */
function serieParJour(string $table, int $nb = TDB_JOURS): array
{
  $tablesAutorisees = ['users', 'trajets', 'reservations', 'messages'];
  if (!in_array($table, $tablesAutorisees, true)) {
    throw new InvalidArgumentException('Table non prévue : ' . $table);
  }
  $serie = joursVides($nb);
  $depuis = array_key_first($serie);
  $stmt = db()->prepare("
    SELECT substr(cree_le, 1, 10) AS jour, COUNT(*) AS n
    FROM $table
    WHERE substr(cree_le, 1, 10) >= :depuis
    GROUP BY jour
  ");
  $stmt->execute(['depuis' => $depuis]);
  foreach ($stmt->fetchAll() as $ligne) {
    if (isset($serie[$ligne['jour']])) {
      $serie[$ligne['jour']] = (int) $ligne['n'];
    }
  }
  return $serie;
}

/** Nombre de lignes créées dans les N derniers jours. */
function compterDepuis(string $table, int $jours): int
{
  return array_sum(array_slice(serieParJour($table), -$jours));
}


// =============================================================
// 2. Activité des membres
// =============================================================

/**
 * Chaque membre avec sa dernière activité et son statut :
 *   'actif'    → activité dans les 7 derniers jours
 *   'endormi'  → déjà venu, mais rien depuis plus de 7 jours
 *   'jamais'   → inscrit, n'a encore rien publié ni réservé
 */
function activiteMembres(): array
{
  // derniere_visite peut manquer si la migration n'a pas encore tourné
  $membres = db()->query("
    SELECT
      u.id, u.nom, u.email, u.cree_le, u.est_conducteur, u.est_admin,
      u.email_verifie, u.derniere_visite,
      i.invite_nom,
      (SELECT COUNT(*) FROM trajets      WHERE user_id = u.id) AS nb_trajets,
      (SELECT COUNT(*) FROM reservations WHERE user_id = u.id) AS nb_reservations,
      (SELECT MAX(cree_le) FROM trajets      WHERE user_id = u.id) AS dernier_trajet,
      (SELECT MAX(cree_le) FROM reservations WHERE user_id = u.id) AS derniere_resa
    FROM users u
    LEFT JOIN invitations i ON i.id = u.invitation_id
    ORDER BY u.id ASC
  ")->fetchAll();

  $limite = date('Y-m-d H:i:s', strtotime('-7 days'));
  foreach ($membres as &$m) {
    // La plus récente des dates connues (format ISO : la comparaison de textes suffit)
    $dates = array_filter([$m['derniere_visite'], $m['dernier_trajet'], $m['derniere_resa'], $m['cree_le']]);
    $derniere = $dates ? max(array_map(fn($d) => str_replace('T', ' ', substr($d, 0, 19)), $dates)) : null;
    $m['derniere_activite'] = $derniere;
    $aAgi = ($m['nb_trajets'] + $m['nb_reservations']) > 0;

    if ($derniere !== null && $derniere >= $limite && ($aAgi || $m['derniere_visite'])) {
      $m['statut'] = 'actif';
    } elseif (!$aAgi) {
      $m['statut'] = 'jamais';
    } else {
      $m['statut'] = 'endormi';
    }
  }
  unset($m);
  return $membres;
}

/** Les étapes du parcours d'un testeur : inscrit → e-mail vérifié → a publié ou réservé. */
function entonnoirTesteurs(array $membres): array
{
  $total = count($membres);
  return [
    ['label' => 'Inscrits',                 'n' => $total],
    ['label' => 'E-mail vérifié',           'n' => count(array_filter($membres, fn($m) => !empty($m['email_verifie'])))],
    ['label' => 'Ont publié ou réservé',    'n' => count(array_filter($membres, fn($m) => ($m['nb_trajets'] + $m['nb_reservations']) > 0))],
  ];
}


// =============================================================
// 3. Invitations : qui a amené qui
// =============================================================

/** Ajoute à chaque code le nombre d'inscrits encore actifs. */
function invitationsAvecActivite(array $invitations, array $membres): array
{
  $actifsParCode = [];
  foreach ($membres as $m) {
    if ($m['statut'] === 'actif' && $m['invite_nom'] !== null) {
      $actifsParCode[$m['invite_nom']] = ($actifsParCode[$m['invite_nom']] ?? 0) + 1;
    }
  }
  foreach ($invitations as &$inv) {
    $inv['nb_actifs'] = $actifsParCode[$inv['invite_nom']] ?? 0;
  }
  unset($inv);
  return $invitations;
}


// =============================================================
// 4. Santé du site
// =============================================================

/** E-mails des 30 derniers jours : envoyés, échoués, et les derniers échecs. */
function santeEmails(): array
{
  $depuis = date('Y-m-d', strtotime('-' . (TDB_JOURS - 1) . ' days'));
  $stmt = db()->prepare("SELECT ok, COUNT(*) AS n FROM emails_log WHERE cree_le >= :d GROUP BY ok");
  $stmt->execute(['d' => $depuis]);
  $envoyes = 0; $echecs = 0;
  foreach ($stmt->fetchAll() as $l) {
    if ((int) $l['ok'] === 1) { $envoyes = (int) $l['n']; } else { $echecs = (int) $l['n']; }
  }
  $derniersEchecs = db()->query("SELECT sujet, methode, cree_le FROM emails_log WHERE ok = 0 ORDER BY id DESC LIMIT 5")->fetchAll();
  return ['envoyes' => $envoyes, 'echecs' => $echecs, 'derniers_echecs' => $derniersEchecs];
}

/**
 * Lit la fin du fichier d'erreurs PHP (data/php-errors.log).
 * Une ligne ressemble à : [24-Nov-2026 10:12:03 Europe/Paris] PHP Warning: …
 * On ne lit que les 200 derniers Ko pour rester rapide même si le fichier grossit.
 */
function santeErreursPhp(): array
{
  $fichier = __DIR__ . '/../data/php-errors.log';
  $resultat = ['existe' => is_file($fichier), 'sur_7_jours' => 0, 'dernieres' => [], 'taille' => 0];
  if (!$resultat['existe']) {
    return $resultat;
  }
  $resultat['taille'] = filesize($fichier);
  $f = fopen($fichier, 'rb');
  if ($f === false) {
    return $resultat;
  }
  $debut = max(0, $resultat['taille'] - 200 * 1024);
  fseek($f, $debut);
  $contenu = stream_get_contents($f);
  fclose($f);

  $limite = strtotime('-7 days');
  $lignes = [];
  foreach (preg_split('/\R/', (string) $contenu) as $ligne) {
    if (!preg_match('/^\[([^\]]+)\]\s+(.*)$/', $ligne, $m)) {
      continue; // suite d'une erreur sur plusieurs lignes (pile d'appels)
    }
    $quand = strtotime(preg_replace('/\s+[A-Za-z_\/]+$/', '', $m[1]));
    if ($quand !== false && $quand >= $limite) {
      $resultat['sur_7_jours']++;
    }
    $lignes[] = ['quand' => $quand ?: null, 'texte' => mb_substr($m[2], 0, 220)];
  }
  $resultat['dernieres'] = array_reverse(array_slice($lignes, -6));
  return $resultat;
}

/** Taille de la base et date de la dernière sauvegarde automatique (faite par deploy.sh). */
function santeStockage(): array
{
  $base = __DIR__ . '/../data/ecoride.sqlite';
  // Le dossier des sauvegardes est à côté de www/, hors du site public
  $dossierSauvegardes = dirname(__DIR__, 2) . '/sauvegardes';
  $sauvegardes = [];
  if (is_dir($dossierSauvegardes)) {
    foreach (glob($dossierSauvegardes . '/*.tar.gz') ?: [] as $archive) {
      $sauvegardes[] = ['nom' => basename($archive), 'date' => filemtime($archive), 'taille' => filesize($archive)];
    }
  }
  usort($sauvegardes, fn($a, $b) => $b['date'] <=> $a['date']);
  return [
    'taille_base'        => is_file($base) ? filesize($base) : 0,
    'derniere_sauvegarde'=> $sauvegardes[0]['date'] ?? null,
    'sauvegardes'        => $sauvegardes,
  ];
}


// =============================================================
// 5. Mise en forme
// =============================================================

/** « il y a 3 h », « hier », « il y a 12 j » */
function ilYa(?string $date): string
{
  if ($date === null || $date === '') {
    return '—';
  }
  $ecart = time() - strtotime($date);
  if ($ecart < 3600)      { return 'il y a ' . max(1, intdiv($ecart, 60)) . ' min'; }
  if ($ecart < 86400)     { return 'il y a ' . intdiv($ecart, 3600) . ' h'; }
  if ($ecart < 2 * 86400) { return 'hier'; }
  return 'il y a ' . intdiv($ecart, 86400) . ' j';
}

function tailleLisible(int $octets): string
{
  if ($octets < 1024)        { return $octets . ' o'; }
  if ($octets < 1024 * 1024) { return number_format($octets / 1024, 0, ',', ' ') . ' Ko'; }
  return number_format($octets / 1024 / 1024, 1, ',', ' ') . ' Mo';
}

/** « 24 nov. » à partir de « 2026-11-24 » */
function jourCourt(string $jour): string
{
  static $mois = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
  $t = strtotime($jour);
  return (int) date('j', $t) . ' ' . $mois[(int) date('n', $t) - 1];
}


// =============================================================
// 6. Graphique en barres (SVG)
// =============================================================

/**
 * Dessine 30 barres, une par jour. Une seule série → une seule couleur,
 * le titre de la carte dit ce que l'on compte.
 *
 * @param array  $serie  ['2026-11-24' => 3, …]
 * @param string $unite  mot au singulier, ex. « inscription » (pour l'infobulle)
 */
function graphiqueBarres(array $serie, string $unite, string $titreAccessible): string
{
  $largeur = 600; $hauteur = 170;
  $margeG = 28; $margeB = 24; $margeH = 10;
  $zoneL = $largeur - $margeG - 4;
  $zoneH = $hauteur - $margeB - $margeH;

  $max = max(1, ...array_values($serie));
  // Graduation ronde : 1, 2, 5, 10, 20…
  $pas = 1;
  foreach ([1, 2, 5, 10, 20, 50, 100, 200, 500] as $p) {
    $pas = $p;
    if ($max / $p <= 4) { break; }
  }
  $haut = (int) (ceil($max / $pas) * $pas);

  $n = count($serie);
  $cran = $zoneL / $n;
  $epaisseur = max(4, $cran - 4); // 4 px de vide entre deux barres

  $svg = '<svg class="tdb-graph" viewBox="0 0 ' . $largeur . ' ' . $hauteur . '" role="img" aria-label="' . htmlspecialchars($titreAccessible) . '">';

  // Lignes de repère horizontales, discrètes
  for ($v = 0; $v <= $haut; $v += $pas) {
    $y = $margeH + $zoneH - ($v / $haut) * $zoneH;
    $svg .= '<line class="tdb-grille" x1="' . $margeG . '" x2="' . ($largeur - 4) . '" y1="' . round($y, 1) . '" y2="' . round($y, 1) . '"/>';
    $svg .= '<text class="tdb-axe" x="' . ($margeG - 6) . '" y="' . round($y + 4, 1) . '" text-anchor="end">' . $v . '</text>';
  }

  $i = 0;
  foreach ($serie as $jour => $valeur) {
    $x = $margeG + $i * $cran + ($cran - $epaisseur) / 2;
    $h = ($valeur / $haut) * $zoneH;
    $yBas = $margeH + $zoneH;
    $libelle = jourCourt($jour) . ' : ' . $valeur . ' ' . $unite . ($valeur > 1 ? 's' : '');

    $svg .= '<g class="tdb-barre">';
    // Zone de survol plus large que la barre, sur toute la hauteur
    $svg .= '<rect class="tdb-cible" x="' . round($margeG + $i * $cran, 1) . '" y="' . $margeH . '" width="' . round($cran, 1) . '" height="' . $zoneH . '"><title>' . htmlspecialchars($libelle) . '</title></rect>';
    if ($valeur > 0) {
      // Barre au bout arrondi (4 px) et pied droit, posée sur la ligne de base
      $r = min(4, $epaisseur / 2, $h);
      $svg .= '<path class="tdb-rect" d="M' . round($x, 1) . ',' . $yBas
        . ' V' . round($yBas - $h + $r, 1)
        . ' Q' . round($x, 1) . ',' . round($yBas - $h, 1) . ' ' . round($x + $r, 1) . ',' . round($yBas - $h, 1)
        . ' H' . round($x + $epaisseur - $r, 1)
        . ' Q' . round($x + $epaisseur, 1) . ',' . round($yBas - $h, 1) . ' ' . round($x + $epaisseur, 1) . ',' . round($yBas - $h + $r, 1)
        . ' V' . $yBas . ' Z"><title>' . htmlspecialchars($libelle) . '</title></path>';
    }
    $svg .= '</g>';

    // Une date tous les 7 jours, en partant d'aujourd'hui vers le passé
    if (($n - 1 - $i) % 7 === 0) {
      $svg .= '<text class="tdb-axe" x="' . round($x + $epaisseur / 2, 1) . '" y="' . ($hauteur - 6) . '" text-anchor="middle">' . ($i === $n - 1 ? 'auj.' : htmlspecialchars(jourCourt($jour))) . '</text>';
    }
    $i++;
  }

  $svg .= '<line class="tdb-base" x1="' . $margeG . '" x2="' . ($largeur - 4) . '" y1="' . ($margeH + $zoneH) . '" y2="' . ($margeH + $zoneH) . '"/>';
  $svg .= '</svg>';
  return $svg;
}

/** Le même chiffre en tableau, pour qui préfère lire (ou utilise un lecteur d'écran). */
function tableauSerie(array $serie, string $colonne): string
{
  $html = '<details class="tdb-details"><summary>Voir les chiffres jour par jour</summary><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Jour</th><th>' . htmlspecialchars($colonne) . '</th></tr></thead><tbody>';
  foreach (array_reverse($serie, true) as $jour => $v) {
    $html .= '<tr><td>' . htmlspecialchars(jourCourt($jour)) . '</td><td>' . (int) $v . '</td></tr>';
  }
  return $html . '</tbody></table></div></details>';
}
