#!/usr/bin/env sh
set -eu

if [ $# -ne 1 ]; then
  echo "Usage: ./scripts/prod-db-restore.sh <backup-file.sql.gz|backup-file.sql>"
  exit 1
fi

BACKUP_FILE="$1"
if [ ! -f "$BACKUP_FILE" ]; then
  echo "Backup file not found: $BACKUP_FILE"
  exit 1
fi

ROOT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)"
cd "$ROOT_DIR"

if [ ! -f ".env" ]; then
  echo "Missing .env in project root. Copy .env.prod.example to .env first."
  exit 1
fi

set -a
. ./.env
set +a

if [ -z "${DB_USERNAME:-}" ] || [ -z "${DB_PASSWORD:-}" ] || [ -z "${DB_DATABASE:-}" ]; then
  echo "DB_USERNAME, DB_PASSWORD or DB_DATABASE is not set in .env."
  exit 1
fi

if [ "${CONFIRM_RESTORE:-}" != "YES" ]; then
  echo "Set CONFIRM_RESTORE=YES to confirm destructive restore."
  exit 1
fi

if printf '%s' "$BACKUP_FILE" | grep -qE '\.gz$'; then
  gzip -dc "$BACKUP_FILE" | docker compose -f docker-compose.prod.yml exec -T mysql \
    sh -lc "mysql -u\"$DB_USERNAME\" -p\"$DB_PASSWORD\" \"$DB_DATABASE\""
else
  cat "$BACKUP_FILE" | docker compose -f docker-compose.prod.yml exec -T mysql \
    sh -lc "mysql -u\"$DB_USERNAME\" -p\"$DB_PASSWORD\" \"$DB_DATABASE\""
fi

echo "Restore completed from: $BACKUP_FILE"
