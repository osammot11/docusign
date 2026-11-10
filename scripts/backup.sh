#!/usr/bin/env bash
set -euo pipefail
umask 077
dump="/tmp/pav-sign-$(date -u +%Y%m%dT%H%M%SZ).dump"
export PGPASSWORD="${DB_PASSWORD:?DB_PASSWORD missing}"
pg_dump -Fc -h "${DB_HOST:-db}" -U "${DB_USERNAME}" "${DB_DATABASE}" -f "$dump"
if ! restic snapshots >/dev/null 2>&1; then restic init; fi
restic backup "$dump" /data/storage --tag pav-sign
restic forget --keep-daily 14 --keep-weekly 8 --keep-monthly 12 --prune
restic check --read-data-subset=5%
rm -f "$dump"
