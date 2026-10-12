# EIP 企業資訊入口網 - 系統架構與開發規劃

## 1. 專案願景與目標
打造一個現代化、模組化、易維護且具備高擴充性的企業資訊入口網（Enterprise Information Portal, EIP），整合企業內部日常溝通、行政簽核、考勤紀錄與資源管理，提升跨部門協作效率。

---

## 2. 核心功能模組劃分

### Phase 1: 基礎核心與資訊中心 (MVP)
- **組織架構與帳號權限 (RBAC)**
  - 多層級組織架構（公司 -> 部門 -> 組別 -> 員工職稱）
  - 角色與細部權限管理（系統管理員、HR/行政、部門主管、一般同仁）
  - 支援 LDAP / Google Workspace SSO 或標準帳密 + 2FA 登入
- **企業公告與即時通知 (Announcement & Notifications)**
  - 全體公告、部門特定公告、置頂與排程發布
  - 閱讀狀態追蹤（追蹤誰已讀/未讀）與附件下載
  - 系統內站內信 / 鈴鐺通知 / Webhook / Email 推播
- **員工通訊錄 (Directory)**
  - 組織樹狀圖檢視、關鍵字搜尋（分機、Email、技能）

### Phase 2: 辦公流程與資源協作 (Operations)
- **電子表單與工作流簽核系統 (BPM / Workflow Engine & Approvals Hub)**
  - 常用表單：請假單、加班單、補打卡、差旅報支、採購申請
  - 動態簽核流程：直屬主管審批、會簽、加簽、條件分支、代理人機制
  - 主管審核中心與一鍵批次簽核：待審單據集中彙整、多選批次核准/駁回、審批批註附言、自動流轉與連鎖連動
  - 簽核履歷記錄與 PDF 匯出
- **考勤與打卡管理 (Attendance & Leave Quotas)**
  - GPS / 辦公室 IP 限制打卡、地理圍欄設定、打卡異常提醒
  - 請假排班與出勤月報表匯出（支援 HR 結算工時）
  - 特休與各假別年度額度管理（Leave Balances：個人可用餘額、HR 額度分配/一鍵初始化、請假即時防呆與審批扣減）
- **行事曆與資源預約 (Calendar & Facilities)**
  - 個人/部門公用行事曆
  - 會議室與設備借用（防時段衝突機制）

### Phase 3: 企業資產與生態整合 (Ecosystem)
- **企業雲端文件庫 (Document Management)**
  - 公司規章、表單範本、ISO 文件分類
  - 嚴格權限讀寫控制、版本歷程回溯
- **報表與儀表板 (Dashboard & Analytics)**
  - 主管專屬儀表板（待簽核總覽、部門出勤概況）
  - 個人儀表板（今日待辦、個人打卡狀態、未讀公告）
- **第三方整合擴充**
  - Slack / Microsoft Teams / LINE Notify 機器人推播
  - Google Calendar 同步

### Phase 4: 企業全方位數位生態旗艦大擴充 (Ultimate Enterprise Ecosystem Expansion)
1. **企業同仁訂餐與下午茶揪團系統 (Corporate Meal & Tea Group Order Hub)** `[✅ 已完工上線]`
   - 發起訂單、自訂規格甜度冰塊、截止倒數計時、補助金額設定
   - 自動品項數量即時彙總清單（方便行政電話訂餐）、自動應付金額試算與收款標記對帳
2. **內部專屬客服工單與 IT / 行政叫修派單系統 (Internal Helpdesk & Service Ticketing Hub)** `[✅ 已完工上線]`
   - 快速提單（IT資訊、總務設施、人資財務問題）、附檔上傳、急迫度分級
   - 權責工程師認領與指派、四態進度流轉 (Open/In Progress/Pending User/Resolved)、五星滿意度評價
