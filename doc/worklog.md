# 工作日誌 (Worklog)

## 2026-10-07
### 做了什麼
- 初始化專案文件結構與骨架。
- 規劃 EIP 企業資訊入口網整體功能模組（Phase 1 基礎核心、Phase 2 行政簽核考勤、Phase 3 文件與第三方整合）。
- 建立待辦清單 `doc/todo.md` 與系統規劃藍圖 `doc/plan.md`。
- 導入 Docker Compose 全容器化架構：整合 Nginx、PHP 8.4-FPM、PostgreSQL 16、Redis。
- 配置 Laravel 12 + Inertia.js (Vue 3) + Tailwind CSS 現代化全端架構。
- 建立 EIP 核心組織架構 (Departments)、企業公告 (Announcements)、動態表單與簽核引擎 (Forms & Approval Records)。
- 實作前台 UI 模組：總覽儀表板、企業公告閱覽與追蹤、表單線上申請與主管審批、組織同仁通訊錄。
- 撰寫自動化測試套件 `tests/Feature/EipTest.php`，驗證 30 項測試案例 100% 通過。
- 撰寫專案專用 `Makefile`，封裝所有容器管理（`make up`/`down`/`ps`/`logs`）、資料庫遷移、假資料填充與測試指令。
- 進行全面 Code Review：
  - 審查並強化 `FormRequestController`，允許管理員進行跨關卡/代理審查。
  - 修復 `OrganizationController` 在 PostgreSQL 下的關鍵字查詢大小寫相容性問題（採用 `LOWER()`）。
  - 修復 `AnnouncementController` 映射已讀同仁時在帳號異動下的 Null 安全問題。
  - 優化 `Forms/Create.vue` 與 `Directory/Index.vue` 的前端欄位驗證與搜尋狀態維持。

### 為什麼這樣做
- 在投入程式碼實作前，先確立清晰的階段性目標與架構邊界，有助於精確收斂 MVP 範圍，避免過度設計或需求發散。
- 使用 Docker Compose 能使開發與生產環境高度一致，且 PostgreSQL 具備優異的 JSONB 支援，能輕量支援 EIP 各類動態表單與稽核記錄。

- 依使用者需求擴充 Phase 2 考勤打卡模組（Attendance）：
  - 建立打卡資料表遷移與 Attendance 模型，支援打卡時間戳、IP、GPS 地點、工時自動計算與遲到/早退異常標記。
  - 實作 AttendanceController 提供個人打卡介面、月報彙總與主管團隊即時出勤監控。
  - 完成前端考勤介面（Index.vue）與首頁 Dashboard 快捷打卡橫幅。
  - 新增測試套件 `tests/Feature/AttendanceTest.php`，驗證 34 項測試全數通過。
- 完成 Phase 2 行事曆與會議室借用管理模組（Meeting Room Booking）：
  - 建立會議室資料表 `meeting_rooms`（設備 JSONB 儲存、容納人數、啟用狀態）與借用記錄表 `room_bookings`。
  - 實作 `RoomBooking::hasConflict` 排他性演算邏輯，防止同會議室於重疊時間遭重複預約。
  - 實作 `MeetingRoomController`，支援日曆日期篩選、同仁線上預約、本人/管理員取消預約、管理員維護會議室。
  - 建立前端介面 `MeetingRooms/Index.vue`，具備日期快捷切換、即時空閒/佔用看板、我的近期預約橫幅與快速預約彈窗。
  - 於首頁工作台 `Dashboard.vue` 與導覽列整合「即將進行的會議」與「會議室借用」快捷入口。
  - 實作自動化測試 `tests/Feature/MeetingRoomTest.php`（共 10 項測試案例）。
  - 主動 Code Review 與修復：在 `MeetingRoomController` 補強與會人數超出會議室容納上限之防呆阻擋，並補齊測試。
  - 執行全套測試，全系統累積 44 項自動化測試 100% 通過（109 assertions）。
  - 依使用者授權完成 Git Commit 並 Push 至遠端 GitHub 倉庫 (`master` @ `67eeb41`)。

