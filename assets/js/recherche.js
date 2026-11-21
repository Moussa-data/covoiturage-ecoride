/* ============================================================
   EcoRide — formulaire de recherche de l'accueil
   - bouton ⇄ : inverse départ et arrivée
   - sélecteur − 1 + : nombre de passagers (1 à 4)
   - la date de retour ne peut pas être avant la date d'aller
   ============================================================ */

(function () {
  // ----- Inverser départ / arrivée -----
  const btnInverser = document.getElementById('btnInverser');
  const depart      = document.getElementById('depart');
  const arrivee     = document.getElementById('arrivee');

  if (btnInverser && depart && arrivee) {
    btnInverser.addEventListener('click', () => {
      [depart.value, arrivee.value] = [arrivee.value, depart.value];
      // petite rotation pour montrer que l'action a eu lieu
      btnInverser.classList.toggle('tourne');
    });
  }

  // ----- Nombre de passagers -----
  const champ   = document.getElementById('passagers');
  const affiche = document.getElementById('passagersAffiche');
  const boutons = document.querySelectorAll('.stepper-btn');
  const MIN = 1, MAX = 4;

  function mettreAJour(valeur) {
    champ.value = valeur;
    affiche.textContent = valeur;
    boutons.forEach((b) => {
      const delta = Number(b.dataset.delta);
      b.disabled = (delta < 0 && valeur <= MIN) || (delta > 0 && valeur >= MAX);
    });
    // relance l'animation "bump" à chaque changement
    affiche.classList.remove('bump');
    void affiche.offsetWidth;
    affiche.classList.add('bump');
  }

  if (champ && affiche) {
    boutons.forEach((b) => {
      b.addEventListener('click', () => {
        const valeur = Math.min(MAX, Math.max(MIN, Number(champ.value) + Number(b.dataset.delta)));
        mettreAJour(valeur);
      });
    });
    // état initial des boutons (sans animation)
    boutons.forEach((b) => {
      const delta = Number(b.dataset.delta);
      const v = Number(champ.value);
      b.disabled = (delta < 0 && v <= MIN) || (delta > 0 && v >= MAX);
    });
  }

  // ----- Retour jamais avant l'aller -----
  const aller  = document.getElementById('date');
  const retour = document.getElementById('retour');
  if (aller && retour) {
    aller.addEventListener('change', () => {
      if (aller.value) {
        retour.min = aller.value;
        if (retour.value && retour.value < aller.value) retour.value = aller.value;
      }
    });
  }
})();
