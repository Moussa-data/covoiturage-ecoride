<?php
/**
 * src/Models/Trajet.php — Modèle Trajet en POO
 */

namespace EcoRide\Models;

use EcoRide\Database;

/**
 * Classe Trajet — représente un trajet de covoiturage.
 */
class Trajet
{
  public int $id;
  public ?int $userId;

  // Conducteur (objet imbriqué pour rester compatible avec le format JSON)
  public array $conducteur;

  // Itinéraire
  public string $depart;
  public string $departLieu;
  public string $arrivee;
  public string $arriveeLieu;

  // Horaires
  public string $heureDepart;
  public string $heureArrivee;
  public ?string $duree;
  public string $date;

  // Détails
  public int $prix;
  public int $placesDispo;
  public string $typeVehicule;
  public ?string $modeleVehicule;
  public float $co2EconomiseKg;
  public ?string $description;

  // Préférences
  public array $preferences;

  /**
   * Constructeur — prend une ligne BDD plate et reconstitue les objets imbriqués.
   */
  public function __construct(array $row)
  {
    $this->id            = (int) ($row['id'] ?? 0);
    $this->userId        = isset($row['user_id']) ? (int) $row['user_id'] : null;
    $this->conducteur    = [
      'nom'                => $row['conducteur_nom'] ?? '',
      'note'               => (float) ($row['conducteur_note'] ?? 5.0),
      'avatar'             => $row['conducteur_avatar'] ?? null,
      'trajets_effectues'  => (int) ($row['conducteur_trajets_effectues'] ?? 0),
      'verifie'            => (bool) ($row['conducteur_verifie'] ?? false),
    ];
    $this->depart        = $row['depart'] ?? '';
    $this->departLieu    = $row['depart_lieu'] ?? '';
    $this->arrivee       = $row['arrivee'] ?? '';
    $this->arriveeLieu   = $row['arrivee_lieu'] ?? '';
    $this->heureDepart   = $row['heure_depart'] ?? '';
    $this->heureArrivee  = $row['heure_arrivee'] ?? '';
    $this->duree         = $row['duree'] ?? null;
    $this->date          = $row['date'] ?? '';
    $this->prix          = (int) ($row['prix'] ?? 0);
    $this->placesDispo   = (int) ($row['places_dispo'] ?? 0);
    $this->typeVehicule  = $row['type_vehicule'] ?? 'essence';
    $this->modeleVehicule= $row['modele_vehicule'] ?? null;
    $this->co2EconomiseKg= (float) ($row['co2_economise_kg'] ?? 0);
    $this->description   = $row['description'] ?? null;
    $this->preferences   = [
      'fumeur'     => (bool) ($row['pref_fumeur'] ?? false),
      'animaux'    => (bool) ($row['pref_animaux'] ?? false),
      'musique'    => (bool) ($row['pref_musique'] ?? true),
      'discussion' => $row['pref_discussion'] ?? 'selon humeur',
    ];
  }

  // =============================================================
  // Finders
  // =============================================================

  public static function find(int $id): ?self
  {
    $row = Database::one("SELECT * FROM trajets WHERE id = :id LIMIT 1", ['id' => $id]);
    return $row ? new self($row) : null;
  }

  /**
   * Tous les trajets, triés par date croissante.
   */
  public static function all(): array
  {
    $rows = Database::all("SELECT * FROM trajets ORDER BY date ASC, heure_depart ASC");
    return array_map(fn($r) => new self($r), $rows);
  }

  /**
   * Trajets publiés par un user donné (le conducteur).
   */
  public static function findByUser(int $userId): array
  {
    $rows = Database::all(
      "SELECT * FROM trajets WHERE user_id = :uid ORDER BY date DESC, heure_depart DESC",
      ['uid' => $userId]
    );
    return array_map(fn($r) => new self($r), $rows);
  }

  /**
   * Recherche avec filtres (utilisée par la barre de recherche).
   */
  public static function search(array $filters = []): array
  {
    $sql = "SELECT * FROM trajets WHERE 1=1";
    $params = [];

    if (!empty($filters['depart'])) {
      $sql .= " AND LOWER(depart) LIKE :depart";
      $params['depart'] = '%' . strtolower($filters['depart']) . '%';
    }
    if (!empty($filters['arrivee'])) {
      $sql .= " AND LOWER(arrivee) LIKE :arrivee";
      $params['arrivee'] = '%' . strtolower($filters['arrivee']) . '%';
    }
    if (!empty($filters['date'])) {
      $sql .= " AND date = :date";
      $params['date'] = $filters['date'];
    }

    $sql .= " ORDER BY date ASC, heure_depart ASC";
    $rows = Database::all($sql, $params);
    return array_map(fn($r) => new self($r), $rows);
  }


  // =============================================================
  // Mutations
  // =============================================================

  /**
   * Supprime ce trajet (uniquement si appartient au user donné).
   */
  public function delete(int $userId): bool
  {
    $stmt = Database::query(
      "DELETE FROM trajets WHERE id = :id AND user_id = :uid",
      ['id' => $this->id, 'uid' => $userId]
    );
    return $stmt->rowCount() > 0;
  }

  /**
   * Liste les passagers ayant réservé ce trajet (avec JOIN users).
   */
  public function getPassengers(): array
  {
    return Database::all(
      "SELECT u.id AS user_id, u.nom, u.email, r.id AS reservation_id, r.cree_le
       FROM reservations r
       INNER JOIN users u ON u.id = r.user_id
       WHERE r.trajet_id = :tid AND r.statut = 'confirmee'
       ORDER BY r.cree_le ASC",
      ['tid' => $this->id]
    );
  }

  /**
   * Convertit en format tableau (compatible avec l'ancien code).
   * Reproduit exactement le format de shapeTrajetRow().
   */
  public function toArray(): array
  {
    return [
      'id'              => $this->id,
      'conducteur'      => $this->conducteur,
      'depart'          => $this->depart,
      'depart_lieu'     => $this->departLieu,
      'arrivee'         => $this->arrivee,
      'arrivee_lieu'    => $this->arriveeLieu,
      'heure_depart'    => $this->heureDepart,
      'heure_arrivee'   => $this->heureArrivee,
      'duree'           => $this->duree,
      'date'            => $this->date,
      'prix'            => $this->prix,
      'places_dispo'    => $this->placesDispo,
      'type_vehicule'   => $this->typeVehicule,
      'modele_vehicule' => $this->modeleVehicule,
      'co2_economise_kg'=> $this->co2EconomiseKg,
      'description'     => $this->description,
      'preferences'     => $this->preferences,
    ];
  }
}
