#!/usr/bin/env bash
# demo: one command to rehearse the Smartflow demo locally.
# Prepares the data engine and the Laravel app if needed, then serves both.
#
#   bash scripts/demo.sh            set up what is missing, then serve
#   bash scripts/demo.sh --reset    also clear batch decisions (between rehearsals)
#   bash scripts/demo.sh --fresh    rebuild engine databases and reseed the app
#   bash scripts/demo.sh --setup    prepare only, don't serve
#   bash scripts/demo.sh --no-ai    skip AI/Codex briefings; pages show the evidence template
#   bash scripts/demo.sh --case=hot       heat evidence and QA workflow
#   bash scripts/demo.sh --case=buffer    computed safety-stock replay
#   bash scripts/demo.sh --case=expedite  computed logistics replay (default)
#
# Engine: Python via pixi when installed, otherwise a uv venv in .runtime/venv.
# App: served by Laravel Herd when it serves this folder, otherwise on port 8000
# (SMARTFLOW_APP_PORT overrides it, like SMARTFLOW_ENGINE_PORT for the engine).
# The engine and app databases stay separate; Laravel never migrates the engine's.

set -euo pipefail

FRESH=0 RESET=0 SERVE=1 BRIEF=1 DEMO_CASE=expedite
for arg in "$@"; do
    case "$arg" in
        --fresh) FRESH=1 ;;
        --reset) RESET=1 ;;
        --setup) SERVE=0 ;;
        --no-ai) BRIEF=0 ;;
        --case=hot|--case=buffer|--case=expedite) DEMO_CASE="${arg#--case=}" ;;
        -h|--help) sed -n '2,13p' "$0"; exit 0 ;;
        *) echo "Unknown option: $arg" >&2; exit 2 ;;
    esac
done

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
APP="$ROOT/dashboard"
RUNTIME="$ROOT/.runtime"
ENGINE_DB="$RUNTIME/current-affairs.sqlite3"
ACTION_DB="$RUNTIME/batch-action-demo.sqlite3"
ENGINE_PORT="${SMARTFLOW_ENGINE_PORT:-8002}"
APP_PORT="${SMARTFLOW_APP_PORT:-8000}"
AS_OF="2026-10-03T12:00:00Z"
SELECTED_DB="$ENGINE_DB"
DATASET_ID=operations-demo-v1
BATCH_ID=operations-demo-v1-batch-002
CASE_LABEL="Heat evidence → operator Quarantine → QA review"
if [ "$DEMO_CASE" != hot ]; then
    SELECTED_DB="$ACTION_DB"
    DATASET_ID=operations-action-demo-v1
    if [ "$DEMO_CASE" = buffer ]; then
        BATCH_ID=operations-action-demo-v1-batch-001
        CASE_LABEL="Historical replay → computed BUFFER → operator decision"
    else
        BATCH_ID=operations-action-demo-v1-batch-002
        CASE_LABEL="Historical replay → late shipment, overheated lot → computed QUARANTINE → QA, then logistics"
    fi
fi

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
    "${PY[@]}" -m baselhack.data_engine seed --output "$RUNTIME/operations-demo.json"
    "${PY[@]}" -m baselhack.integration analyze
fi

# An existing engine database may predate the batch layer. Preparing these
# immutable records is idempotent; file existence alone does not prove readiness.
step "Ensuring the four stored batch assessments are ready"
"${PY[@]}" -m baselhack.integration configure-batches
for batch in 001 002 003 004; do
    "${PY[@]}" -m baselhack.integration analyze-batch \
        --dataset-id operations-demo-v1 \
        --batch-id "operations-demo-v1-batch-$batch" \
        --as-of "$AS_OF" >/dev/null
done

if [ ! -f "$ACTION_DB" ]; then
    step "Building action demo database"
    "${PY[@]}" -m baselhack.batch_demo --output-dir "$RUNTIME"
fi

# --- Laravel app -------------------------------------------------------------
cd "$APP"

if [ ! -f .env ]; then
    step "Creating dashboard/.env from env.example"
    cp env.example .env
fi
# artisan serve and Herd read .env, not this shell, so point it at this run's engine.
if grep -q '^DATA_ENGINE_URL=' .env; then
    sed -i.bak "s|^DATA_ENGINE_URL=.*|DATA_ENGINE_URL=http://127.0.0.1:$ENGINE_PORT|" .env && rm -f .env.bak
else
    printf '\nDATA_ENGINE_URL=http://127.0.0.1:%s\n' "$ENGINE_PORT" >> .env
fi
# Typing "." into an empty decision field types the prepared demo text.
if grep -q '^SMARTFLOW_DEMO_AUTOFILL=' .env; then
    sed -i.bak 's/^SMARTFLOW_DEMO_AUTOFILL=.*/SMARTFLOW_DEMO_AUTOFILL=true/' .env && rm -f .env.bak
else
    printf '\nSMARTFLOW_DEMO_AUTOFILL=true\n' >> .env
