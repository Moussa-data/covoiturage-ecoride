  <footer class="site-footer">
    <div class="container footer-grid">

      <div class="footer-col footer-brand">
        <a href="/" class="footer-logo">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M12 2C9 5 6 8 6 12a6 6 0 0 0 12 0c0-4-3-7-6-10z" fill="currentColor"/>
          </svg>
          <span>EcoRide</span>
        </a>
        <p class="footer-tagline">Le covoiturage écologique 🌱</p>
      </div>

      <div class="footer-col">
        <h4>Découvrir</h4>
        <ul>
          <li><a href="/">Accueil</a></li>
          <li><a href="/a-propos.php">À propos</a></li>
          <li><a href="/inscription.php">S'inscrire</a></li>
          <li><a href="/connexion.php">Se connecter</a></li>
        </ul>
      </div>

      <div class="footer-col">
        <h4>Légal</h4>
        <ul>
          <li><a href="/mentions-legales.php">Mentions légales</a></li>
          <li><a href="/a-propos.php">À propos</a></li>
        </ul>
      </div>

      <div class="footer-col">
        <h4>Code source</h4>
        <p class="footer-source">
          Projet open-source, codé en PHP / SQLite vanilla.
          <a href="https://github.com" target="_blank" rel="noopener">Voir sur GitHub</a>
        </p>
      </div>

    </div>

    <div class="container footer-bottom">
      <p>© <?= date('Y') ?> EcoRide — Projet d'apprentissage de Moussa.</p>
    </div>
  </footer>

  <!-- Scripts UI : burger mobile + menu utilisateur -->
  <script>
    // Menu burger mobile
    (function () {
      const btn  = document.getElementById('burgerBtn');
      const nav  = document.querySelector('.main-nav');
      const auth = document.querySelector('.auth-buttons');
      if (!btn || !nav) return;
      btn.addEventListener('click', () => {
        const open = nav.classList.toggle('mobile-open');
        if (auth) auth.classList.toggle('mobile-open', open);
        btn.classList.toggle('is-open', open);
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
      });
    })();

    // Menu utilisateur : se ferme quand on clique ailleurs
    (function () {
      const userMenu = document.querySelector('.user-menu');
      if (!userMenu) return;
      document.addEventListener('click', (e) => {
        if (userMenu.open && !userMenu.contains(e.target)) {
          userMenu.open = false;
        }
      });
      // Fermer aussi quand on clique sur un lien interne du menu
      userMenu.querySelectorAll('a').forEach(link => {
        link.addEventListener('click', () => {
          userMenu.open = false;
        });
      });
    })();
  </script>
</body>
</html>