- 完成 Phase 3 企業知識文件庫與檔案版本控制模組（Document Management）：
  - 建立 `documents` 與 `document_versions` 資料表，支援階層分類、部門歸屬、機密等級 `restricted_roles` 控管。
  - 實作版本累進與修訂紀錄功能，支援同文件疊加發布新版，完整保留歷史版本實體檔案。
  - 實作安全下載機制（`Storage::download`），嚴格防範越權下載 (IDOR) 並累計下載次數。
  - 完成前端 `Documents/Index.vue`，具備分類標籤切換、關鍵字檢索、部門篩選、文件卡片、多版本歷程 Modal 與發布新版彈窗。
  - 於桌機與手機導覽列新增「企業文件庫」入口。
  - 撰寫自動化測試 `tests/Feature/DocumentTest.php`，驗證文件上傳、新版本累進、安全下載、機密越權防護、刪除權限。
  - 主動 Code Review 與修復：修復 SQLite 測試環境下的 JSON 查詢語法相容性（全面改用 Laravel 原生 `whereJsonContains`）。
  - 透過 Headless Chrome 進行端對端畫面渲染驗證，確保 Vue 3 與 Tailwind CSS 樣式與機密權限隱藏效果完美呈現。
  - 全套測試通過數提升至 51 項 Feature/Unit 測試 100% 通過（138 assertions）。

- 大幅擴充電子表單範本與示範簽核單據資料庫（Forms & FormRequests）：
  - 擴充表單範本至 6 大常見企業行政類別：休假申請單 (LEAVE)、費用報銷單 (EXPENSE)、加班申請單 (OVERTIME)、忘刷/補打卡單 (CLOCK_ADJUST)、資訊設備資產採購單 (PURCHASE)、公出外勤洽公單 (TRIP)。
  - 豐富示範單據至 9 筆不同情境與狀態：包含待主管審批（特休、週末加班、4K 螢幕採購）、待總裁審批（Kubernetes 伺服器擴容）、已核准單據（出差高鐵報銷、上班補打卡、公出拜訪、季度慶生茶會點心）、已駁回單據（未檢附發票報銷退件）及詳細的主管審批附言紀錄。
  - 實作「自訂動態表單設計器」(Custom Form Builder)：
    - 後端：`FormRequestController::storeTemplate` 與 `destroyTemplate`，具備代碼格式校驗、JSON Schema 欄位結構驗證、無單據時徹底刪除與有單據時軟性下架防呆機制。
    - 路由：註冊 `POST /forms/templates` 與 `DELETE /forms/templates/{form}`。
    - 前端：於 `Forms/Index.vue` 增設「+ 自訂新表單範本」彈窗，支援主管與管理員動態新增欄位、指定類型（單行、多行、數值、日期、自訂下拉清單），儲存後全體同仁於前台立即可發起申請。
  - 擴充自動化測試案例：於 `tests/Feature/EipTest.php` 增設主管建立表單範本、一般同仁越權防護 (403) 與範本刪除/軟性停用測試。
  - 執行前端資產編譯（`npm run build`）與全套測試套件（`make test`），全系統 54 項 Feature/Unit 測試全數 100% 通過（146 assertions）。

## 2026-10-08
### 做了什麼
- 完成 Phase 3「站內通知中心與事件推播引擎」(Notification Center)：
  - 建立 Laravel `notifications` 資料表遷移，實作通用通知類別 `App\Notifications\EipSystemNotification`。
  - 於 `HandleInertiaRequests` 中介層共享目前使用者未讀通知計數與最新通知摘要。
  - 於導覽列 `AuthenticatedLayout.vue` 增設頂部「🔔 鈴鐺下拉選單」，具備紅點計數、未讀彈出預覽、快捷跳轉與一鍵標記已讀。
  - 完成前端獨立通知中心頁面 `Notifications/Index.vue`，支援通知分類標籤、全部已讀、個別已讀、刪除與分頁導覽。
  - 深度串接業務流程：表單申請時即時推播主管審批、主管審批（核准/駁回）時即時推播原同仁、會議室預約成功時即時發送確認通知。
