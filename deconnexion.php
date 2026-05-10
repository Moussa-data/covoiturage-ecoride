<?php
/**
 * deconnexion.php — Détruit la session et redirige vers l'accueil
 *
 * Maintenant en POST avec vérification CSRF (sécurité).
 * Pourquoi POST ? Pour empêcher qu'un attaquant t'ouvre un onglet
 * pointant ici via un lien : tes amis pourraient te déconnecter
 * à coups de <a href="/deconnexion.php"> dans des forums ou emails.
 */
require_once __DIR__ . '/lib/init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  // Si on arrive ici en GET (ancien lien, refresh accidentel...)
  // on redirige juste vers l'accueil sans rien faire.
  header('Location: /');
  exit;
}

if (!csrfVerify($_POST['csrf_token'] ?? null)) {
  http_response_code(403);
  exit('Token CSRF invalide.');
}

logoutUser();

header('Location: /');
exit;
