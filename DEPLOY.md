# 🚀 Guide de déploiement — EcoRide sur AlwaysData

Ce guide te montre comment mettre EcoRide en ligne sur **AlwaysData (gratuit)**.

---

## ✅ Pré-requis

- [x] Compte AlwaysData créé
- [x] Sous-domaine activé (par défaut : `moussa.alwaysdata.net`)
- [ ] Client SFTP : on utilisera **FileZilla** (gratuit, multi-OS) → https://filezilla-project.org

---

## 📂 Étape 1 — Récupérer tes identifiants SFTP

1. Connecte-toi sur **https://admin.alwaysdata.com**
2. Dans le menu de gauche, clique sur **"Comptes FTP"** (ou directement **"SSH"**)
3. Tu vois ton compte par défaut. Note ces 4 infos :
   - **Hôte** : `ssh-moussa.alwaysdata.net` (souvent juste `ftp-moussa.alwaysdata.net`)
   - **Port** : 22 (SFTP)
   - **Nom d'utilisateur** : ton nom de compte
   - **Mot de passe** : celui que tu as choisi à l'inscription

---

## 🔧 Étape 2 — Configurer FileZilla

1. Télécharge et installe **FileZilla Client**
2. Lance-le
3. En haut, dans la barre rapide, remplis :
   - **Hôte** : `sftp://ssh-moussa.alwaysdata.net` (le `sftp://` au début est important)
   - **Identifiant** : ton nom d'utilisateur
   - **Mot de passe** : ton mot de passe
   - **Port** : 22
4. Clique **"Connexion rapide"**
5. Accepte le certificat SSH (clé publique du serveur)

---

## 🚛 Étape 3 — Uploader le code

1. Côté gauche de FileZilla = **ton ordi local**
2. Côté droit = **le serveur AlwaysData**

3. À gauche, navigue vers ton dossier `EcoRide/`
4. À droite, navigue vers le dossier `www/` (c'est le dossier public servi par le serveur web)
5. **Sélectionne TOUS les fichiers du projet à gauche** (Cmd+A)
6. **Glisse-dépose** vers le côté droit (dans `www/`)
7. Attends que tout soit uploadé (quelques secondes)

### ⚠️ Important : ne pas uploader

Si tu vois ces fichiers/dossiers, **NE LES UPLOAD PAS** (ou supprime-les après) :
- `BRIEF.md`, `DEPLOY.md`, `README.md` (optionnel — pas dangereux mais pas utile)
- `data/ecoride.sqlite` ← La BDD locale ! Si tu uploades ça, ça écrasera la prod (à transférer **une seule fois** la première fois)
- `data/php-errors.log` ← log des erreurs (sera créé sur le serveur tout seul)

### ✅ À uploader absolument

Tous les fichiers `.php`, `.htaccess`, le dossier `assets/`, `lib/`, `partials/`, `api/`, et le **fichier vide `data/.htaccess`**.

Pour le dossier `data/`, **uploade juste les `.json` initiaux** (`trajets.json`, `users.json`). La BDD SQLite sera créée automatiquement au premier accès grâce à nos migrations.

---

## 🌐 Étape 4 — Configurer le site dans AlwaysData

1. Sur l'admin AlwaysData, va dans **"Sites"** (menu de gauche)
2. Clique sur **"Ajouter un site"**
3. Configure :
   - **Type** : Site PHP
   - **Adresse** : `moussa.alwaysdata.net` (devrait être pré-rempli)
   - **Type d'hôte** : Apache + PHP
   - **Version PHP** : 8.2 (ou 8.3 / 8.4)
   - **Dossier racine** : `/www` (par défaut)
4. Sauvegarde

---

## 🔒 Étape 5 — Activer HTTPS

1. Dans **Sites** → ton site → onglet **"SSL"**
2. Coche **"Forcer HTTPS"**
3. Clique **"Activer le certificat Let's Encrypt"** (gratuit)
4. Attends quelques minutes que le certificat soit généré

---

## 🧪 Étape 6 — Tester

1. Ouvre dans ton navigateur : **https://moussa.alwaysdata.net**
2. Tu devrais voir l'accueil EcoRide avec le 🔒 vert dans la barre d'adresse
3. Vérifie :
   - L'inscription fonctionne
   - La connexion fonctionne
   - Les trajets s'affichent

### En cas de problème

- **500 Internal Server Error** : va dans **Logs** → **error_log**, lis l'erreur
- **404 sur les CSS/JS** : vérifie que le dossier `assets/` est bien uploadé
- **"Database is locked"** : permissions du dossier `data/` → onglet SFTP → clic droit sur `data/` → **Permissions** → mets `755` ou `775`

---

## 🎯 Bonus — Mettre l'URL sur ton CV

Après ton déploiement, tu peux mettre :

> **EcoRide** — Site fullstack de covoiturage écologique. PHP, SQLite, déployé sur AlwaysData avec HTTPS et migrations idempotentes. → [https://moussa.alwaysdata.net](#)

---

## 🔄 Pour mettre à jour plus tard

Quand tu modifies un fichier en local :
1. Édite le fichier
2. Ouvre FileZilla
3. Upload le fichier modifié vers le serveur (glisse-dépose)
4. Recharge la page en prod pour vérifier

C'est ça le **déploiement manuel**. En vrai en entreprise, on automatise ça avec un système de **CI/CD** (GitHub Actions, GitLab CI). Mais pour ton projet, manuel c'est très bien.