- 完成 Phase 3「系統審計稽核日誌」(Audit Trail & Security Log)：
  - 建立 `audit_logs` 資料表與 Eloquent 模型，支援多態模型關聯、使用者、IP 位址、User-Agent 與詳細變更 JSONB。
  - 於關鍵業務模組（上下班打卡、表單送單與審批、會議室預約與取消、文件上傳/發布新版/下載/刪除、自訂表單範本）全面埋點審計軌跡。
  - 建立管理員專屬的審計日誌檢索頁面 `AuditLogs/Index.vue`，支援關鍵字即時檢索、動作類別統計與分頁導覽；同仁防越權 (403) 隔離。
- 撰寫自動化測試套件 `tests/Feature/NotificationAndAuditTest.php`：
  - 涵蓋通知檢視、單則已讀、一鍵全讀、表單流程通知與日誌連鎖驗證、會議室通知與日誌驗證、管理員日誌存取與同仁 403 越權防護。
  - 主動 Code Review 發現種子資料通知疊加問題，及時透過測試環境資料隔離修復。
- 完成 Phase 4「權限越權檢查 (IDOR Protection) 與安全性稽核加固」：
  - 盤查表單簽核模組：於 `FormRequest` 模型實作 `canAccess(User $user)` 授權演算，於 `FormRequestController::show` 嚴格阻擋跨部門/非關係人未授權調閱（403 Forbidden）。
  - 盤查公告模組：於 `AnnouncementController::show` 阻擋未公開/草稿狀態公告，僅系統管理員具備預覽授權。
  - 盤查會議室與文件模組：確認預約取消、文件發布新版、文件安全下載與刪除之角色與擁有者防偽邊界。
- 建置 Phase 4「CI/CD 自動化建置工作流」(GitHub Actions)：
  - 建立 `.github/workflows/ci.yml`，支援每次代碼 Push 或 PR 至 `master` 分支時，自動啟動 PHP 8.4、安裝 Composer 與 NPM 依賴、打包前端資產並執行全套自動化測試套件。
- 撰寫安全性自動化測試套件 `tests/Feature/SecurityAndIdorTest.php`：
  - 涵蓋表單跨部門調閱阻擋、本人查閱、主管/管理員查閱、草稿公告隔離、同仁竄改會議室阻擋、取消他人會議室借用阻擋。
- 完成 Phase 3/4「外部通訊群組 Webhook 整合生態」(Slack / Discord / Teams 即時推播)：
  - 建立 `webhooks` 資料表遷移與 Eloquent 模型，支援訂閱事件陣列與 `subscribesTo()` 判定。
  - 實作 `WebhookService`：提供跨平台相容 Payload（Slack `text`、Discord `content`、通用 `data`）、HMAC-SHA256 數位簽章防偽與 3 秒超時安全容錯。
  - 串接核心業務流程：表單申請送出 (`form.submitted`)、主管審核通過 (`form.approved`)、主管駁回退件 (`form.rejected`)、會議室預約成立 (`room.booked`) 全面支援 Webhook 自動推播。
  - 實作 Webhook 管理後台 `Webhooks/Index.vue`：支援端點清單檢視、新增 Webhook、一鍵 Ping 連線測試、啟用/停用切換與刪除。
  - 於 `AuthenticatedLayout.vue` 導覽列與下拉選單增設「整合設定 / 外部整合 (Webhooks)」入口（管理員專屬權限控制）。
  - 撰寫自動化測試套件 `tests/Feature/WebhookTest.php`：涵蓋管理員存取、員工越權防護 (403)、端點建立/切換/刪除、連線測試 Ping、表單提交推播與會議室預約推播等 8 項測試。
- 執行前端資產建置（`npm run build`）與全套測試套件（`php artisan test`）：
  - 全系統累積 **74 項自動化 Feature/Unit 測試全數 100% 通過**（193 assertions）。
