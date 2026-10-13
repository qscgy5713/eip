# 資安測試報告(2026-10-13)

範圍:本機開發環境(白箱:原始碼 + 自動化測試)與 beta 站 `https://eip-beta.duckdns.org`(黑箱:只做唯讀、無破壞性的探測,沒有送任何攻擊 payload 到線上站)。
前一份審查見 `doc/security-review.md`(SEC-01~07);本次是複測加上新功能(工單、PWA、2FA、登入紀錄等)的新增面向。

## 發現與處理

| 編號 | 風險 | 問題 | 處理 |
|---|---|---|---|
| **N-01** | **高** | **工單(叫修)附件完全沒有型別限制,且存在公開磁碟 `public`,可由 `/storage/...` 直接存取**。登入同仁可上傳 `.php` / `.html` / `.svg`:HTML/SVG 會在本站網域執行(儲存型 XSS、可竊取他人 session 內容);nginx 把所有 `.php` 交給 PHP-FPM,上傳 `.php` 理論上可達遠端程式執行(RCE)。同時任何知道網址的人(含未登入)都能下載附件。實測:修正前 6 種危險檔案(php、phtml、雙副檔名、html、svg、.htaccess)全部被接受 | ① 附件改驗證型別(`mimes`,依內容判斷)並改存私有 `local` 磁碟;② 新增授權下載路由 `tickets.attachment` / `tickets.reply-attachment`(需能檢視該工單;內部備註的附件只有處理人員能下載),一律 `attachment` 下載 + `nosniff` + `sandbox`;③ nginx 只讓 `/index.php` 交給 PHP,其餘 `.php` 一律 404,`/storage/` 加 `sandbox` CSP。回歸測試 `UploadSecurityTest` |
| **N-02** | 中 | 忘記密碼:已註冊與未註冊的信箱回應不同(帳號列舉,可探測哪些人是公司員工),且路由沒有節流 | 一律回同樣訊息(含被節流時),路由加 `throttle:5,1`。回歸測試 `AuthSecurityTest` |
| **N-03** | 低 | CSV 公式注入防護(SEC-07)漏了幾個欄位:派車匯出的姓名/部門/核准人、訪客匯出的主辦人/部門/會議室 | 補上 `sanitize`。測試 `CsvInjectionExportTest` 把使用者姓名改成 `=HYPERLINK(...)` 後逐一匯出 7 種 CSV 驗證 |

## 沒有發現問題的項目(已有自動化測試鎖住)

- **認證**:登入失敗 5 次後鎖定(正確密碼也被擋);登入錯誤訊息不透露帳號是否存在;登入後換新 session id(防 session fixation);離職/停權者的既有 session 被拒;登入的 `redirect` 參數無法導向站外。
- **授權(全路由掃描 `SecuritySweepTest`)**:所有需登入的路由,未登入者對任何 HTTP 方法都被擋(新增路由忘了加認證會立刻失敗);一般員工碰不到管理員/人資專用區;員工瀏覽所有 GET 路由沒有 5xx。
- **橫向越權 IDOR(`IdorTest`)**:他人的表單單據(檢視/列印/附件/核准/撤回/轉簽/加簽)、薪資單(檢視/修改/刪除)、通知(標已讀/刪除)、休假額度、文件版本(跨文件版本調閱)、他人的個人頁——全部擋下且資料不變。
- **權限提升**:個人資料更新帶入 `role`/`status` 無效;人資無法建立管理員、無法把人升成管理員、無法修改管理員。
- **相依套件**:`composer audit`、`npm audit --omit=dev` 皆 0 個已知漏洞。
- **SQL 注入**:原生 SQL 只出現在 `whereRaw`/`orderByRaw`/`selectRaw` 且全為參數綁定或常數;`v-html` 只用在分頁連結標籤(後端產生,非使用者輸入)。
- **Webhook SSRF**:有擋私有網段與保留位址。
- **beta 站外部探測**:`.env`、`.git/*` 回 403;`composer.json`、`artisan`、`storage/logs`、`vendor`、`backups`、`telescope`、`phpinfo` 等 20 個敏感路徑都 404;HTTP 自動 308 轉 HTTPS;憑證為 Let's Encrypt、TLS 1.0/1.1 被拒、支援 TLS 1.2/1.3;對外只開 22/80/443(5432、6379、3306 等資料庫埠沒有對外);回應標頭有 HSTS、CSP(nonce)、X-Frame-Options、nosniff、Referrer-Policy、Permissions-Policy,沒有洩漏 `Server`/`X-Powered-By`。

## 已知限制 / 沒有測的

- **沒有做**:負載/DoS、暴力破解線上站、真實 2FA 繞過嘗試、社交工程、主機層(SSH 設定、作業系統修補)、Caddy 與同機其他服務(shop)的資安。
- 22 埠(SSH)對全網開放,但已確認 `PasswordAuthentication no`、`PermitRootLogin no`、只允許金鑰登入;如要再收斂,可用 GCP 防火牆限制來源 IP。
- 表單附件/公告附件的 `mimes` 只擋副檔名對應的內容型別,沒有防毒掃描。
- CSP 的 `style-src` 仍保留 `'unsafe-inline'`(Vue/Tailwind 動態樣式需要),腳本已用 nonce。
- 自動化的 IDOR 測試只涵蓋高價值資源(單據、薪資、通知、額度、文件),未逐一覆蓋全部 70 多個頁面的每個物件。
- 本機測試資料庫是 SQLite,PostgreSQL 專屬行為(如 `ilike` 搜尋)沒有在測試中跑到。
