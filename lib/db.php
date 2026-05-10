<?php
/**
 * lib/db.php — Connexion à la base de données (SQLite via PDO)
 *
 * NOTIONS CLÉS :
 *
 * 1. PDO (PHP Data Objects)
 *    → Une couche d'abstraction pour parler à n'importe quelle BDD (MySQL, SQLite, Postgres...).
 *    → Pour passer de SQLite à MySQL, on changera UNIQUEMENT cette ligne :
 *        $dsn = 'sqlite:...'   →   $dsn = 'mysql:host=localhost;dbname=ecoride;charset=utf8mb4'
 *    → Le reste du code fonctionne tel quel.
 *
 * 2. Requêtes préparées
 *    → JAMAIS faire $sql = "SELECT ... WHERE id = $id" : c'est l'injection SQL garantie.
 *    → TOUJOURS faire :
 *        $stmt = $pdo->prepare("SELECT ... WHERE id = :id");
 *        $stmt->execute(['id' => $id]);
 *    → PDO échappe les valeurs pour toi. C'est LA seule façon sûre.
 *
 * 3. Singleton
 *    → On veut UNE SEULE connexion par requête HTTP (pas une par fonction).
 *    → On la crée à la première demande, on la garde dans une variable statique.
 */

const DB_FILE = __DIR__ . '/../data/ecoride.sqlite';


/**
 * Retourne l'unique connexion PDO à la base.
 * Crée la base + le schéma + les données initiales au premier appel.
 */
function db(): PDO
{
  // static = la variable garde sa valeur entre les appels de cette fonction
  static $pdo = null;
  if ($pdo !== null) {
    return $pdo;
  }

  $premierLancement = !file_exists(DB_FILE);

  // Connexion à SQLite. Le fichier est créé automatiquement s'il n'existe pas.
  $pdo = new PDO('sqlite:' . DB_FILE);

  // Configuration importante :
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  // → en cas d'erreur SQL, PDO lance une Exception (au lieu de juste retourner false).
  //   Tu vois l'erreur tout de suite, pas un bug silencieux.

  $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
  // → fetch() retourne un tableau associatif ['nom' => '...'] et pas un objet ou un truc bizarre.

  // Active les clés étrangères dans SQLite (désactivées par défaut !)
  $pdo->exec('PRAGMA foreign_keys = ON');

  // Premier lancement : on crée les tables et on importe les données existantes
  if ($premierLancement) {
    initSchema($pdo);
    seedFromJson($pdo);
  }

  // La table reservations est créée à la demande (rétrocompatible avec
  // les utilisateurs qui ont déjà la BDD sans cette table).
  require_once __DIR__ . '/reservations.php';
  initReservationsSchema($pdo);

  // Table avis (rétrocompatible aussi)
  require_once __DIR__ . '/avis.php';
  initAvisSchema($pdo);

  // Table messages
  require_once __DIR__ . '/messages.php';
  initMessagesSchema($pdo);

  // Migrations idempotentes : s'exécutent à chaque démarrage mais
  // ne font rien si elles ont déjà été appliquées.
  migrateAddConducteurColumn($pdo);
  migrateCleanSelfMessages($pdo);
  migrateAddAdminColumn($pdo);
  migrateLinkOrphanTrajets($pdo);

  return $pdo;
}


/**
 * MIGRATION : ajoute la colonne est_admin à users.
 * Auto-promeut l'user id=1 (le créateur) en admin.
 */
function migrateAddAdminColumn(PDO $pdo): void
{
  $cols = $pdo->query("PRAGMA table_info(users)")->fetchAll();
  foreach ($cols as $col) {
    if ($col['name'] === 'est_admin') {
      return; // déjà migrée
    }
  }
  $pdo->exec("ALTER TABLE users ADD COLUMN est_admin INTEGER NOT NULL DEFAULT 0");

  // L'utilisateur id=1 est le créateur du site → automatiquement admin
  $pdo->exec("UPDATE users SET est_admin = 1 WHERE id = 1");
}


