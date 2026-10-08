# EIP 專案待辦清單 (Todo List)

## 階段 0：需求確認與架構定案
- [x] 建立專案骨架與核心文檔 (`doc/plan.md`, `doc/todo.md`, `doc/worklog.md`, `README.md`)
- [x] 確認技術棧選型（Laravel 12 + Inertia.js Vue 3 + PostgreSQL 16 + Redis + Docker Compose）
- [x] 確認核心功能範圍與優先級（MVP: 組織權限 + 企業公告 + 表單簽核工作流 + 通訊錄）
- [x] 確認身分驗證方式（Laravel Breeze 認證系統 + RBAC 權限角色）

## 階段 1：系統基礎與組織架構 (MVP)
- [x] 初始化專案環境與 Docker Compose 建置腳本（Nginx + PHP 8.4-FPM + PostgreSQL + Redis）
- [x] 設計組織架構與使用者資料表（Departments, Users, Roles）
- [x] 實作 RBAC 角色與細部權限機制（admin, manager, employee, hr）
- [x] 使用者身分驗證與個人資料維護
- [x] 企業公告發布與閱覽追蹤功能（Announcements, Reads 追蹤）
- [x] 員工通訊錄與組織樹狀圖（Directory 查詢與篩選）

## 階段 2：行政流程與協同工作
- [x] 電子表單簽核工作流引擎（請假單、報銷單、加班單、補打卡單、請購單、差旅單等動態 JSON Schema 欄位）
- [x] 自訂動態表單設計器（Custom Form Builder：支援管理員/主管自由配置欄位型態、選項與驗證，建立後全員立即套用發起申請）
- [x] 主管審批與簽核歷程記錄機制（Approval Records、核准/駁回附言、單據狀態即時更新）
- [x] 簽核職務代理人機制（Delegation Engine：出差休假代簽人設定、生效期間自動判定、代理待審通知連鎖、歷程代簽標籤與 IDOR 權限加固）
- [x] 打卡考勤系統（GPS/IP 限制、打卡紀錄、異常判定、主管團隊出勤）
- [x] 行事曆與會議室借用管理（會議室管理、防衝突排程、日曆預約、超額人數校驗）

## 階段 3：資產管理與生態整合
- [x] 企業文件庫與檔案版本控制（分類資料夾、文件上傳、版本歷程回溯、下載權限控管、密件隔離）
- [x] 系統儀表板（待辦統計、未讀公告、進行中申請、即將到來會議、快捷打卡）
- [x] 站內通知中心與推播通知整合（Laravel Notifications、導覽列小鈴鐺即時未讀數與下拉預覽、簽核/會議室/公告事件即時發送、一鍵全讀）
- [x] 外部通訊群組 Webhook 整合生態（Slack / Discord / Teams 即時推播、HMAC-SHA256 數位簽章防偽、雙向跨平台 Payload 相容、連線 Ping 測試與開關切換）
- [x] 系統審計稽核日誌 (Audit Trail) 與安全性加固（操作人員、IP、動作分類、變更詳情 JSON、管理員專屬查詢篩選）

## 階段 4：測試與正式上線
- [x] 單元與整合測試（EipTest, AttendanceTest, MeetingRoomTest, DocumentTest, NotificationAndAuditTest, SecurityAndIdorTest, WebhookTest, DelegationTest 等 80 項測試案例全數 100% 通過）
- [x] 權限越權檢查 (IDOR) 與安全性稽核（FormRequest canAccess 授權機制、草稿公告隔離、會議室/文件權限邊界校驗）
- [x] 容器化 (Docker) 與 CI/CD 自動化建置（Docker Compose 容器編排 + GitHub Actions 自動化測試流程 `.github/workflows/ci.yml`）
