<?php
/**
 * reserver.php — Endpoint POST pour créer une réservation
 *
 * Cet endpoint ne fait jamais de rendu HTML : il fait l'action
 * et redirige vers la page de détail (pattern POST/Redirect/GET).
 *
 * Sécurité :
 *  - Doit être appelé en POST
 *  - Token CSRF obligatoire
 *  - L'utilisateur doit être connecté
 *  - addReservation() vérifie le reste (places dispo, propre trajet, doublon)
 */

require_once __DIR__ . '/lib/init.php';
requireLogin();

// Refuse tout sauf POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405); // 405 Method Not Allowed
  exit('Méthode non autorisée.');
}

// Vérification CSRF
if (!csrfVerify($_POST['csrf_token'] ?? null)) {
  http_response_code(403);
  exit('Token CSRF invalide.');
}

$user     = currentUser();
$trajetId = (int) ($_POST['trajet_id'] ?? 0);

$resultat = addReservation($user['id'], $trajetId);

// Redirection vers la page de détail avec un flag de statut.
// Le ?reservation= sera lu par trajet.php pour afficher la bonne bannière.
if ($resultat['ok']) {
  header('Location: /trajet.php?id=' . $trajetId . '&reservation=ok');
} else {
  // On stocke l'erreur en session pour la lire de l'autre côté
  $_SESSION['flash_error'] = $resultat['error'];
  header('Location: /trajet.php?id=' . $trajetId . '&reservation=ko');
}
exit;