/**
 * MIGRATION : pour chaque trajet "orphelin" (user_id NULL), crée un user
 * fictif portant le nom du conducteur et lie le trajet à ce user.
 *
 * Avantages :
 *  - Tous les trajets ont un propriétaire en BDD → cohérence
 *  - Les passagers peuvent contacter ces "conducteurs fictifs" (utile pour la démo)
 *  - Les comptes fictifs ne sont pas connectables (mot de passe random impossible à retrouver)
 */
function migrateLinkOrphanTrajets(PDO $pdo): void
{
  // On cherche tous les trajets sans user_id
  $orphans = $pdo->query("
    SELECT DISTINCT conducteur_nom
    FROM trajets
    WHERE user_id IS NULL
  ")->fetchAll();

  if (empty($orphans)) {
    return; // rien à faire
  }

  foreach ($orphans as $row) {
    $nom = $row['conducteur_nom'];

    // Génère un email fictif unique basé sur le nom
    // Ex: "Thomas R." → "thomas-r@demo.ecoride.local"
    $emailFictif = strtolower(
      preg_replace('/[^a-z0-9]+/i', '-', $nom)
    ) . '@demo.ecoride.local';
    $emailFictif = trim($emailFictif, '-');

    // Vérifie que ce user fictif n'existe pas déjà
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
    $stmt->execute(['email' => $emailFictif]);
    $userId = $stmt->fetchColumn();

    if ($userId === false) {
      // On crée le user fictif avec un hash random (impossible à connecter dessus)
      $stmt = $pdo->prepare("
        INSERT INTO users (nom, email, mot_de_passe, cree_le, est_conducteur, est_admin)
        VALUES (:nom, :email, :mdp, :cree_le, 1, 0)
      ");
      $stmt->execute([
        'nom'     => $nom,
        'email'   => $emailFictif,
        // bin2hex(random_bytes(32)) = 64 chars de hex aléatoires.
        // password_hash dessus, on ne pourra jamais retrouver ce mdp.
        'mdp'     => password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT),
        'cree_le' => date('c'),
      ]);
      $userId = (int) $pdo->lastInsertId();
    }

    // Lie tous les trajets de ce conducteur fictif
    $stmt = $pdo->prepare("
      UPDATE trajets
      SET user_id = :uid
      WHERE conducteur_nom = :nom AND user_id IS NULL
    ");
    $stmt->execute(['uid' => $userId, 'nom' => $nom]);
  }
}


/**
 * MIGRATION : supprime les éventuels messages où expediteur = destinataire.
 * Ces messages "à soi-même" ne devraient jamais exister (canMessage les bloque)
 * mais on nettoie au cas où il y en aurait dans la BDD.
 */
function migrateCleanSelfMessages(PDO $pdo): void
{
  // On vérifie d'abord si la table messages existe (sinon erreur)
  $check = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='messages'")->fetchColumn();
  if (!$check) {
    return;
  }
  $pdo->exec("DELETE FROM messages WHERE expediteur_id = destinataire_id");
}


/**
 * MIGRATION : ajoute la colonne est_conducteur à la table users.
 *
 * En SQL, "modifier le schéma sans perdre les données" s'appelle une migration.
 * En vraie prod, on a des outils dédiés (Doctrine Migrations, Phinx, Liquibase...)
 * mais le principe est le même : du code qui dit "si la colonne X manque, ajoute-la".
 *
 * Idempotence : on vérifie d'abord si la colonne existe déjà, pour pouvoir
 * relancer cette fonction sans soucis.
 */
function migrateAddConducteurColumn(PDO $pdo): void
{
  // PRAGMA table_info(t) retourne les colonnes d'une table en SQLite
  $cols = $pdo->query("PRAGMA table_info(users)")->fetchAll();
  foreach ($cols as $col) {
    if ($col['name'] === 'est_conducteur') {
      return; // déjà migrée
    }
  }

  // ALTER TABLE pour ajouter la colonne
  $pdo->exec("ALTER TABLE users ADD COLUMN est_conducteur INTEGER NOT NULL DEFAULT 0");

  // Auto-promotion : tout user qui a DÉJÀ publié un trajet devient conducteur
  // (sinon il serait bloqué d'accéder à ses propres trajets après la migration).
  $pdo->exec("
    UPDATE users
    SET est_conducteur = 1
    WHERE id IN (SELECT DISTINCT user_id FROM trajets WHERE user_id IS NOT NULL)
  ");
}


/**
 * Crée les tables de la base.
 *
 * Notes SQLite :
 *  - INTEGER PRIMARY KEY AUTOINCREMENT : ID auto-incrémenté
 *  - Les booléens n'existent pas, on stocke en INTEGER (0 ou 1)
 *  - TEXT pour les strings, INTEGER pour les entiers, REAL pour les décimaux
 */
function initSchema(PDO $pdo): void
{
  $pdo->exec("
    CREATE TABLE users (
      id            INTEGER PRIMARY KEY AUTOINCREMENT,
      nom           TEXT NOT NULL,
      email         TEXT NOT NULL UNIQUE,
      mot_de_passe  TEXT NOT NULL,
      cree_le       TEXT NOT NULL
    );
  ");

  $pdo->exec("
    CREATE TABLE trajets (
      id                            INTEGER PRIMARY KEY AUTOINCREMENT,

      -- Conducteur (on aplatit l'objet imbriqué pour rester en 1 table)
      conducteur_nom                TEXT NOT NULL,
      conducteur_note               REAL NOT NULL DEFAULT 5.0,
      conducteur_avatar             TEXT,
      conducteur_trajets_effectues  INTEGER NOT NULL DEFAULT 0,
      conducteur_verifie            INTEGER NOT NULL DEFAULT 0,

      -- Itinéraire
      depart        TEXT NOT NULL,
      depart_lieu   TEXT NOT NULL,
      arrivee       TEXT NOT NULL,
      arrivee_lieu  TEXT NOT NULL,

      -- Horaires
      heure_depart   TEXT NOT NULL,
      heure_arrivee  TEXT NOT NULL,
      duree          TEXT,
      date           TEXT NOT NULL,

      -- Détails
      prix              INTEGER NOT NULL,
      places_dispo      INTEGER NOT NULL,
      type_vehicule     TEXT NOT NULL,
      modele_vehicule   TEXT,
      co2_economise_kg  REAL,
      description       TEXT,

      -- Préférences (aplaties aussi)
      pref_fumeur      INTEGER NOT NULL DEFAULT 0,
      pref_animaux     INTEGER NOT NULL DEFAULT 0,
      pref_musique     INTEGER NOT NULL DEFAULT 1,
      pref_discussion  TEXT NOT NULL DEFAULT 'selon humeur',

      -- Lien vers l'utilisateur (qui a publié) — optionnel pour les anciens trajets
      user_id  INTEGER,
      cree_le  TEXT,

      FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    );
  ");

  // Quelques index pour accélérer les recherches courantes
  $pdo->exec("CREATE INDEX idx_trajets_date    ON trajets(date);");
  $pdo->exec("CREATE INDEX idx_trajets_depart  ON trajets(depart);");
  $pdo->exec("CREATE INDEX idx_trajets_arrivee ON trajets(arrivee);");
}


/**
 * Importe les données existantes depuis les JSON dans la base.
 * Permet de ne rien perdre lors du passage JSON → SQL.
 */
function seedFromJson(PDO $pdo): void
{
  // ----- USERS -----
  $usersFile = __DIR__ . '/../data/users.json';
  if (file_exists($usersFile)) {
    $users = json_decode(file_get_contents($usersFile), true) ?: [];
    $stmt = $pdo->prepare("
      INSERT INTO users (id, nom, email, mot_de_passe, cree_le)
      VALUES (:id, :nom, :email, :mdp, :cree_le)
    ");
    foreach ($users as $u) {
      $stmt->execute([
        'id'      => $u['id'],
        'nom'     => $u['nom'],
        'email'   => $u['email'],
        'mdp'     => $u['mot_de_passe'],
        'cree_le' => $u['cree_le'],
      ]);
    }
  }

  // ----- TRAJETS -----
  $trajetsFile = __DIR__ . '/../data/trajets.json';
  if (file_exists($trajetsFile)) {
    $trajets = json_decode(file_get_contents($trajetsFile), true) ?: [];
    $stmt = $pdo->prepare("
      INSERT INTO trajets (
        id,
        conducteur_nom, conducteur_note, conducteur_avatar,
        conducteur_trajets_effectues, conducteur_verifie,
        depart, depart_lieu, arrivee, arrivee_lieu,
        heure_depart, heure_arrivee, duree, date,
        prix, places_dispo, type_vehicule, modele_vehicule,
        co2_economise_kg, description,
        pref_fumeur, pref_animaux, pref_musique, pref_discussion
      ) VALUES (
        :id,
        :c_nom, :c_note, :c_avatar,
        :c_te, :c_verif,
        :depart, :depart_lieu, :arrivee, :arrivee_lieu,
        :hd, :ha, :duree, :date,
        :prix, :places, :type_v, :modele,
        :co2, :desc,
        :pf, :pa, :pm, :pd
      )
    ");
    foreach ($trajets as $t) {
      $cond  = $t['conducteur'] ?? [];
      $prefs = $t['preferences'] ?? [];
      $stmt->execute([
        'id'           => $t['id'] ?? null,
        'c_nom'        => $cond['nom'] ?? '',
        'c_note'       => $cond['note'] ?? 5.0,
        'c_avatar'     => $cond['avatar'] ?? null,
        'c_te'         => $cond['trajets_effectues'] ?? 0,
        'c_verif'      => !empty($cond['verifie']) ? 1 : 0,
        'depart'       => $t['depart'] ?? '',
        'depart_lieu'  => $t['depart_lieu'] ?? '',
        'arrivee'      => $t['arrivee'] ?? '',
        'arrivee_lieu' => $t['arrivee_lieu'] ?? '',
        'hd'           => $t['heure_depart'] ?? '',
        'ha'           => $t['heure_arrivee'] ?? '',
        'duree'        => $t['duree'] ?? null,
        'date'         => $t['date'] ?? '',
        'prix'         => $t['prix'] ?? 0,
        'places'       => $t['places_dispo'] ?? 1,
        'type_v'       => $t['type_vehicule'] ?? 'essence',
        'modele'       => $t['modele_vehicule'] ?? null,
        'co2'          => $t['co2_economise_kg'] ?? 0,
        'desc'         => $t['description'] ?? null,
        'pf'           => !empty($prefs['fumeur'])  ? 1 : 0,
        'pa'           => !empty($prefs['animaux']) ? 1 : 0,
        'pm'           => isset($prefs['musique']) ? ($prefs['musique'] ? 1 : 0) : 1,
        'pd'           => $prefs['discussion'] ?? 'selon humeur',
      ]);
    }
  }
}


/**
 * Helper : transforme une ligne plate de la BDD en objet imbriqué
 * (conducteur, preferences) pour rester compatible avec le code existant.
 */
function shapeTrajetRow(array $row): array
{
  return [
    'id'           => (int) $row['id'],
    'conducteur'   => [
      'nom'                => $row['conducteur_nom'],
      'note'               => (float) $row['conducteur_note'],
      'avatar'             => $row['conducteur_avatar'],
      'trajets_effectues'  => (int) $row['conducteur_trajets_effectues'],
      'verifie'            => (bool) $row['conducteur_verifie'],
    ],
    'depart'         => $row['depart'],
    'depart_lieu'    => $row['depart_lieu'],
    'arrivee'        => $row['arrivee'],
    'arrivee_lieu'   => $row['arrivee_lieu'],
    'heure_depart'   => $row['heure_depart'],
    'heure_arrivee'  => $row['heure_arrivee'],
    'duree'          => $row['duree'],
    'date'           => $row['date'],
    'prix'           => (int) $row['prix'],
    'places_dispo'   => (int) $row['places_dispo'],
    'type_vehicule'  => $row['type_vehicule'],
    'modele_vehicule'=> $row['modele_vehicule'],
    'co2_economise_kg' => (float) $row['co2_economise_kg'],
    'description'    => $row['description'],
    'preferences'    => [
      'fumeur'     => (bool) $row['pref_fumeur'],
      'animaux'    => (bool) $row['pref_animaux'],
      'musique'    => (bool) $row['pref_musique'],
      'discussion' => $row['pref_discussion'],
    ],
  ];
}
