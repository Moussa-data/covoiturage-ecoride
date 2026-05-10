<?php
/**
 * src/Models/User.php — Modèle utilisateur en POO
 */

namespace EcoRide\Models;

use EcoRide\Database;

/**
 * Classe User — représente un utilisateur du site.
 *
 * Pattern : Active Record léger.
 *  - Les propriétés correspondent aux colonnes de la table users
 *  - Les méthodes statiques (find, findByEmail) construisent un User depuis la BDD
 *  - Les méthodes d'instance (isAdmin, save) opèrent sur un user
 *
 * On garde la POO simple :
 *  - public readonly pour l'id (immuable une fois créé)
 *  - public pour le reste (pas de getters/setters partout, c'est PHP, pas Java)
 */
class User
{
  public int $id;
  public string $nom;
  public string $email;
  public string $motDePasseHash;
  public string $creeLe;
  public bool $estConducteur;
  public bool $estAdmin;

  /**
   * Constructeur — prend un tableau associatif (issu de la BDD).
   * On utilise des valeurs par défaut pour la robustesse.
   */
  public function __construct(array $data)
  {
    $this->id              = (int) ($data['id'] ?? 0);
    $this->nom             = $data['nom'] ?? '';
    $this->email           = $data['email'] ?? '';
    $this->motDePasseHash  = $data['mot_de_passe'] ?? '';
    $this->creeLe          = $data['cree_le'] ?? '';
    $this->estConducteur   = (bool) ($data['est_conducteur'] ?? false);
    $this->estAdmin        = (bool) ($data['est_admin'] ?? false);
  }

  // =============================================================
  // Méthodes statiques — finders
  // =============================================================

  /**
   * Trouve un user par ID.
   */
  public static function find(int $id): ?self
  {
    $row = Database::one(
      "SELECT * FROM users WHERE id = :id LIMIT 1",
      ['id' => $id]
    );
    return $row ? new self($row) : null;
  }

  /**
   * Trouve un user par email (insensible à la casse).
   */
  public static function findByEmail(string $email): ?self
  {
    $row = Database::one(
      "SELECT * FROM users WHERE email = :email LIMIT 1",
      ['email' => strtolower(trim($email))]
    );
    return $row ? new self($row) : null;
  }

  /**
   * Liste tous les users.
   */
  public static function all(): array
  {
    $rows = Database::all("SELECT * FROM users ORDER BY id ASC");
    return array_map(fn($row) => new self($row), $rows);
  }


  // =============================================================
  // Méthodes d'instance — comportements
  // =============================================================

  /**
   * Vérifie le mot de passe en clair contre le hash stocké.
   */
  public function verifyPassword(string $plain): bool
  {
    return password_verify($plain, $this->motDePasseHash);
  }

  /**
   * Convertit en tableau associatif (pour rester compatible avec l'ancien code
   * qui utilise $user['id'], $user['nom'], etc.).
   *
   * Exclut le mot de passe par défaut pour la sécurité.
   */
  public function toArray(bool $includePassword = false): array
  {
    $arr = [
      'id'              => $this->id,
      'nom'             => $this->nom,
      'email'           => $this->email,
      'cree_le'         => $this->creeLe,
      'est_conducteur'  => $this->estConducteur ? 1 : 0,
      'est_admin'       => $this->estAdmin ? 1 : 0,
    ];
    if ($includePassword) {
      $arr['mot_de_passe'] = $this->motDePasseHash;
    }
    return $arr;
  }


  // =============================================================
  // Méthodes de mutation
  // =============================================================

  /**
   * Promeut l'utilisateur conducteur. Met à jour l'objet et la BDD.
   */
  public function promoteConducteur(): bool
  {
    $stmt = Database::query(
      "UPDATE users SET est_conducteur = 1 WHERE id = :id",
      ['id' => $this->id]
    );
    if ($stmt->rowCount() > 0) {
      $this->estConducteur = true;
      return true;
    }
    return false;
  }

  /**
   * Met à jour le profil (nom + email).
   * Retourne ['ok' => bool, 'errors' => array]
   */
  public function updateProfile(string $nom, string $email): array
  {
    $errors = [];
    $nom    = trim($nom);
    $email  = trim(strtolower($email));

    if ($nom === '') {
      $errors['nom'] = 'Le nom est requis.';
    } elseif (mb_strlen($nom) > 50) {
      $errors['nom'] = 'Le nom est trop long (50 caractères max).';
    }
    if ($email === '') {
      $errors['email'] = 'L\'email est requis.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $errors['email'] = 'L\'email n\'est pas valide.';
    } else {
      $existing = Database::one(
        "SELECT id FROM users WHERE email = :email AND id != :id LIMIT 1",
        ['email' => $email, 'id' => $this->id]
      );
      if ($existing) {
        $errors['email'] = 'Cet email est déjà utilisé.';
      }
    }

    if (!empty($errors)) {
      return ['ok' => false, 'errors' => $errors];
    }

    Database::query(
      "UPDATE users SET nom = :nom, email = :email WHERE id = :id",
      ['nom' => $nom, 'email' => $email, 'id' => $this->id]
    );
    $this->nom = $nom;
    $this->email = $email;
    return ['ok' => true, 'errors' => []];
  }


  // =============================================================
  // Création (factory)
  // =============================================================

  /**
   * Crée un nouvel utilisateur. Retourne ['ok' => bool, 'errors' => [], 'user' => User|null]
   */
  public static function register(string $nom, string $email, string $motDePasse, string $confirmation): array
  {
    $errors = [];
    $nom    = trim($nom);
    $email  = trim(strtolower($email));

    if ($nom === '') {
      $errors['nom'] = 'Le nom est requis.';
    } elseif (mb_strlen($nom) > 50) {
      $errors['nom'] = 'Le nom est trop long (50 caractères max).';
    }
    if ($email === '') {
      $errors['email'] = 'L\'email est requis.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $errors['email'] = 'L\'email n\'est pas valide.';
    } elseif (self::findByEmail($email) !== null) {
      $errors['email'] = 'Un compte existe déjà avec cet email.';
    }
    if ($motDePasse === '') {
      $errors['mot_de_passe'] = 'Le mot de passe est requis.';
    } elseif (strlen($motDePasse) < 8) {
      $errors['mot_de_passe'] = 'Le mot de passe doit faire au moins 8 caractères.';
    }
    if ($motDePasse !== $confirmation) {
      $errors['confirmation'] = 'Les deux mots de passe ne correspondent pas.';
    }

    if (!empty($errors)) {
      return ['ok' => false, 'errors' => $errors, 'user' => null];
    }

    Database::query(
      "INSERT INTO users (nom, email, mot_de_passe, cree_le)
       VALUES (:nom, :email, :mdp, :cree_le)",
      [
        'nom'     => $nom,
        'email'   => $email,
        'mdp'     => password_hash($motDePasse, PASSWORD_DEFAULT),
        'cree_le' => date('c'),
      ]
    );

    $newId = (int) Database::getInstance()->lastInsertId();
    $user = self::find($newId);
    return ['ok' => true, 'errors' => [], 'user' => $user];
  }
}
