#!/bin/bash
# deploy_simplevoter.sh — Universelles Deploy-Skript
#
# Erkennt automatisch ob /var/www/simplevoter bereits ein Git-Repo ist:
#   - NEIN -> klont main-Branch frisch (Erstinstallation)
#   - JA   -> normales Update (git pull, etc.)
#
# Verwendung:
#   bash deploy_simplevoter.sh                — volles Update/Deploy
#   bash deploy_simplevoter.sh --no-npm       — ohne npm-Build
#   bash deploy_simplevoter.sh --no-migrate   — ohne Migrationen
#
set -e

APP_DIR="/var/www/simplevoter"
REPO_URL="git@github.com:tmerle24/simplevoter.git"
BRANCH="main"

SKIP_NPM=false
SKIP_MIGRATE=false
for arg in "$@"; do
    case $arg in
        --no-npm)     SKIP_NPM=true ;;
        --no-migrate) SKIP_MIGRATE=true ;;
    esac
done

# ── Erstinstallation: Repo klonen falls noch nicht vorhanden ────────
if [ ! -d "$APP_DIR/.git" ]; then
    echo "═══ Kein Git-Repo gefunden — klone $BRANCH nach $APP_DIR ═══"
    sudo mkdir -p "$APP_DIR"
    sudo chown "$(whoami):$(whoami)" "$APP_DIR"
    git clone --branch "$BRANCH" "$REPO_URL" "$APP_DIR"

    cd "$APP_DIR"

    if [ ! -f .env ]; then
        echo "═══ .env aus Vorlage erstellen — MUSS danach manuell befüllt werden! ═══"
        cp .env.example .env
        echo ""
        echo "⚠  STOPP: .env jetzt mit echten Werten befüllen (DB, MAIL, APP_URL),"
        echo "   dann dieses Skript erneut ausführen."
        exit 0
    fi

    FIRST_INSTALL=true
else
    cd "$APP_DIR"
    FIRST_INSTALL=false
fi

# Echte Existenzprüfung statt Git-Diff — entscheidend beim allerersten
# Deploy, wenn vendor/ bzw. node_modules/ noch nie installiert wurden
# (git diff gegen HEAD@{1} liefert dann nichts Verwertbares).
NEEDS_COMPOSER=false
[ ! -f "$APP_DIR/vendor/autoload.php" ] && NEEDS_COMPOSER=true

NEEDS_NPM=false
[ ! -d "$APP_DIR/node_modules" ] && NEEDS_NPM=true

# ── Ab hier: normaler Update-/Deploy-Flow ───────────────────────────

echo "═══ 1/9 Maintenance-Mode AN ═══"
if [ -f "$APP_DIR/vendor/autoload.php" ]; then
    php artisan down --retry=15 || true
else
    echo "  → vendor/ existiert noch nicht, artisan noch nicht nutzbar, übersprungen"
fi

if [ "$FIRST_INSTALL" = false ]; then
   echo "═══ 2/9 Git Pull ($BRANCH) ═══"
   OLD_COMMIT=$(git rev-parse HEAD)
   git fetch origin "$BRANCH"
   git reset --hard "origin/$BRANCH"
   NEW_COMMIT=$(git rev-parse HEAD)
else
   echo "═══ 2/9 Erstinstallation — kein Pull nötig (frisch geklont) ═══"
fi

echo "═══ 3/9 Composer ═══"
if [ "$NEEDS_COMPOSER" = true ] || [ "$FIRST_INSTALL" = true ] || git diff HEAD@{1} HEAD --name-only 2>/dev/null | grep -q "composer.lock"; then
    composer install --no-dev --optimize-autoloader
else
    echo "  → vendor/ vorhanden und composer.lock unverändert, übersprungen"
fi

# APP_KEY kann erst jetzt gesetzt werden (braucht vendor/autoload.php).
# Nur einmalig beim allerersten Deploy, falls .env noch keinen Key hat —
# NICHT bei jedem Deploy, sonst werden bestehende Sessions ungültig.
if ! grep -q "^APP_KEY=base64:" "$APP_DIR/.env" 2>/dev/null; then
    echo "  → APP_KEY fehlt noch, generiere einmalig"
    php artisan key:generate --force
fi

if [ "$SKIP_NPM" = false ]; then
    echo "═══ 4/9 npm install + build ═══"
    if [ "$NEEDS_NPM" = true ] || [ "$FIRST_INSTALL" = true ] || git diff HEAD@{1} HEAD --name-only 2>/dev/null | grep -qE "package-lock\.json|package\.json"; then
        npm ci
    fi
    export NODE_OPTIONS="--max-old-space-size=3072"
    npm run build
else
    echo "═══ 4/9 npm build übersprungen (--no-npm) ═══"
fi

if [ "$SKIP_MIGRATE" = false ]; then
    echo "═══ 5/9 Migrationen ═══"
    php artisan migrate --force
else
    echo "═══ 5/9 Migrationen übersprungen (--no-migrate) ═══"
fi

echo "═══ 6/9 Storage-Link ═══"
php artisan storage:link 2>/dev/null || true

echo "═══ 7/9 Caches neu aufbauen ═══"
php artisan config:clear
php artisan config:cache
php artisan route:cache
php artisan view:clear
php artisan view:cache
php artisan event:cache

echo "═══ 8/9 Verzeichnis-Rechte ═══"
sudo chown -R "$(whoami):www-data" "$APP_DIR"
sudo find "$APP_DIR/storage" -type d -exec chmod 775 {} \;
sudo find "$APP_DIR/storage" -type f -exec chmod 664 {} \;
sudo find "$APP_DIR/bootstrap/cache" -type d -exec chmod 775 {} \;
sudo find "$APP_DIR/bootstrap/cache" -type f -exec chmod 664 {} \;

echo "═══ 9/9 Queue-Worker neu starten + Maintenance-Mode AUS ═══"
sudo supervisorctl restart simplevoter-worker:* 2>/dev/null || echo "  → Supervisor-Worker noch nicht eingerichtet, übersprungen"
php artisan up

echo ""
if [ "$FIRST_INSTALL" = true ]; then
    echo "✅ Erstinstallation abgeschlossen."
    echo "   Nächste Schritte: Nginx-Vhost + SSL einrichten, Supervisor-Worker konfigurieren."
else
    echo "✅ Deploy fertig."
fi
