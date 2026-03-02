#!/usr/bin/env bash

set -euo pipefail

access_key_id="${R2_ACCESS_KEY_ID:-}"
secret_access_key="${R2_SECRET_ACCESS_KEY:-}"

while [[ $# -gt 0 ]]; do
  case "$1" in
    --access-key-id)
      access_key_id="${2:-}"
      shift 2
      ;;
    --secret-access-key)
      secret_access_key="${2:-}"
      shift 2
      ;;
    -h|--help)
      cat <<'EOF'
pouzitie:
  ./backend/scripts/r2_smoketest.sh --access-key-id __R2_ACCESS_KEY_ID__ --secret-access-key __R2_SECRET_ACCESS_KEY__

alternativne:
  R2_ACCESS_KEY_ID=__R2_ACCESS_KEY_ID__ R2_SECRET_ACCESS_KEY=__R2_SECRET_ACCESS_KEY__ ./backend/scripts/r2_smoketest.sh
EOF
      exit 0
      ;;
    *)
      echo "neznamy argument: $1" >&2
      exit 1
      ;;
  esac
done

if [[ -z "$access_key_id" ]]; then
  read -r -p "zadajte r2 access key id: " access_key_id
fi

if [[ -z "$secret_access_key" ]]; then
  read -r -s -p "zadajte r2 secret access key: " secret_access_key
  echo
fi

if [[ -z "$access_key_id" || -z "$secret_access_key" ]]; then
  echo "access key id aj secret access key su povinne" >&2
  exit 1
fi

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
backend_dir="$(cd "$script_dir/.." && pwd)"

restore_files_disk="${FILES_DISK-}"
restore_files_private_disk="${FILES_PRIVATE_DISK-}"
restore_aws_access_key_id="${AWS_ACCESS_KEY_ID-}"
restore_aws_secret_access_key="${AWS_SECRET_ACCESS_KEY-}"
restore_aws_default_region="${AWS_DEFAULT_REGION-}"
restore_aws_bucket="${AWS_BUCKET-}"
restore_aws_url="${AWS_URL-}"
restore_aws_endpoint="${AWS_ENDPOINT-}"
restore_aws_use_path_style_endpoint="${AWS_USE_PATH_STYLE_ENDPOINT-}"
restore_r2_private_bucket="${R2_PRIVATE_BUCKET-}"

had_files_disk="${FILES_DISK+x}"
had_files_private_disk="${FILES_PRIVATE_DISK+x}"
had_aws_access_key_id="${AWS_ACCESS_KEY_ID+x}"
had_aws_secret_access_key="${AWS_SECRET_ACCESS_KEY+x}"
had_aws_default_region="${AWS_DEFAULT_REGION+x}"
had_aws_bucket="${AWS_BUCKET+x}"
had_aws_url="${AWS_URL+x}"
had_aws_endpoint="${AWS_ENDPOINT+x}"
had_aws_use_path_style_endpoint="${AWS_USE_PATH_STYLE_ENDPOINT+x}"
had_r2_private_bucket="${R2_PRIVATE_BUCKET+x}"

cleanup() {
  if [[ -n "$had_files_disk" ]]; then export FILES_DISK="$restore_files_disk"; else unset FILES_DISK; fi
  if [[ -n "$had_files_private_disk" ]]; then export FILES_PRIVATE_DISK="$restore_files_private_disk"; else unset FILES_PRIVATE_DISK; fi
  if [[ -n "$had_aws_access_key_id" ]]; then export AWS_ACCESS_KEY_ID="$restore_aws_access_key_id"; else unset AWS_ACCESS_KEY_ID; fi
  if [[ -n "$had_aws_secret_access_key" ]]; then export AWS_SECRET_ACCESS_KEY="$restore_aws_secret_access_key"; else unset AWS_SECRET_ACCESS_KEY; fi
  if [[ -n "$had_aws_default_region" ]]; then export AWS_DEFAULT_REGION="$restore_aws_default_region"; else unset AWS_DEFAULT_REGION; fi
  if [[ -n "$had_aws_bucket" ]]; then export AWS_BUCKET="$restore_aws_bucket"; else unset AWS_BUCKET; fi
  if [[ -n "$had_aws_url" ]]; then export AWS_URL="$restore_aws_url"; else unset AWS_URL; fi
  if [[ -n "$had_aws_endpoint" ]]; then export AWS_ENDPOINT="$restore_aws_endpoint"; else unset AWS_ENDPOINT; fi
  if [[ -n "$had_aws_use_path_style_endpoint" ]]; then export AWS_USE_PATH_STYLE_ENDPOINT="$restore_aws_use_path_style_endpoint"; else unset AWS_USE_PATH_STYLE_ENDPOINT; fi
  if [[ -n "$had_r2_private_bucket" ]]; then export R2_PRIVATE_BUCKET="$restore_r2_private_bucket"; else unset R2_PRIVATE_BUCKET; fi
}

trap cleanup EXIT

export FILES_DISK="r2_public"
export FILES_PRIVATE_DISK="r2_private"
export AWS_ACCESS_KEY_ID="$access_key_id"
export AWS_SECRET_ACCESS_KEY="$secret_access_key"
export AWS_DEFAULT_REGION="auto"
export AWS_BUCKET="astrokomunita-public-prod"
export AWS_URL=""
export AWS_ENDPOINT="https://d839eaa7086db644c33b3d41ec5c9c7c.r2.cloudflarestorage.com"
export AWS_USE_PATH_STYLE_ENDPOINT="true"
export R2_PRIVATE_BUCKET="astrokomunita-private-prod"

cd "$backend_dir"

echo "spustam: php artisan optimize:clear"
php artisan optimize:clear

echo "spustam: php artisan storage:r2-healthcheck"
php artisan storage:r2-healthcheck

echo
echo "info: overte cloudflare ui:"
echo "public bucket: healthchecks/public/..."
echo "private bucket: healthchecks/private/..."
