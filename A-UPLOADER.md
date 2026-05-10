# 📤 Fichiers à uploader sur la prod

Salut Moussa, voici ce que j'ai préparé pendant ton absence. À ton retour, ouvre **FileZilla** et glisse ces fichiers vers le serveur.

## ✨ Nouveaux fichiers (à uploader)

```
✅ a-propos.php             → /home/moussa/www/a-propos.php
✅ 404.php                  → /home/moussa/www/404.php
✅ mentions-legales.php     → /home/moussa/www/mentions-legales.php
```

## 🔧 Fichiers modifiés (à RE-uploader pour écraser)

```
🔄 .htaccess                → ajout ErrorDocument 404
🔄 partials/header.php      → favicon + meta Open Graph + lien "À propos"
🔄 partials/footer.php      → footer enrichi avec liens utiles
🔄 assets/css/style.css     → 4 nouvelles sections (about, 404, legal, footer)
🔄 README.md                → version pro pour GitHub (optionnel mais sympa)
```

## 🚀 Comment faire (rappel rapide)

1. Ouvre FileZilla, connecte-toi au serveur
2. **Côté gauche** : `/Users/moussawanecamara/Documents/Claude/Projects/EcoRide`
3. **Côté droit** : `/home/moussa/www`
4. Sélectionne tous les fichiers de la liste ci-dessus
5. Glisse-dépose vers le panneau droit
6. Quand FileZilla demande "Le fichier existe déjà", choisis **"Remplacer"**

## 🧪 Tests à faire après l'upload

Va sur **https://moussa.alwaysdata.net** :

1. ✅ Le footer en bas doit avoir maintenant **4 colonnes** (EcoRide / Découvrir / Légal / Code source)
2. ✅ Dans la nav, nouveau lien **"À propos"**
3. ✅ Tape **https://moussa.alwaysdata.net/aaa** (URL inexistante) → tu dois voir la **belle page 404 verte**
4. ✅ Onglet du navigateur : tu vois maintenant un **petit point vert** (favicon)
5. ✅ Si tu copies-colles ton URL dans Slack/Messages/Discord → joli preview avec titre + description (Open Graph)

## 📦 Ce qui a changé en détail

### `a-propos.php` — Page de présentation
Mission, valeurs, 4 cartes (écologique / économique / convivial / sécurisé), CTA d'inscription. Renforce la crédibilité du site.

### `404.php` — Page d'erreur stylée
Au lieu de la page 404 brute d'Apache, le visiteur voit une page EcoRide avec illustration de voiture inclinée et bouton retour.

### `mentions-legales.php` — Conformité
Page basique RGPD : qui édite, qui héberge, quelles données, comment les supprimer. Important pour tout site français.

### Favicon SVG inline
Une **seule ligne** dans le `<head>` : un SVG en `data:image/svg+xml,...` avec la goutte verte du logo. Pas de fichier à charger, ultra léger.

### Meta Open Graph + Twitter Card
Quand tu partages le lien sur Slack/Facebook/Discord/Twitter, ça affiche un beau preview avec titre, description, et type "website".

### Footer enrichi
4 colonnes : EcoRide (logo + tagline) / Découvrir (liens nav) / Légal (mentions légales, à propos) / Code source (lien GitHub).

### README professionnel
Le README de ton projet GitHub. Avec badges (PHP, SQLite, Apache, HTTPS), liste des fonctionnalités, architecture, mesures de sécurité, concepts SQL démontrés. Quand un recruteur tombe sur ton repo, c'est ça qu'il voit en premier.

## 💡 Pour la prochaine étape (si tu veux)

- Mettre le code sur **GitHub** : tu pourras le partager comme portfolio. Si tu veux, je t'aide à initialiser le repo et faire le premier push.
- **Refacto en POO** : transformer les fonctions en classes (User, Trajet, Reservation...). Plus moderne, plus aligné avec ce que demandent les entreprises.

Bonne soirée 🌱