- 完成 Phase 2「簽核職務代理人機制」(Delegation & Proxy Signing Engine)：
  - 建立 `delegations` 資料表遷移，支援指定代理人 (`delegate_id`)、生效起訖日期 (`start_date`, `end_date`)、事由與啟用狀態；並於 `approval_records` 擴充 `delegated_from_id` 外鍵留存代簽軌跡。
  - 實作 Eloquent 模型 `Delegation`，包含 `currentlyActive` scope 與 `isCurrentlyActive()` 期間演算。
  - 於 `User` 模型實作關聯與 `canActAsDelegateFor($user)` 代理權限驗證。
  - 深度擴充 `FormRequestController`：
    - 待審清單 (`index`)：自動匯整由我代理之主管待審單據，並標記 `is_delegated`。
    - 送單通知 (`store`)：主管若有生效中代理人，同步推播站內通知給代理人。
    - 調閱授權 (`show`)：在 `FormRequest::canAccess` 中給予生效中代理人合規調閱權限（IDOR 防護加固）。
    - 審批執行 (`action`)：支援代理人代為核准或駁回，審批紀錄與通知精確標記「代理人 XXX（原主管：YYY）代簽」。
  - 實作前端 `Delegations/Index.vue`：支援我指派的代理人清單、指派我為代理人之主管清單、新增代理人彈窗、暫停/重新啟用與刪除。
  - 於 `Forms/Index.vue` 增設「設定職務代理人」快捷鍵與待審單據「🏷️ 代理代簽」徽章；於 `Forms/Show.vue` 審核歷程標示職務代理節點。
  - 於 `AuthenticatedLayout.vue` 整合職務代理人設定入口。
  - 撰寫自動化測試套件 `tests/Feature/DelegationTest.php`，涵蓋設定、防指派自己、暫停/刪除、生效代理人調閱與代簽、過期代理人 403 阻擋等 6 項測試。
- 執行前端資產打包（`npm run build`）與全套測試套件（`php artisan test`）：
  - 全系統累積 **80 項自動化 Feature/Unit 測試全數 100% 通過**（223 assertions）。
- 完成 Phase 2「HR 人資考勤月報統計與工時結算匯出系統」(Attendance & Payroll Analytics with CSV Export)：
  - 實作控制器 `AttendanceReportController`：
    - `index`：月度出勤 KPI 統計（涵蓋在職員工數、總累計工時、全體遲到人次、早退人次）、員工個人月度出勤天數/工時/異常匯總，以及每日打卡明細展開檢視。
    - `exportSummary`：一鍵匯出月度各員工出勤統計彙總 CSV，內建 UTF-8 BOM，相容 Windows/Mac Microsoft Excel 繁中無亂碼。
    - `exportDetails`：一鍵匯出月度全員每日打卡明細 CSV，包含打卡時間戳、工時、出勤狀態與備註說明。
    - 安全防護：嚴格限定 HR、主管與系統管理員檢閱，一般員工阻擋（403 IDOR 防護），每次匯出敏感個資均自動寫入審計稽核日誌 (AuditLog)。
  - 實作前端 `Attendance/Report.vue`：支援年份月份快速切換、部門篩選下拉、員工姓名/工號關鍵字檢索、4 大 KPI 指標卡、彙總資料表格與點擊展開每日出勤明細。
  - 於 `Attendance/Index.vue` 頂部增設「📊 考勤月報統計與工時結算」入口按鈕，於 `AuthenticatedLayout.vue` 整合選單連結。
  - 撰寫自動化測試套件 `tests/Feature/AttendanceReportTest.php`：涵蓋 HR/Admin/Manager 存取授權、一般同仁越權防護 (403)、月份與部門篩選、匯出 CSV 格式/BOM/標頭/審計日誌等 6 項測試。
- 執行前端資產建置（`npm run build`）與全套測試套件（`php artisan test`）：
  - 全系統累積 **86 項自動化 Feature/Unit 測試全數 100% 通過**（278 assertions）。

### 下一步
- 向使用者回報完整進度與成果，詢問是否同意執行 Git Commit 與 Git Push。
