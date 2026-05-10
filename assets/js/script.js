/* ============================================================
   EcoRide — script principal (version simplifiée)
   ============================================================
   Les trajets sont maintenant rendus côté serveur (PHP).
   Ce script gère UNIQUEMENT les filtres instantanés en sidebar :
   - slider de prix max
   - cases à cocher type de véhicule

   On lit les attributs data-prix et data-type sur chaque .trip-card
   et on cache/affiche selon les critères.
   ============================================================ */

const $trips        = document.getElementById('trips');
const $prixMax      = document.getElementById('prixMax');
const $prixMaxValue = document.getElementById('prixMaxValue');
const $vehicules    = document.querySelectorAll('input[name="vehicule"]');
const $resetBtn     = document.getElementById('resetFilters');

// Tous les .trip-card du DOM (rendus par PHP)
const $cards = () => document.querySelectorAll('.trip-card');


function appliquerFiltres() {
  const prixMax = Number($prixMax.value);
  const typesCoches = Array.from($vehicules)
    .filter(input => input.checked)
    .map(input => input.value);

  let visibles = 0;
  $cards().forEach(card => {
    const prix = Number(card.dataset.prix);
    const type = card.dataset.type;
    const ok   = prix <= prixMax && typesCoches.includes(type);
    card.style.display = ok ? '' : 'none';
    if (ok) visibles++;
  });

  // Met à jour le message si plus aucun résultat visible
  const ancienVide = document.querySelector('.empty.empty-filters');
  if (visibles === 0 && $cards().length > 0 && !ancienVide) {
    const div = document.createElement('div');
    div.className = 'empty empty-filters';
    div.textContent = 'Aucun trajet ne correspond à tes filtres.';
    $trips.appendChild(div);
  } else if (visibles > 0 && ancienVide) {
    ancienVide.remove();
  }
}


// Slider du prix
if ($prixMax) {
  $prixMax.addEventListener('input', () => {
    $prixMaxValue.textContent = $prixMax.value;
    appliquerFiltres();
  });
}

// Checkboxes type de véhicule
$vehicules.forEach(input => {
  input.addEventListener('change', appliquerFiltres);
});

// Bouton Réinitialiser
if ($resetBtn) {
  $resetBtn.addEventListener('click', () => {
    $prixMax.value = 20;
    $prixMaxValue.textContent = '20';
    $vehicules.forEach(input => input.checked = true);
    appliquerFiltres();
  });
}
