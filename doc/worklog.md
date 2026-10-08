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
  - 全套測試通過數躍升至 **60 項測試案例 100% 通過**（163 assertions）。

### 下一步
- 詢問使用者是否同意提交 Git Commit 與 Git Push，確認後即可繼續推進 Phase 4「權限越權檢查 (IDOR) 與生產環境最佳化」。
