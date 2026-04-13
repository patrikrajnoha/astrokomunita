#!/usr/bin/env sh
set -eu

ROOT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)"
cd "$ROOT_DIR"

COMPOSE_FILE="${COMPOSE_FILE:-docker-compose.prod.yml}"
REMOTE="${DEPLOY_REMOTE:-origin}"
BRANCH="${DEPLOY_BRANCH:-main}"
API_HEALTH_URL="${API_HEALTH_URL:-https://api.astrokomunita.sk/api/health}"
WEB_HEALTH_URL="${WEB_HEALTH_URL:-https://astrokomunita.sk}"
MAX_RETRIES="${MAX_RETRIES:-24}"
SLEEP_SECONDS="${SLEEP_SECONDS:-5}"

if [ ! -f ".env" ]; then
  echo "Missing .env in project root. Copy .env.prod.example to .env first."
  exit 1
fi

if ! command -v docker >/dev/null 2>&1; then
  echo "Docker is required but not found in PATH."
  exit 1
fi

if ! command -v curl >/dev/null 2>&1; then
  echo "curl is required but not found in PATH."
  exit 1
fi

echo ">>> Fetching latest code from $REMOTE/$BRANCH"
git fetch "$REMOTE" "$BRANCH"
git pull --ff-only "$REMOTE" "$BRANCH"

echo ">>> Building and starting production stack"
docker compose -f "$COMPOSE_FILE" up -d --build --remove-orphans

echo ">>> Restarting backend-api to refresh FastCGI upstream mapping"
docker compose -f "$COMPOSE_FILE" restart backend-api

echo ">>> Waiting for API health check ($API_HEALTH_URL)"
attempt=1
api_status="000"
while [ "$attempt" -le "$MAX_RETRIES" ]; do
  api_status="$(curl -k -sS -o /dev/null -w '%{http_code}' "$API_HEALTH_URL" || true)"
  if [ "$api_status" = "200" ]; then
    break
  fi
  echo "API health attempt $attempt/$MAX_RETRIES returned $api_status"
  attempt=$((attempt + 1))
  sleep "$SLEEP_SECONDS"
done

if [ "$api_status" != "200" ]; then
  echo "API health check failed after $MAX_RETRIES attempts."
  docker compose -f "$COMPOSE_FILE" ps
  exit 1
fi

web_status="$(curl -k -sS -o /dev/null -w '%{http_code}' "$WEB_HEALTH_URL" || true)"
echo ">>> Frontend status ($WEB_HEALTH_URL): $web_status"

echo ">>> Deployment finished successfully"
docker compose -f "$COMPOSE_FILE" ps
