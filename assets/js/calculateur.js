/* ============================================================
   EcoRide — calculateur "Vous conduisez déjà ce trajet ?"
   coût du trajet  = distance × coût au km (selon la voiture)
   prix conseillé  = coût / (passagers + conducteur), arrondi aux 10 centimes
   économie        = prix conseillé × passagers × nombre de trajets,
                     jamais plus que le coût réel (pas de bénéfice)
   Le montant "défile" jusqu'à sa nouvelle valeur (sauf si l'utilisateur
   a demandé moins d'animations).
   ============================================================ */

(function () {
  const km = document.getElementById('calcKm');
  if (!km) return;

  const kmAffiche = document.getElementById('calcKmAffiche');
  const montant   = document.getElementById('calcMontant');
  const periode   = document.getElementById('calcPeriode');
  const label     = document.getElementById('calcLabel');
  const prixEl    = document.getElementById('calcPrix');
  const coutEl    = document.getElementById('calcCout');
  const reduit    = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  const valeur = (nom) => Number(document.querySelector('input[name="' + nom + '"]:checked').value);
  const euros  = (n) => (Math.abs(n - Math.round(n)) < 0.001 ? String(Math.round(n)) : n.toFixed(2).replace('.', ',')) + ' €';

  let actuel = 0;
  let animation = null;

  function defiler(cible) {
    cancelAnimationFrame(animation);
    if (reduit) { montant.textContent = Math.round(cible); actuel = cible; return; }
    const depart = actuel, debut = performance.now(), duree = 450;
    const pas = (t) => {
      const p = Math.min(1, (t - debut) / duree);
      const ease = 1 - Math.pow(1 - p, 3);
      actuel = depart + (cible - depart) * ease;
      montant.textContent = Math.round(actuel);
      if (p < 1) animation = requestAnimationFrame(pas);
    };
    animation = requestAnimationFrame(pas);
  }

  function calculer() {
    const distance  = Number(km.value);
    const passagers = valeur('calcPassagers');
    const parKm     = valeur('calcVoiture');
    const trajets   = valeur('calcFrequence');

    const cout = distance * parKm;
    const prix = Math.max(0.1, Math.round((cout / (passagers + 1)) * 10) / 10);
    // On plafonne au coût du trajet : en covoiturage, pas de bénéfice
    const economie = Math.min(prix * passagers, cout) * trajets;

    kmAffiche.textContent = distance;
    prixEl.textContent = euros(prix);
    coutEl.textContent = euros(Math.round(cout * 100) / 100);
    label.textContent  = 'Vous économisez environ';
    periode.textContent = trajets === 1 ? 'sur ce trajet' : 'par mois (20 jours, aller-retour)';
    defiler(economie);
  }

  document.querySelectorAll('#calculateur input').forEach((el) => el.addEventListener('input', calculer));
  calculer();
})();
