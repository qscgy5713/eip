# 工作日誌 (Worklog)

## 2026-10-08
### 做了什麼
- 實作「電子表單與簽核」模組之**檢附證明文件與附件安全上傳/下載系統 (Form Attachments & Proof Documents Upload/Download)**：
  - **資料庫擴充**：建立資料庫遷移 `2026_10_08_040000_add_attachments_to_form_requests_table.php`，為 `form_requests` 表新增 `attachments` JSONB 欄位，支援動態陣列存儲附件中繼資料（原始檔名、儲存路徑、大小、MIME 類型與上傳時間）。
  - **後端安全防護**：
    - `app/Http/Controllers/FormRequestController.php` 實作多檔案驗證上傳（限制單檔最大 10MB，副檔名白名單：jpg, jpeg, png, pdf, doc, docx, xls, xlsx, csv, zip）。
    - 檔案安全存放於 `storage/app/public/form_attachments`，檔名採用隨機 hash 避免衝突。
    - 實作安全下載方法 `downloadAttachment`，透過 `EipFormRequest::canAccess` 實施強制 IDOR 存取控制（僅申請人、審核主管、簽核代理人與管理員可調閱），並在每次下載時自動寫入 `AuditLog` 審計留痕。
  - **前端介面 (Vue 3)**：
    - `resources/js/Pages/Forms/Create.vue`：加入檢附證明文件上傳區塊，支援點擊/拖曳多檔案選取、即時檔案大小格式化與個別移除按鈕。
    - `resources/js/Pages/Forms/Show.vue`：在申請單明細加入檢附證明文件卡片清單與安全下載按鈕。
    - `resources/js/Pages/Forms/Print.vue`：在公文存證列印頁面整合「檢附證明文件清單」表格，完整記錄項次、檔名、大小與上傳存檔時間，確保紙本與 PDF 存證備查。
  - **自動化測試**：
    - 建立 `tests/Feature/FormAttachmentTest.php`，共 7 項測試案例（包含有附件/無附件建立、申請人/主管授權下載、他部同仁 403 越權阻擋、不存在附件 404、副檔名與大小違規防護、下載 AuditLog 審計）。
    - 執行全系統 103 項測試案例全數 100% 通過（403 assertions）。
- **表單申請送出防呆與錯誤反饋全面加固**：
  - 排查並解決前端發起申請時因缺乏 `forceFormData` 與欄位錯誤未提示所導致的「按了沒反應」問題。
  - 在 `Forms/Create.vue` 提交時加入 `{ forceFormData: true, preserveScroll: true }`，並增設全域錯誤橫幅與單一欄位紅字提示，按鈕加入旋轉 Loading 動畫與防重複提交機制。
  - 後端 `FormRequestController::store` 擴充 `attachments` 為陣列容錯驗證。
- **導覽列 (Header Bar) 跑版擠壓修復與排版重構**：
  - 排查桌機與筆電螢幕因 10 個導覽連結與過大間距 (`space-x-8`) 導致文字換行擠壓跑版問題。
  - 將導覽容器寬度調整為自適應全寬 (`max-w-full px-4 sm:px-6 lg:px-8`)，間距設為動態自適應 (`space-x-1 sm:space-x-2 md:space-x-3 lg:space-x-4 xl:space-x-6`)。
  - 將管理員專屬之「系統日誌」與「整合設定」整併收納至「系統管理」Dropdown 下拉選單，大幅釋放導覽列寬度空間，文字加入 `whitespace-nowrap`，徹底解決擠壓跑版問題。
- **知識文件庫 (Documents) 線上安全預覽引擎開發**：
  - 新增路由 `/documents/{document}/preview/{version?}` 與 Controller 方法 `preview`。
  - 支援以 `inline` Content-Disposition 安全輸出文件串流，自動映射正確 MIME 類型，並記錄 `preview_document` AuditLog 審計日誌。
  - 前端 `Documents/Index.vue` 列表與版本歷程加入「預覽」按鈕，實作全螢幕高質感 Modal 彈窗：PDF 內嵌高畫質閱讀、圖片原寸自適應、純文字代碼瀏覽、Office 文件引導與新分頁全螢幕開啟。
  - 於 `tests/Feature/DocumentTest.php` 增加 2 項 Feature 測試（公開文件預覽、機密文件 403 阻擋），全系統測試擴充至 105 項 100% 通過（408 assertions）。
- **全方位深度 Code Review (CR) 與系統架構重構修復**：
  - **安全性加固 (Security / IDOR)**：排查出 `AttendanceReportController` 之 `index`、`exportSummary` 與 `exportDetails` 存在主管可傳入他部 `department_id` 越權竊閱/匯出全公司考勤薪資紀錄之高風險 IDOR 漏洞，全面加入強制部門鎖定防護。
  - **敏感個資防護 (Data Exposure Prevention)**：排查出 `OrganizationController` 原先整列查詢使用者暴露雜湊密碼與 Token 等隱私資料，全面改為顯式 `select` 白名單欄位。
  - **資料庫效能優化 (N+1 Query Optimization)**：排查出 `AnnouncementController::index` 原先於列表遍歷時逐筆調用 `isReadBy` 造成 10 次額外 SQL 查詢，重構改用 Eloquent `withExists` 關聯子查詢一次加載，完全消滅 N+1 瓶頸。
  - **邊界防禦與例外處理 (Edge Cases)**：修復 `AttendanceController::clockOut` 原先當同仁未打上班卡時直接拋出 404 ModelNotFoundException 白畫面崩潰之問題，改為友善提示導流。
  - **前端時間換日偏差修復 (Timezone Shift Bug)**：修復 `MeetingRooms/Index.vue` 中 `shiftDate` 使用 `toISOString()` 導致 UTC+8 換日產生跨天偏差問題，改用本地年月日建構防範。
  - **操作視覺狀態回饋 (UX Polish)**：補強 `Forms/Show.vue` 主管審核同意/駁回按鈕在 `processing` 狀態之禁用樣式與旋轉 Loading 動畫。
  - 全套自動化 Feature 測試 105 項全數 100% 通過（408 assertions），前端 Vite 建置 0 錯誤 0 警告。

