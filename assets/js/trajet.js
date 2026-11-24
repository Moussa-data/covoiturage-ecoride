/* ============================================================
   EcoRide — page d'un trajet
   Choix du nombre de places : le récapitulatif se recalcule en direct.
   Même calcul que le serveur (calculerCommission en PHP) :
   frais = max(sous-total × taux, minimum), arrondis au centime.
   Le serveur recalcule de toute façon au moment de réserver.
   ============================================================ */

(function () {
  const bloc = document.querySelector('.recap-places');
  if (!bloc) return;

  const prix    = Number(bloc.dataset.prix);
  const taux    = Number(bloc.dataset.taux);
  const minimum = Number(bloc.dataset.minimum);
  const boutons = bloc.querySelectorAll('.stepper-btn');
  const max     = Number(bloc.querySelector('[data-max]').dataset.max);

  const affiche   = document.getElementById('placesAffiche');
  const champ     = document.getElementById('placesChamp');
  const detail    = document.getElementById('recapDetail');
  const sousTotal = document.getElementById('recapSousTotal');
  const frais     = document.getElementById('recapFrais');
  const total     = document.getElementById('recapTotal');
  const btnTotal  = document.getElementById('btnTotal');

  // 12 → "12" ; 2.5 → "2,50"
  const euros = (n) => (Math.abs(n - Math.round(n)) < 0.001
    ? String(Math.round(n))
    : n.toFixed(2).replace('.', ',')) + ' €';

  function maj(places) {
    const st = prix * places;
    const f  = Math.round(Math.max(st * taux, minimum) * 100) / 100;
    affiche.textContent = places;
    if (champ) champ.value = places;
    detail.textContent    = places > 1 ? '(' + places + ' × ' + euros(prix) + ')' : '';
    sousTotal.textContent = euros(st);
    if (frais) frais.textContent = euros(f);
    total.textContent     = euros(st + f);
    if (btnTotal) btnTotal.textContent = '· ' + euros(st + f);

    boutons.forEach((b) => {
      const d = Number(b.dataset.delta);
      b.disabled = (d < 0 && places <= 1) || (d > 0 && places >= max);
    });
    total.classList.remove('bump');
    void total.offsetWidth;
    total.classList.add('bump');
  }

  boutons.forEach((b) => {
    b.addEventListener('click', () => {
      const v = Math.min(max, Math.max(1, Number(affiche.textContent) + Number(b.dataset.delta)));
      maj(v);
    });
  });
  // état initial des boutons
  const v0 = Number(affiche.textContent);
  boutons.forEach((b) => {
    const d = Number(b.dataset.delta);
    b.disabled = (d < 0 && v0 <= 1) || (d > 0 && v0 >= max);
  });

  // Confirmation avant d'envoyer la réservation
  const form = document.getElementById('formReservation');
  if (form) {
    form.addEventListener('submit', (e) => {
      const places = Number(affiche.textContent);
      const msg = 'Confirmer pour ' + places + ' place' + (places > 1 ? 's' : '') + ' (' + total.textContent + ') ?';
      if (!window.confirm(msg)) e.preventDefault();
    });
  }
})();
