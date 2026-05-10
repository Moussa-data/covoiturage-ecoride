<?php
/**
 * lib/csrf.php — Protection CSRF (Cross-Site Request Forgery)
 *
 * QU'EST-CE QUE LA CSRF ?
 *
 *   Imagine : tu es connecté à EcoRide dans un onglet. Dans un autre onglet,
 *   un site malveillant a une page qui contient :
 *
 *     <img src="https://ecoride.test/supprimer-trajet.php?id=42">
 *
 *   Le navigateur charge cette image en envoyant TES cookies de session
 *   d'EcoRide. Résultat : ton trajet 42 est supprimé sans que tu cliques.
 *
 *   La défense : on génère un TOKEN secret à chaque session, on l'inclut
 *   en champ caché dans nos formulaires, et on vérifie qu'il est présent
 *   et correct sur chaque action sensible. L'attaquant ne le connaît pas,
 *   donc sa fausse requête échoue.
 *
 * RÈGLE D'OR :
 *   - Toute action qui MODIFIE des données (POST, DELETE, PUT) doit
 *     vérifier le token CSRF.
 *   - Les actions qui LISENT seulement (GET sans effet de bord) n'en
 *     ont pas besoin.
 */


// ============================================================
// 🆕 Refacto progressive : façades qui délèguent à \EcoRide\Csrf
// ============================================================

function csrfToken(): string {
  return \EcoRide\Csrf::token();
}

function csrfVerify(?string $token): bool {
  return \EcoRide\Csrf::verify($token);
}

function csrfField(): string {
  return \EcoRide\Csrf::field();
}
