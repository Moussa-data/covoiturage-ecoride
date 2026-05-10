<?php
/**
 * api/trajets.php — Mini-API qui renvoie tous les trajets en JSON
 *
 * Avant : le JS faisait fetch('/data/trajets.json')
 * Maintenant : le JS fait fetch('/api/trajets.php')
 *               et c'est PHP qui interroge SQLite et renvoie du JSON.
 *
 * C'est ta première "API" : un endpoint qui retourne des données
 * dans un format que JS peut consommer. C'est exactement ça que font
 * les vraies API REST modernes (en plus complexe).
 */

require_once __DIR__ . '/../lib/init.php';

// On dit au navigateur que la réponse est du JSON (et pas du HTML)
header('Content-Type: application/json; charset=utf-8');

$trajets = loadTrajets();

// json_encode avec UNESCAPED pour garder les accents et les / lisibles
echo json_encode($trajets, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
