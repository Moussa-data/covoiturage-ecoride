<?php
/**
 * src/Database.php — Connexion BDD encapsulée en classe (Singleton)
 */

namespace EcoRide;

use PDO;

/**
 * Classe Database — wrapper autour de PDO en pattern Singleton.
 *
 * NOUVELLES NOTIONS :
 *
 * 1. namespace
 *    Permet d'éviter les collisions de noms entre classes.
 *    EcoRide\Database est différent de Vendor\Database.
 *
 * 2. Singleton (design pattern)
 *    On veut UNE SEULE instance de connexion par requête HTTP.
 *    Le constructeur est privé → impossible de faire `new Database()` partout.
 *    On utilise toujours `Database::getInstance()`.
 *
 * 3. Méthodes statiques
 *    Pas besoin d'instance pour les appeler : Database::query("...")
 */
class Database
{
  /** @var \PDO|null Instance unique de la connexion */
  private static ?PDO $pdo = null;

  /**
   * Constructeur privé : empêche `new Database()` ailleurs.
   * (ce qui forcerait à passer par getInstance())
   */
  private function __construct() {}

  /**
   * Empêche le clonage de l'instance (sécurité Singleton).
   */
  private function __clone() {}

  /**
   * Retourne l'instance PDO unique. La crée au premier appel.
   *
   * On garde la même implémentation qu'avant (lib/db.php) mais on l'encapsule
   * dans une classe pour respecter le principe de responsabilité unique.
   */
  public static function getInstance(): PDO
  {
    if (self::$pdo !== null) {
      return self::$pdo;
    }

    // On délègue à la fonction db() existante pour ne pas dupliquer
    // l'init du schéma + les migrations. C'est le principe de la
    // refacto progressive : on enveloppe l'ancien code dans la nouvelle API.
    self::$pdo = \db();
    return self::$pdo;
  }

  /**
   * Helper pratique : exécute une requête préparée et retourne le statement.
   *
   * Usage :
   *   $stmt = Database::query("SELECT * FROM users WHERE id = :id", ['id' => 1]);
   *   $user = $stmt->fetch();
   *
   * Évite la verbosité $pdo->prepare(...)->execute(...) dans tout le code.
   */
  public static function query(string $sql, array $params = []): \PDOStatement
  {
    $stmt = self::getInstance()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
  }

  /**
   * Helper : récupère une seule ligne (ou null).
   */
  public static function one(string $sql, array $params = []): ?array
  {
    $row = self::query($sql, $params)->fetch();
    return $row ?: null;
  }

  /**
   * Helper : récupère toutes les lignes.
   */
  public static function all(string $sql, array $params = []): array
  {
    return self::query($sql, $params)->fetchAll();
  }

  /**
   * Helper : récupère une seule valeur (premier champ de la première ligne).
   * Utile pour les COUNT, SUM, etc.
   */
  public static function value(string $sql, array $params = []): mixed
  {
    return self::query($sql, $params)->fetchColumn();
  }
}
