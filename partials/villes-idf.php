<?php
/**
 * partials/villes-idf.php — Suggestions de villes d'Île-de-France.
 *
 * <datalist> = liste de suggestions native du navigateur : quand on tape
 * dans un champ qui a list="villes-idf", le navigateur propose ces villes.
 * L'utilisateur peut quand même écrire une autre ville (ex : Lyon).
 */
$villesIdf = [
  // Paris
  'Paris',
  // Hauts-de-Seine (92)
  'Antony', 'Asnières-sur-Seine', 'Boulogne-Billancourt', 'Clamart', 'Clichy',
  'Colombes', 'Courbevoie', 'Garches', 'Gennevilliers', 'Issy-les-Moulineaux',
  'La Défense', 'La Garenne-Colombes', 'Levallois-Perret', 'Malakoff', 'Meudon',
  'Montrouge', 'Nanterre', 'Neuilly-sur-Seine', 'Puteaux', 'Rueil-Malmaison',
  'Saint-Cloud', 'Sèvres', 'Suresnes', 'Vaucresson', 'Ville-d\'Avray',
  // Yvelines (78)
  'Le Chesnay-Rocquencourt', 'Mantes-la-Jolie', 'Marly-le-Roi', 'Poissy',
  'Saint-Germain-en-Laye', 'Saint-Quentin-en-Yvelines', 'Vélizy-Villacoublay', 'Versailles',
  // Seine-Saint-Denis (93)
  'Aubervilliers', 'Aulnay-sous-Bois', 'Bobigny', 'Bondy', 'Montreuil',
  'Noisy-le-Grand', 'Pantin', 'Saint-Denis', 'Saint-Ouen-sur-Seine',
  // Val-de-Marne (94)
  'Champigny-sur-Marne', 'Créteil', 'Ivry-sur-Seine', 'Orly', 'Rungis',
  'Saint-Maur-des-Fossés', 'Vincennes', 'Vitry-sur-Seine',
  // Essonne (91)
  'Évry-Courcouronnes', 'Massy', 'Palaiseau', 'Saclay', 'Orsay',
  // Val-d'Oise (95)
  'Argenteuil', 'Cergy', 'Pontoise', 'Roissy-en-France', 'Sarcelles',
  // Seine-et-Marne (77)
  'Chessy', 'Fontainebleau', 'Marne-la-Vallée', 'Meaux', 'Melun', 'Torcy',
];
?>
<datalist id="villes-idf">
  <?php foreach ($villesIdf as $ville): ?>
    <option value="<?= htmlspecialchars($ville) ?>"></option>
  <?php endforeach; ?>
</datalist>
