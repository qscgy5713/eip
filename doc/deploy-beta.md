# Beta 環境部署紀錄(eip-beta.duckdns.org)

- 主機:GCP VM `35.253.140.113`(`ssh focus`),1GB RAM + 4GB swap,與 shop、motion-trace 共用同一台。
- HTTPS:共用的 Caddy(`shop-caddy-1`,設定在本機 `~/www/Caddy`,站台檔 `sites/eip.caddy`)自動簽憑證並轉給 `eip-web:80`。
- 程式位置:`~/eip`(以 tar 從本機傳上去,不含 `.git`、`vendor`、`.env`、`node_modules`;前端在本機 `npm run build` 後一起傳)。
- 啟動:`sudo docker compose -f docker-compose.yml -f docker-compose.prod.yml -f docker-compose.caddy.yml up -d`
- 監控:root crontab 每 5 分鐘 `docker exec eip-app php artisan eip:health-alert`(異常寄信給管理員,紀錄在 `~/eip/backups/health-alert.log`)。
- 備份:root crontab 每天 03:00 執行 `scripts/backup.sh`(保留 14 份,放 `~/eip/backups/`,權限 600)。

## 更新程式的步驟
1. 本機 `npm run build`,用 tar 把變更檔傳到 `~/eip`(不要覆蓋 `.env`、`storage/`)。
2. 有 PHP 依賴變更:`sudo docker exec eip-app composer install --no-dev --optimize-autoloader`。
3. `sudo docker exec eip-app php artisan migrate --force`
4. `sudo docker exec -u www-data eip-app sh -c "php artisan config:cache && php artisan route:cache && php artisan view:cache"`(**用 www-data 執行**)
5. `sudo docker compose ... restart queue scheduler`
6. `sudo docker exec eip-app php artisan eip:preflight`

## 踩過的坑
- **storage / bootstrap/cache 必須歸 www-data(uid 82)**:`sudo chown -R 82:82 storage bootstrap/cache`。否則 php-fpm 寫不了日誌與快取,`/up` 會 500。
- **`.env` 必須讓 www-data 讀得到**:`sudo chgrp 82 .env && chmod 640 .env`。否則 `config:cache` 讀不到設定,會快取成預設的 sqlite,整站 500。
- **`TRUSTED_PROXIES` 要走 config**:`config:cache` 之後 `bootstrap/app.php` 裡的 `env()` 是 null,導致資源網址變成 `http://` 被瀏覽器擋(白屏)。已改成 `config('eip.trusted_proxies')`。
- 伺服器沒有 `rsync`、`make`,所以用 tar 傳檔、用 `scripts/backup.sh` 備份。
- 在 1GB 的 VM 上建置 PHP 映像檔約 30 分鐘,期間 shop 服務仍正常。
