<?php
/**
 * src/Auth.php — Gestion de l'authentification et des sessions
 */

namespace EcoRide;

use EcoRide\Models\User;

/**
 * Classe Auth — encapsule tout ce qui concerne sessions + login.
 *
 * Toutes les méthodes sont statiques car l'authentification est conceptuellement
 * un service global (un seul user connecté à la fois par session HTTP).
 */
class Auth
{
  /** @var User|null Cache pour éviter de re-fetch à chaque appel */
  private static ?User $cachedUser = null;
  private static bool $cachedUserChecked = false;

  /**
   * Tente de connecter un user. Retourne true si OK, false sinon.
   */
  public static function login(string $email, string $motDePasse): bool
  {
    $user = User::findByEmail($email);
    if ($user === null || !$user->verifyPassword($motDePasse)) {
      return false;
    }

    // Régénération de l'ID de session : sécurité contre la session fixation
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user->id;
    self::$cachedUser = $user;
    self::$cachedUserChecked = true;
    return true;
  }

  /**
   * Déconnecte le user et détruit la session.
   */
  public static function logout(): void
  {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
      $params = session_get_cookie_params();
      setcookie(
        session_name(), '',
        time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
      );
    }
    session_destroy();
    self::$cachedUser = null;
    self::$cachedUserChecked = true;
  }

  /**
   * Retourne le User connecté (ou null).
   * Utilise un cache static pour éviter les fetch répétés dans la même requête.
   */
  public static function user(): ?User
  {
    if (self::$cachedUserChecked) {
      return self::$cachedUser;
    }
    self::$cachedUserChecked = true;
    if (!isset($_SESSION['user_id'])) {
      return self::$cachedUser = null;
    }
    $user = User::find((int) $_SESSION['user_id']);
    if ($user === null) {
      self::logout();
      return self::$cachedUser = null;
    }
    return self::$cachedUser = $user;
  }

  public static function check(): bool
  {
    return self::user() !== null;
  }

  public static function isConducteur(): bool
  {
    $u = self::user();
    return $u !== null && $u->estConducteur;
  }

  public static function isAdmin(): bool
  {
    $u = self::user();
    return $u !== null && $u->estAdmin;
  }

  /**
   * Redirige vers /connexion.php si pas connecté.
   * Garde l'URL d'origine pour rediriger après login.
   */
  public static function requireLogin(): void
  {
    if (!self::check()) {
      $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '/';
      header('Location: /connexion.php');
      exit;
    }
  }

  /**
   * Redirige vers /devenir-conducteur.php si pas conducteur.
   */
  public static function requireConducteur(): void
  {
    self::requireLogin();
    if (!self::isConducteur()) {
      header('Location: /devenir-conducteur.php');
      exit;
    }
  }

  /**
   * Renvoie 403 si pas admin.
   */
  public static function requireAdmin(): void
  {
    self::requireLogin();
    if (!self::isAdmin()) {
      http_response_code(403);
      $pageTitle = 'Accès refusé — EcoRide';
      include __DIR__ . '/../partials/header.php';
      echo '<main class="container detail-page"><div class="empty empty-large"><h1>🚫 Accès refusé</h1><p>Cette page est réservée aux administrateurs.</p><a href="/" class="btn btn-primary">Retour à l\'accueil</a></div></main>';
      include __DIR__ . '/../partials/footer.php';
      exit;
    }
  }

  /**
   * Force le re-fetch du user (utile après une mutation type updateProfile).
   */
  public static function clearCache(): void
  {
    self::$cachedUser = null;
    self::$cachedUserChecked = false;
  }
}
