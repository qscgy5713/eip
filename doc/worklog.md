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

### 下一步
- 繼續推進 Phase 3「企業文件庫與檔案版本控制」或「推播通知整合」。
