/* admin.js — bouton « Copier le lien » des codes d'invitation */
document.querySelectorAll('.btn-copier').forEach(function (bouton) {
  bouton.addEventListener('click', function () {
    var texte = bouton.dataset.copier;
    var fini = function () {
      var avant = bouton.textContent;
      bouton.textContent = 'Lien copié ✓';
      setTimeout(function () { bouton.textContent = avant; }, 1800);
    };
    if (navigator.clipboard) {
      navigator.clipboard.writeText(texte).then(fini, function () { window.prompt('Copiez le lien :', texte); });
    } else {
      window.prompt('Copiez le lien :', texte);
    }
  });
});