3. **企業訪客預約與門禁貴賓接待系統 (Visitor Management & Guest Reception Hub)** `[✅ 已完工上線]`
   - 同仁提前預約訪客、專屬數位通行卡單號與防偽驗證
   - 前台大廳快速簽到 Check-in、連鎖即時通知接待同仁、出入安全日誌與符合 ISO 27001 實體安全稽核
4. **企業公務車輛派車與出差調派系統 (Company Fleet & Vehicle Dispatch Hub)** `[✅ 已完工上線]`
   - 公務車檔案（車牌/型號/保養里程檢核）、派車預約申請（事由/時段/目的地/隨行同仁）
   - 防時段重疊衝堂衝突演算、出車與還車里程/油料報支登記、行駛里程自動試算與每 5000km 保養提醒
5. **企業內訓課程與員工學習學分系統 (Corporate Training & Learning Credits Hub)** `[✅ 已完工上線]`
   - 訓練課程發布（主題/講師/教材下載/教室/學分）、線上報名與防爆額候補遞補機制
   - 個人學分存摺（累計學分與研習總時數）、完訓評量滿意度、符合 ESG 人力資本培訓總表 CSV 匯出
6. **企業福利點數、同仁互贈感謝幣與文化牆系統 (Peer Recognition & Welfare Points Hub)** `[✅ 已完工上線]`
   - 每月固定額度感謝幣互贈 (Kudos) 與感謝留言、公開企業文化感謝牆輪播
   - 福利點數存摺 (Welfare Points)、福利商城兌換券、月度感謝之星榮譽榜

---

## 3. 已定案技術架構 (Finalized Tech Stack)

- **容器編排**：Docker Compose (服務：app, web, db, redis)
- **後端框架**：Laravel 12 (PHP 8.4-FPM)
- **前端架構**：Inertia.js + Vue 3 + Tailwind CSS + Vite
- **關聯資料庫**：PostgreSQL 16 (原生 JSONB 支援彈性表單欄位)
- **快取與佇列**：Redis (Alpine)
- **反向代理與靜態服務**：Nginx (Alpine)

---

## 4. 安全性與合規規範
1. **審計日誌 (Audit Trail)**：表單修改、人員權限變更、敏感情資存取一律記錄完整 Log。
2. **機密資料保護**：員工身分證字號、薪資、銀行帳戶採 AES 資料庫欄位加密。
3. **傳輸安全**：全面強制 HTTPS，並配置安全標頭 (CSP, HSTS) 與 Rate Limiting 防暴力破解。

---

## 5. 實施階段路線圖 (Roadmap)
```
[階段 0: 需求對齊與技術定案] ──> [階段 1: 基礎架構與 MVP 實作] ──> [階段 2: 考勤與表單簽核] ──> [階段 3: 文件管理與通知整合]
```
1. **需求與架構確認**：確定企業規模、主選技術棧、是否需對接現有 HR/ERP 系統。
2. **基礎骨架建置**：資料庫遷移、會員認證、組織架構管理。
3. **功能反覆迭代**：以 MVP 為基準分批驗證上線。
4. **壓力測試與安全檢查**：權限越權檢查 (IDOR)、高併發打卡壓測。

## 正式環境部署清單
1. 複製 `.env.production.example` 為 `.env`,填入 `APP_KEY`、資料庫、SMTP;確認 `APP_DEBUG=false`、`APP_ENV=production`、`SESSION_SECURE_COOKIE=true`。
2. 複製 `docker/nginx/production.conf.example` 為 `docker/nginx/default.conf`,換成實際網域與憑證路徑(HTTPS、HSTS、CSP)。
3. 啟動 `docker compose up -d`(含 `queue` 容器);首次執行 `php artisan migrate --force`、`php artisan config:cache route:cache view:cache`。
4. 以 `EIP_REQUIRE_2FA_ROLES=admin,hr` 強制高權限角色啟用雙因素驗證;預設測試帳號(密碼 `password`)正式上線前必須刪除或改密碼。
5. 定期備份 PostgreSQL 與 `storage/app`。