### 為什麼這樣做
- 同仁請假（病假、婚喪假、公傷假）與報銷常需檢附就醫診斷證明、公文或收據，原先系統僅能填寫文字理由，造成審批主管無法實質查驗佐證資料。
- 採用 JSONB 儲存附件中繼資料兼顧了結構彈性與查詢效能，下載時透過索引 (index) 映射後端檔案路徑，杜絕路徑遍歷與內部儲存結構暴露風險。
- 企業知識文件庫同仁經常需要快速查閱規章或範本內容，若每次都需下載至本機電腦開啟，不僅繁瑣且造成暫存檔佔用；提供線上預覽大幅提升同仁日常閱讀與檢閱效率。

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

- 全面優化前端視覺設計，移除不專業的 Emoji 圖示：
  - 檢索全專案前端 Vue 頁面中雜亂的 Emoji 表情符號（包含導覽選單、按鈕、文件類型、通知標題、會議室卡片、通訊錄欄位等共 12 個檔案）。
  - 將 Emoji 替換為乾淨、俐落的企業級 SVG 向量圖示（如 Heroicons 風格的盾牌、鈴鐺、時鐘、建築、下載、傳輸、資料夾、連結、信箱與電話等）或專業的文字狀態標籤（如 PDF / DOC / XLS / PPT 副檔名 badge）。
  - 重新打包前端資產（`npm run build`），並執行全系統 86 項 Feature/Unit 測試全數 100% 通過。

- 完成 Phase 2「企業全景綜合行事曆看板 (Enterprise Calendar Hub)」：
  - 實作控制器 `CalendarController`：
    - 聚合會議室預約事件 (`RoomBooking`)、核准差勤休假單 (`FormRequest`) 與企業重要公告發布日程 (`Announcement`)。
    - 支援依年月期間（自動補齊月曆首尾前後補日）範圍抓取、事件類型篩選（全部、會議、休假、公告）與部門篩選。
  - 註冊路由 `/calendar` 並整併至頂部主導覽列與手機選單 (`AuthenticatedLayout.vue`)。
  - 實作前端 `Calendar/Index.vue`：
    - 42 格標準月曆網格，即時標記當日 (Today) 與週末。
    - 藍/紫/琥珀三色事件徽章、超出 3 筆「+N 則更多」摺疊收納。
    - 單一事件詳情彈窗（包含會議參與人數、地點、時間，請假事由與天數，公告導覽連結）與當日全體事件列表彈窗。
    - 年月份快捷切換（上個月、今天、下個月）。
  - 撰寫自動化測試套件 `tests/Feature/CalendarTest.php`：涵蓋行事曆檢閱、會議室聚合、核准假單聚合、公告日程聚合、類型篩選等 5 項測試。
  - 全系統累積 **91 項自動化測試 100% 通過**（339 assertions）。

- 完成 Phase 2「電子簽核公文單據正式列印與 PDF 存證匯出 (Form Request Print & Official PDF Archive)」：
  - 擴充控制器 `FormRequestController::print`：
    - 支援依單據 ID 產製公文存證檢視，嚴格校驗 IDOR 水平越權（僅限申請人本人、審批主管、生效職務代理人或系統管理員調閱）。
    - 每次產製與列印機密單據自動寫入系統審計稽核日誌 (`print_form_request`)。
  - 註冊路由 `/forms/requests/{formRequest}/print`。
  - 於 `Forms/Show.vue` 單據頁面增設「列印存證 / PDF」快捷按鈕。
  - 實作前端 `Forms/Print.vue`：
    - 標準 A4 版型高對比度排版，整合 `@media print` 列印最佳化（自動隱藏頂部工具列、邊距最佳化、防跨頁截斷）。
    - 完整呈現公文編號、申請同仁資訊（工號、部門、職稱）、申請主旨與自訂欄位表格。
    - 呈現完整審核簽署鏈軌跡（包含職務代理代簽標註、審定時間與附言）。
    - 右下角蓋上紅色雙圓框「企業電子核准防偽印章 (Official Approved Seal)」，頁尾附帶安全雜湊序號與調閱留痕。
  - 撰寫自動化測試套件 `tests/Feature/FormPrintTest.php`：涵蓋本人調閱、主管調閱、未授權 403 阻擋、審計日誌生成、生效職務代理人調閱等 5 項測試。
  - 全系統累積 **96 項自動化 Feature/Unit 測試全數 100% 通過**（380 assertions）。

### 下一步
- 向使用者回報公文單據列印存證與 PDF 匯出功能成果，詢問是否同意執行 Git Commit 與 Git Push。
