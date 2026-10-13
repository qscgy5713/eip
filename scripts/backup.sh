#!/usr/bin/env bash
# 不依賴 make 的備份腳本(正式主機用,可放 cron):
#   0 3 * * * /home/<user>/eip/scripts/backup.sh >> /home/<user>/eip/backups/backup.log 2>&1
# 備份 PostgreSQL 與 storage/app 到 backups/,保留最近 BACKUP_KEEP 份(預設 14)。
set -euo pipefail

cd "$(dirname "$0")/.."
KEEP="${BACKUP_KEEP:-14}"
DIR="backups"
ts="$(date +%Y%m%d_%H%M%S)"
mkdir -p "$DIR"

docker exec eip-db sh -c 'pg_dump -U "$POSTGRES_USER" "$POSTGRES_DB"' | gzip > "$DIR/eip_db_$ts.sql.gz"
test -s "$DIR/eip_db_$ts.sql.gz" && gzip -t "$DIR/eip_db_$ts.sql.gz"
tar -czf "$DIR/eip_files_$ts.tar.gz" -C storage/app .
chmod 600 "$DIR"/eip_*_"$ts".*

ls -1t "$DIR"/eip_db_*.sql.gz | tail -n +$((KEEP + 1)) | xargs -r rm -f
ls -1t "$DIR"/eip_files_*.tar.gz | tail -n +$((KEEP + 1)) | xargs -r rm -f
echo "$(date '+%F %T') 備份完成:eip_db_$ts.sql.gz、eip_files_$ts.tar.gz"
