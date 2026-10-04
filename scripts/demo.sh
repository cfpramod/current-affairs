#!/usr/bin/env bash
# demo: one command to rehearse the Smartflow demo locally.
# Prepares the data engine and the Laravel app if needed, then serves both.
#
#   bash scripts/demo.sh            set up what is missing, then serve
#   bash scripts/demo.sh --reset    also clear batch decisions (between rehearsals)
#   bash scripts/demo.sh --fresh    rebuild engine databases and reseed the app
#   bash scripts/demo.sh --setup    prepare only, don't serve
#
# Engine: Python via pixi when installed, otherwise a uv venv in .runtime/venv.
# App: served by Laravel Herd when it serves this folder, otherwise on port 8000.
# The engine and app databases stay separate; Laravel never migrates the engine's.

set -euo pipefail

FRESH=0 RESET=0 SERVE=1
for arg in "$@"; do
    case "$arg" in
        --fresh) FRESH=1 ;;
        --reset) RESET=1 ;;
        --setup) SERVE=0 ;;
        -h|--help) sed -n '2,12p' "$0"; exit 0 ;;
        *) echo "Unknown option: $arg" >&2; exit 2 ;;
    esac
done

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
APP="$ROOT/dashboard"
RUNTIME="$ROOT/.runtime"
ENGINE_DB="$RUNTIME/current-affairs.sqlite3"
ACTION_DB="$RUNTIME/batch-action-demo.sqlite3"
ENGINE_PORT=8002
AS_OF="2026-10-03T12:00:00Z"

step() { printf '\n\033[1;36m==> %s\033[0m\n' "$*"; }

cd "$ROOT"
mkdir -p "$RUNTIME"

# --- Python for the data engine ---------------------------------------------
if command -v pixi >/dev/null 2>&1; then
    PY=(pixi run python)
else
    command -v uv >/dev/null 2>&1 || { echo "Install pixi or uv first (brew install uv)." >&2; exit 1; }
    if [ ! -x "$RUNTIME/venv/bin/python" ]; then
        step "Creating Python 3.12 environment in .runtime/venv"
        uv venv -q --python 3.12 "$RUNTIME/venv"
        VIRTUAL_ENV="$RUNTIME/venv" uv pip install -q -e "pipeline[api]"
    fi
    PY=("$RUNTIME/venv/bin/python")
fi

# --- Engine databases --------------------------------------------------------
if [ "$FRESH" = 1 ]; then
    step "Removing engine databases for a fresh build"
    rm -f "$ENGINE_DB" "$ENGINE_DB".* "$ACTION_DB" "$ACTION_DB".*
fi

if [ ! -f "$ENGINE_DB" ]; then
    step "Building engine database (observations, operations, batch assessments)"
    "${PY[@]}" -m baselhack.data_engine init
    "${PY[@]}" -m baselhack.data_engine import-cache
    "${PY[@]}" -m baselhack.data_engine seed --output output/operations-demo.json
    "${PY[@]}" -m baselhack.integration analyze
    "${PY[@]}" -m baselhack.integration configure-batches
    for batch in 001 002 003 004; do
        "${PY[@]}" -m baselhack.integration analyze-batch \
            --dataset-id operations-demo-v1 \
            --batch-id "operations-demo-v1-batch-$batch" \
            --as-of "$AS_OF" >/dev/null
    done
fi

if [ ! -f "$ACTION_DB" ]; then
    step "Building action demo database"
    "${PY[@]}" -m baselhack.batch_demo
fi

# --- Laravel app -------------------------------------------------------------
cd "$APP"

if [ ! -f .env ]; then
    step "Creating dashboard/.env from env.example"
    cp env.example .env
fi
grep -q '^DATA_ENGINE_URL=' .env || printf '\nDATA_ENGINE_URL=http://127.0.0.1:%s\n' "$ENGINE_PORT" >> .env

if [ ! -d vendor ]; then
    step "Installing PHP dependencies"
    composer install --no-interaction --prefer-dist
fi
grep -q '^APP_KEY=base64' .env || php artisan key:generate --no-interaction

if [ "$FRESH" = 1 ]; then
    step "Reseeding the app database"
    php artisan migrate:fresh --seed --force --no-interaction
else
    php artisan migrate --force --no-interaction
    if [ "$(php artisan tinker --execute 'echo App\Models\User::query()->count();' 2>/dev/null | tail -1)" = "0" ]; then
        step "Seeding demo users and shipments"
        php artisan db:seed --force --no-interaction
    fi
fi

if [ "$RESET" = 1 ]; then
    step "Clearing batch decisions"
    php artisan demo:reset
fi

if [ ! -d node_modules ]; then
    step "Installing frontend dependencies"
    npm ci
fi
if [ "$FRESH" = 1 ] || [ ! -f public/build/manifest.json ]; then
    step "Building frontend assets"
    npm run build
fi
rm -f public/hot
php artisan config:clear >/dev/null

if [ "$SERVE" = 0 ]; then
    step "Ready. Run without --setup to serve."
    exit 0
fi

# --- Serve -------------------------------------------------------------------
cd "$ROOT"
PIDS=()
cleanup() { [ "${#PIDS[@]}" -gt 0 ] && kill "${PIDS[@]}" 2>/dev/null || true; }
trap cleanup EXIT INT TERM

if curl -fs "http://127.0.0.1:$ENGINE_PORT/api/integration/batch-assessments" >/dev/null 2>&1; then
    step "Engine API already running on port $ENGINE_PORT"
else
    step "Starting engine API on port $ENGINE_PORT"
    "${PY[@]}" -m baselhack.integration serve --port "$ENGINE_PORT" &
    PIDS+=($!)
    for _ in $(seq 1 30); do
        curl -fs "http://127.0.0.1:$ENGINE_PORT/api/integration/batch-assessments" >/dev/null 2>&1 && break
        sleep 0.5
    done
fi

APP_URL=""
if command -v herd >/dev/null 2>&1 && herd links 2>/dev/null | grep -q "$APP"; then
    APP_URL="$(herd links 2>/dev/null | grep "$APP" | grep -o 'https\?://[^ |]*' | head -1)"
fi

cat <<EOF

Smartflow demo
  Logins (password: secret): operator@example.com · qa@example.com · logistics@example.com
  Hot day: Batches → Batch 002 → operator chooses Quarantine → QA accepts from the overview
  Between rehearsals: bash scripts/demo.sh --reset
EOF

if [ -n "$APP_URL" ]; then
    printf '  App: %s (served by Herd)\n' "$APP_URL"
    if [ "${#PIDS[@]}" -gt 0 ]; then
        printf '\nPress Ctrl+C to stop the engine.\n'
        wait
    fi
else
    printf '  App: http://127.0.0.1:8000\n\nPress Ctrl+C to stop.\n'
    (cd "$APP" && php artisan serve --host 127.0.0.1 --port 8000) &
    PIDS+=($!)
    wait
fi
