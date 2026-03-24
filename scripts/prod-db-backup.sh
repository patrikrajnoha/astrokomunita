#!/usr/bin/env sh
set -eu

ROOT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)"
cd "$ROOT_DIR"

if [ ! -f ".env" ]; then
  echo "Missing .env in project root. Copy .env.prod.example to .env first."
  exit 1
fi

mkdir -p backups
TIMESTAMP="$(date -u +%Y%m%dT%H%M%SZ)"
OUT_FILE="backups/mysql_${TIMESTAMP}.sql.gz"

set -a
. ./.env
set +a

if [ -z "${DB_USERNAME:-}" ] || [ -z "${DB_PASSWORD:-}" ] || [ -z "${DB_DATABASE:-}" ]; then
  echo "DB_USERNAME, DB_PASSWORD or DB_DATABASE is not set in .env."
  exit 1
fi

docker compose -f docker-compose.prod.yml exec -T mysql \
  sh -lc "mysqldump -u\"$DB_USERNAME\" -p\"$DB_PASSWORD\" \"$DB_DATABASE\"" \
  | gzip -9 > "$OUT_FILE"

echo "Backup created: $OUT_FILE"
