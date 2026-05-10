# EcoRide — Brief du projet

## 🌐 EN LIGNE : https://moussa.alwaysdata.net

Hébergé sur AlwaysData (gratuit) avec HTTPS via Let's Encrypt.
Upload via SFTP avec FileZilla.

## Le projet
Site web de **covoiturage écologique** avec mise en avant du CO₂ économisé.

## Stack technique (validée et en place)
- **Frontend** : HTML / CSS / JS vanilla
- **Backend** : PHP (vanilla, fonctions, partials)
- **Base de données** : SQLite via PDO (data/ecoride.sqlite)
- **Serveur local** : Laravel Herd → http://ecoride.test

## Architecture
```
EcoRide/
├── index.php                # Accueil + recherche server-side
├── trajet.php               # Détail d'un trajet + bouton réserver
├── publier.php              # Publier un nouveau trajet (auth required)
├── modifier-trajet.php      # Éditer un trajet existant (auth + propriétaire)
├── reserver.php             # Endpoint POST de réservation
├── inscription.php          # Inscription
├── connexion.php            # Connexion
├── deconnexion.php          # Déconnexion (POST + CSRF)
├── profil.php               # Modifier nom/email/mot de passe
├── mes-trajets.php          # Liste de mes trajets + passagers + édition/suppression
├── mes-reservations.php     # Mes réservations + annulation
│
├── lib/
│   ├── init.php             # Démarre la session, charge tout
│   ├── db.php               # PDO + auto-init du schéma + seed JSON
│   ├── auth.php             # register/login/logout/currentUser/updateProfile/changePassword
│   ├── trajets.php          # load/find/add/update/delete/searchTrajets/getByUser/getPassengers
│   ├── reservations.php     # add/cancel/getByUser + transactions SQL
│   └── csrf.php             # csrfToken / csrfVerify / csrfField
│
├── partials/
│   ├── header.php           # Header partagé (nav dynamique selon login)
│   └── footer.php           # Footer
│
├── api/
│   └── trajets.php          # Mini-API JSON (peu utilisé maintenant)
│
├── data/
│   ├── ecoride.sqlite       # BDD principale
│   ├── trajets.json         # données initiales (importées au 1er run)
│   └── users.json           # données initiales
│
└── assets/
    ├── css/style.css        # Tous les styles
    └── js/script.js         # Filtres prix/véhicule en sidebar
```

## Fonctionnalités en place
- ✅ Page d'accueil avec hero + barre de recherche server-side
- ✅ Recherche par départ / arrivée / date (URL partageable)
- ✅ Filtres client-side (prix max + type de véhicule)
- ✅ Page de détail d'un trajet avec carte conducteur, véhicule, préférences, impact CO₂
- ✅ Inscription / Connexion sécurisées (bcrypt, sessions, regenerate_id)
- ✅ Profil utilisateur (modifier infos + changer mot de passe)
- ✅ Publier un trajet (formulaire complet avec validation server-side)
- ✅ Modifier un trajet (UPDATE avec garde-fou propriétaire)
- ✅ Supprimer un trajet (cascade sur les réservations)
- ✅ Mes trajets (avec liste des passagers ayant réservé via JOIN)
- ✅ Réserver un trajet (transaction SQL : INSERT + UPDATE places)
- ✅ Mes réservations (avec annulation + restitution de la place)
- ✅ Protection CSRF sur toutes les actions sensibles
- ✅ POST/Redirect/GET partout
- ✅ Migration JSON → SQLite automatique au 1er lancement

## Concepts maîtrisés (peuvent être listés en entretien)
- PDO + requêtes préparées (anti-injection SQL)
- Transactions SQL (atomicité)
- JOIN entre tables
- Foreign keys + ON DELETE CASCADE
- Index pour la perf
- password_hash / password_verify (bcrypt)
- Sessions PHP + session_regenerate_id (anti-fixation)
- Token CSRF + hash_equals (anti-timing-attack)
- Validation côté serveur + ré-affichage avec messages
- Architecture en couches (lib/ partials/ api/)
- Defense in depth (vérif user_id en SQL en plus du PHP)

## Pistes pour les prochaines sessions
- Système d'**avis / note** post-trajet (passagers notent le conducteur)
- **Messagerie** entre conducteur et passagers
- Page **admin** (modérer, voir tous les users)
- **Édition du profil avancée** (avatar uploadable, bio)
- **Notifications** (emails de confirmation de réservation)
- **Migration MySQL** (changer 1 ligne dans lib/db.php)
- **Déploiement** sur OVH / o2switch / Vercel-like
- **Refacto en POO** (classes Trajet, User, Reservation)

## Pour reprendre
À la prochaine session, dis simplement :
> *"Salut, on reprend EcoRide. Lis le BRIEF.md. On attaque [feature]."*
