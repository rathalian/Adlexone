#!/usr/bin/env bash
# Idempotent. Copies missing config templates and creates writable dirs.
set -euo pipefail
cd "$(dirname "$0")/.."

mkdir -p config storage/database storage/attachments writeable

copy_if_missing() {
  local src="$1"
  local dest="$2"
  if [[ ! -f "$dest" ]]; then
    mkdir -p "$(dirname "$dest")"
    cp "$src" "$dest"
  fi
}

while IFS= read -r -d '' src; do
  rel="${src#config.example/}"
  copy_if_missing "$src" "config/$rel"
done < <(find config.example -type f -name '*.json' -print0)
