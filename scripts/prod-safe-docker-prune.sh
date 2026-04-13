#!/usr/bin/env bash
set -euo pipefail

TARGET_FS="${TARGET_FS:-/}"
DISK_THRESHOLD_PERCENT="${DISK_THRESHOLD_PERCENT:-80}"
BUILDER_UNTIL="${BUILDER_UNTIL:-720h}"
IMAGE_UNTIL="${IMAGE_UNTIL:-720h}"
CONTAINER_UNTIL="${CONTAINER_UNTIL:-720h}"

DRY_RUN=false
if [[ "${1:-}" == "--dry-run" ]]; then
  DRY_RUN=true
fi

run_cmd() {
  echo "+ $*"
  if [[ "$DRY_RUN" == false ]]; then
    "$@"
  fi
}

now_utc() {
  date -u +"%Y-%m-%dT%H:%M:%SZ"
}

usage_percent_raw="$(df --output=pcent "$TARGET_FS" | tail -n 1 | tr -dc '0-9')"
if [[ -z "$usage_percent_raw" ]]; then
  echo "[$(now_utc)] ERROR: unable to detect disk usage for $TARGET_FS"
  exit 1
fi

usage_percent="$usage_percent_raw"

echo "[$(now_utc)] start safe docker prune (dry_run=$DRY_RUN, fs=$TARGET_FS, usage=${usage_percent}%, threshold=${DISK_THRESHOLD_PERCENT}%)"

if (( usage_percent < DISK_THRESHOLD_PERCENT )); then
  echo "[$(now_utc)] skip: usage below threshold"
  exit 0
fi

echo "[$(now_utc)] before:"
run_cmd docker system df
run_cmd df -h "$TARGET_FS"

# Safety rule: never run volume prune here. Volumes contain persistent data (DB, uploads).
run_cmd docker builder prune -af --filter "until=${BUILDER_UNTIL}"
run_cmd docker image prune -af --filter "until=${IMAGE_UNTIL}"
run_cmd docker container prune -f --filter "until=${CONTAINER_UNTIL}"

echo "[$(now_utc)] after:"
run_cmd docker system df
run_cmd df -h "$TARGET_FS"

echo "[$(now_utc)] done"
