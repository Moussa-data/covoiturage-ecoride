<?php
/**
 * src/Csrf.php — Protection CSRF en classe
 */

namespace EcoRide;

class Csrf
{
  /**
   * Retourne le token CSRF de la session, en le générant si besoin.
   */
  public static function token(): string
  {
    if (empty($_SESSION['csrf_token'])) {
      $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
  }

  /**
   * Vérifie qu'un token reçu correspond à celui de la session.
   * Utilise hash_equals() (anti-timing attack).
   */
  public static function verify(?string $token): bool
  {
    if ($token === null || empty($_SESSION['csrf_token'])) {
      return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
  }

  /**
   * Génère le HTML d'un input caché pour les formulaires POST.
   */
  public static function field(): string
  {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(self::token()) . '">';
  }
}
