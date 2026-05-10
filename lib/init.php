<?php
/**
 * lib/init.php — Initialisation centralisée
 *
 * À inclure tout en haut de chaque page PHP :
 *   require_once __DIR__ . '/lib/init.php';
 *
 * Pourquoi ce fichier ?
 *  - session_start() doit être appelée AVANT toute sortie HTML
 *  - on charge les libs partagées une seule fois (require_once)
 *  - on configure dev vs prod (display_errors, etc.)
 */


// =============================================================
// Détection environnement : DEV (local) vs PROD (en ligne)
// =============================================================
// On regarde le nom d'hôte. En local, c'est "ecoride.test" (Herd).
// En prod, c'est "*.alwaysdata.net" ou un vrai domaine.
$host = $_SERVER['HTTP_HOST'] ?? '';
$isProd = !str_ends_with($host, '.test')
       && !str_starts_with($host, 'localhost')
       && !str_starts_with($host, '127.0.0.1');
define('IS_PROD', $isProd);


// =============================================================
// Configuration des erreurs PHP
// =============================================================
if (IS_PROD) {
  // En PROD : on cache les erreurs aux visiteurs (sécurité)
  // mais on les LOGGE dans un fichier pour pouvoir debugger.
  ini_set('display_errors', '0');
  ini_set('log_errors', '1');
  ini_set('error_log', __DIR__ . '/../data/php-errors.log');
  error_reporting(E_ALL);
} else {
  // En DEV : on AFFICHE toutes les erreurs (visibles tout de suite)
  ini_set('display_errors', '1');
  error_reporting(E_ALL);
}


// =============================================================
// Cookie de session sécurisé en prod
// =============================================================
// secure=true → cookie envoyé UNIQUEMENT en HTTPS
// httponly=true → cookie inaccessible en JS (anti-XSS)
// samesite=Lax → protection contre certaines attaques CSRF
if (session_status() === PHP_SESSION_NONE) {
  session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => '',
    'secure'   => IS_PROD,    // forcer HTTPS uniquement en prod
    'httponly' => true,
    'samesite' => 'Lax',
  ]);
  session_start();
}

// =============================================================
// Autoloader PSR-4 simple pour les classes du namespace EcoRide\
// =============================================================
// Quand on écrit `new EcoRide\Models\User(...)`, PHP cherche le fichier
// correspondant. Le standard PSR-4 dit : remplacer "\" par "/" et ajouter ".php".
// Donc EcoRide\Models\User → src/Models/User.php
//
// On évite Composer pour ne pas avoir de dépendance externe — un autoloader
// maison de 8 lignes suffit pour notre cas. Quand le projet grossira on
// passera à Composer (la commande "composer init" prend 2 minutes).
spl_autoload_register(function (string $class) {
  $prefix = 'EcoRide\\';
  if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
    return; // pas notre namespace, on laisse les autres autoloaders gérer
  }
  $relative = substr($class, strlen($prefix));
  $file = __DIR__ . '/../src/' . str_replace('\\', '/', $relative) . '.php';
  if (file_exists($file)) {
    require $file;
  }
});


// Charge nos libs
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/trajets.php';
require_once __DIR__ . '/reservations.php';
require_once __DIR__ . '/avis.php';
require_once __DIR__ . '/messages.php';
require_once __DIR__ . '/admin.php';
require_once __DIR__ . '/csrf.php';
