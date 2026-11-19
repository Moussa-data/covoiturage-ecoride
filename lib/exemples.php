<?php
/**
 * lib/exemples.php — Trajets d'exemple affichés quand personne n'a encore publié.
 *
 * Ces trajets ne sont PAS en base de données : ils servent uniquement à montrer
 * à quoi ressemblera la page. Dès qu'un vrai trajet est publié, ils disparaissent.
 * Ils ne sont ni cliquables ni réservables.
 */

function trajetsExemples(): array
{
  $exemples = [
    ['Camille D.', 4.9, 31, 'Paris Gare du Nord', 'Lille Flandres', '07:30', '10:20', 15, 3, 'electrique', 'Renault Mégane E-Tech'],
    ['Yanis B.',   4.7, 12, 'Lyon Part-Dieu',     'Grenoble Gare',  '08:15', '09:35',  8, 2, 'hybride',    'Toyota Corolla'],
    ['Inès M.',    4.8, 24, 'Bordeaux Saint-Jean','Toulouse Matabiau','09:00','11:30', 13, 3, 'essence',    'Peugeot 308'],
    ['Lucas F.',   5.0,  8, 'Nantes Gare',        'Rennes Gare',    '17:45', '19:10',  7, 4, 'electrique', 'Tesla Model Y'],
    ['Aminata S.', 4.6, 19, 'Marseille Saint-Charles', 'Montpellier Centre', '18:30', '20:30', 11, 2, 'hybride', 'Kia Niro'],
  ];

  $trajets = [];
  foreach ($exemples as [$nom, $note, $nb, $depart, $arrivee, $hd, $ha, $prix, $places, $type, $modele]) {
    $trajets[] = [
      'conducteur'       => ['nom' => $nom, 'note' => $note, 'avatar' => '', 'trajets_effectues' => $nb, 'verifie' => true],
      'depart_lieu'      => $depart,
      'arrivee_lieu'     => $arrivee,
      'heure_depart'     => $hd,
      'heure_arrivee'    => $ha,
      'duree'            => calculerDuree($hd, $ha),
      'prix'             => $prix,
      'places_dispo'     => $places,
      'type_vehicule'    => $type,
      'modele_vehicule'  => $modele,
      'co2_economise_kg' => estimerCo2($type, $hd, $ha),
    ];
  }
  return $trajets;
}
