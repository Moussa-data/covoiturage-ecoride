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
  // Trajets courts du quotidien en Île-de-France (domicile → travail, gare, aéroport)
  $exemples = [
    ['Camille D.', 4.9, 31, 'Vaucresson Gare',          'La Défense Esplanade',   '07:45', '08:10', 2.5, 3, 'electrique', 'Renault Mégane E-Tech'],
    ['Yanis B.',   4.7, 12, 'Versailles Château',       'Paris Montparnasse',     '08:00', '08:40', 3.5, 2, 'hybride',    'Toyota Corolla'],
    ['Inès M.',    4.8, 24, 'Saint-Denis Pleyel',       'Massy TGV',              '07:15', '08:05', 4,   3, 'essence',    'Peugeot 308'],
    ['Lucas F.',   5.0,  8, 'Boulogne Marcel Sembat',   'Roissy CDG Terminal 2',  '06:30', '07:30', 6,   4, 'electrique', 'Tesla Model Y'],
    ['Aminata S.', 4.6, 19, 'Créteil Préfecture',       'Nanterre Université',    '17:30', '18:35', 4.5, 2, 'hybride',    'Kia Niro'],
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