fi

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
# pixi and artisan serve start child processes; stop the whole tree so no
# orphaned engine keeps its port. Children first, before they are reparented.
kill_tree() {
    local child
    for child in $(pgrep -P "$1" 2>/dev/null); do kill_tree "$child"; done
    kill "$1" 2>/dev/null || true
}
cleanup() {
    local pid
    for pid in ${PIDS[@]+"${PIDS[@]}"}; do kill_tree "$pid"; done
}
trap cleanup EXIT
trap 'exit 129' HUP
trap 'exit 130' INT
trap 'exit 143' TERM

port_in_use() { (exec 3<>"/dev/tcp/127.0.0.1/$1") 2>/dev/null; }

APP_URL=""
if command -v herd >/dev/null 2>&1 && herd links 2>/dev/null | grep -q "$APP"; then
    APP_URL="$(herd links 2>/dev/null | grep "$APP" | grep -o 'https\?://[^ |]*' | head -1)"
fi
if [ -z "$APP_URL" ] && port_in_use "$APP_PORT"; then
    echo "Port $APP_PORT is already in use. Stop the other server (lsof -i :$APP_PORT shows it) or set SMARTFLOW_APP_PORT, then run this again." >&2
    exit 1
fi

if curl -fs "http://127.0.0.1:$ENGINE_PORT/api/integration/batch-assessments" >/dev/null 2>&1; then
    if ! curl -fs "http://127.0.0.1:$ENGINE_PORT/api/integration/batch-assessments?dataset_id=$DATASET_ID" | "${PY[@]}" -c 'import json,sys; sys.exit(0 if json.load(sys.stdin) else 1)'; then
        echo "Port $ENGINE_PORT is serving a different dataset. Stop that API or choose a free SMARTFLOW_ENGINE_PORT before switching cases." >&2
        exit 1
    fi
    step "Engine API already running on port $ENGINE_PORT"
else
    step "Starting engine API on port $ENGINE_PORT"
    "${PY[@]}" -m baselhack.integration serve --port "$ENGINE_PORT" --database "$SELECTED_DB" &
    ENGINE_PID=$!
    PIDS+=("$ENGINE_PID")
    ENGINE_UP=0
    for _ in $(seq 1 30); do
        if curl -fs "http://127.0.0.1:$ENGINE_PORT/api/integration/batch-assessments" >/dev/null 2>&1; then
            ENGINE_UP=1
            break
        fi
        kill -0 "$ENGINE_PID" 2>/dev/null || break
        sleep 0.5
    done
    if [ "$ENGINE_UP" = 0 ]; then
        if kill -0 "$ENGINE_PID" 2>/dev/null; then
            echo "The engine API did not answer on port $ENGINE_PORT within 15 seconds. Check the output above, then run this again." >&2
        else
            echo "The engine API stopped while starting; see the error above (is port $ENGINE_PORT taken?)." >&2
        fi
        exit 1
    fi
fi

if [ "$BRIEF" = 1 ]; then
    step "Preparing cached batch briefings (API, then local Codex, then template)"
    (cd "$APP" && DATA_ENGINE_URL="http://127.0.0.1:$ENGINE_PORT" SMARTFLOW_CODEX_BRIEFINGS=true php artisan batches:brief) || echo "Pages will show the evidence template until briefings are prepared (php artisan batches:brief)."
else
    step "Skipping AI briefings (--no-ai): pages show the evidence template unless a briefing was prepared earlier"
fi

cat <<EOF

Smartflow demo
  Logins (password: secret): operator@example.com · qa@example.com · logistics@example.com
  Selected case: $CASE_LABEL
  Dataset: $DATASET_ID
  Operator and approver: open /batches/$BATCH_ID in separate signed-in browser profiles
  After acceptance or denial: refresh the operator tab to show the recorded result
  Shortcut: type "." in an empty reason or next-action field to type the prepared text
  Between rehearsals: bash scripts/demo.sh --case=$DEMO_CASE --reset
EOF

if [ -n "$APP_URL" ]; then
    printf '  App: %s (served by Herd)\n' "$APP_URL"
    printf '  Direct case: %s/batches/%s\n' "${APP_URL%/}" "$BATCH_ID"
    if [ "${#PIDS[@]}" -gt 0 ]; then
        printf '\nPress Ctrl+C to stop the engine.\n'
        wait
    fi
else
    printf '  App: http://127.0.0.1:%s\n\nPress Ctrl+C to stop.\n' "$APP_PORT"
    printf '  Direct case: http://127.0.0.1:%s/batches/%s\n' "$APP_PORT" "$BATCH_ID"
    (cd "$APP" && DATA_ENGINE_URL="http://127.0.0.1:$ENGINE_PORT" PHP_CLI_SERVER_WORKERS=4 php artisan serve --no-reload --host 127.0.0.1 --port "$APP_PORT") &
    PIDS+=($!)
    wait
fi
