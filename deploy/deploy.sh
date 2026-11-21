#!/bin/bash
# =============================================================
# deploy.sh — Met le site à jour depuis GitHub (branche main)
# =============================================================
# Lancé toutes les 5 minutes par une tâche planifiée AlwaysData.
#  1. récupère la dernière version du dépôt GitHub (public)
#  2. s'il y a du nouveau : sauvegarde www, puis copie les fichiers
#  3. ne touche JAMAIS au dossier data/ (base de données du site)
#
# Lancer à la main :  bash ~/deploy.sh --force
# Historique       :  ~/deploy.log
# =============================================================
set -euo pipefail

REPO="https://github.com/Moussa-data/covoiturage-ecoride.git"
SRC="$HOME/ecoride-repo"     # copie locale du dépôt
WWW="$HOME/www"              # dossier du site en ligne
LOG="$HOME/deploy.log"
SAUVEGARDES="$HOME/sauvegardes"

log() { echo "$(date '+%Y-%m-%d %H:%M:%S') $*" >> "$LOG"; }

PREMIERE_FOIS=0
if [ ! -d "$SRC/.git" ]; then
  git clone --quiet --branch main "$REPO" "$SRC"
  PREMIERE_FOIS=1
fi

cd "$SRC"
git fetch --quiet origin main
ACTUEL=$(git rev-parse HEAD)
NOUVEAU=$(git rev-parse origin/main)

# Rien de nouveau → on s'arrête (sauf première fois ou --force)
if [ "$ACTUEL" = "$NOUVEAU" ] && [ "$PREMIERE_FOIS" = 0 ] && [ "${1:-}" != "--force" ]; then
  exit 0
fi

git reset --quiet --hard origin/main

# Sauvegarde complète de www (base comprise), on garde les 5 dernières
mkdir -p "$SAUVEGARDES"
tar czf "$SAUVEGARDES/www-$(date +%Y%m%d-%H%M%S).tar.gz" -C "$HOME" www
ls -1t "$SAUVEGARDES"/www-*.tar.gz | tail -n +6 | xargs -r rm -f

# Ménage unique (dossiers en double envoyés par erreur, validé par Moussa le 21/11/2026)
if [ "$PREMIERE_FOIS" = 1 ]; then
  rm -rf "$HOME/1-EN-PREMIER" "$HOME/2-ENSUITE" "$HOME/3-EN-DERNIER" \
         "$HOME/LISEZ-MOI.txt" "$HOME/.DS_Store" \
         "$WWW/1-EN-PREMIER" "$WWW/2-ENSUITE" "$WWW/3-EN-DERNIER" \
         "$WWW/ecoride-v3" "$WWW/ecoride-v5" "$WWW/style.css"
  log "ménage des dossiers en double effectué"
fi

# Copie des fichiers du site (sans data/, sans les fichiers de travail)
if command -v rsync >/dev/null 2>&1; then
  rsync -a --exclude '/.git' --exclude '/.github' --exclude '/data' --exclude '/docs' \
           --exclude '/deploy' --exclude '*.md' --exclude 'ChatGPT Image*' --exclude '/.gitignore' \
           ./ "$WWW/"
else
  tar cf - --exclude './.git' --exclude './.github' --exclude './data' --exclude './docs' \
           --exclude './deploy' --exclude '*.md' --exclude './ChatGPT Image*' --exclude './.gitignore' \
           . | tar xf - -C "$WWW"
fi

log "déployé $(git rev-parse --short HEAD) : $(git log -1 --pretty=%s)"
