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
- [x] 打卡考勤系統與 HR 月報結算（GPS/IP 限制、打卡紀錄、異常判定、主管團隊出勤、月度考勤統計看板、月度彙總與每日明細 CSV/Excel 格式化匯出、敏感個資匯出審計）
- [x] 智慧 GPS 經緯度地理圍欄打卡與外勤/遠端判定（Geofence Engine：Haversine 距離計算演算法、台北總部/自訂半徑判定、內勤辦公室/外勤遠端自動標籤、出勤明細 Google Maps 定位連結、團隊出勤狀態同步）
- [x] 考勤打卡管理後台與超出半徑強制填寫事由機制（Attendance Settings & Strict Reason Modal：管理員/HR專屬後台視覺化配置公司名稱、詳細地址、經緯度座標與允許打卡半徑；GPS 一鍵定位填入；支援未設定/清空公司位置即自動啟用「全遠端自由打卡」模式、無需設定距離且不限制事由；動態 SystemSetting 快取存儲即時生效；超出半徑打卡前後端雙層嚴格阻擋未填事由；彈出專屬事由輸入 Modal 且未填寫事由一律不給打卡；全系統 123 項測試 100% 通過）
- [x] 行事曆與會議室借用管理（會議室管理、防衝突排程、日曆預約、超額人數校驗）
- [x] 企業全景綜合行事曆看板（Calendar Hub：整合會議室借用、同仁休假/出勤單據、企業重大公告日程，支援月曆切換、三色事件標籤、彈窗明細與部門篩選）
- [x] 電子簽核公文單據正式列印與 PDF 存證匯出（Print-Ready View：A4 版型、公文編號、申請資訊、審核簽署鏈歷程、電子核准印章、列印防偽留痕與 AuditLog 審計）
- [x] 電子表單檢附證明文件與附件安全上傳/下載系統（支援請假就醫證明、公文單據等多檔上傳、10MB 限制與類型校驗、防越權 IDOR 下載保護、A4 列印存證留痕與 AuditLog 下載稽核）
- [x] 多層級簽核與條件分支流程引擎（Workflow Engine：支援依請假天數 >3 天動態追加人資複核、報銷金額 >=10,000 元追加財務/管理員複核、巨額報支 >=50,000 總經理決行；前端響應式 Stepper 進度管線、公文列印多級印章鏈、自訂表單多級簽核模式與 111 項測試 100% 通過）

## 階段 3：資產管理與生態整合
- [x] 企業文件庫與檔案版本控制（分類資料夾、文件上傳、版本歷程回溯、下載權限控管、密件隔離）
- [x] 企業文件庫線上安全預覽引擎（支援 PDF 高解析度翻頁內嵌、圖片/文字檔直接預覽、Office 文件引導、新分頁全螢幕開啟與 AuditLog 預覽稽核）
- [x] 頂部導覽列 (Header Bar) 自適應排版重構（修復字元跑版擠壓、響應式間距優化、管理員專屬項目收納為系統管理 Dropdown）
- [x] 系統儀表板（待辦統計、未讀公告、進行中申請、即將到來會議、快捷打卡）
- [x] 站內通知中心與推播通知整合（Laravel Notifications、導覽列小鈴鐺即時未讀數與下拉預覽、簽核/會議室/公告事件即時發送、一鍵全讀）
- [x] 外部通訊群組 Webhook 整合生態（Slack / Discord / Teams 即時推播、HMAC-SHA256 數位簽章防偽、雙向跨平台 Payload 相容、連線 Ping 測試與開關切換）
- [x] 系統審計稽核日誌 (Audit Trail) 與安全性加固（操作人員、IP、動作分類、變更詳情 JSON、管理員專屬查詢篩選）

## 階段 4：測試與正式上線
- [x] 單元與整合測試（EipTest, MultilevelWorkflowTest, DelegationTest, FormPrintTest, CalendarTest, GeofencingAttendanceTest, AttendanceSettingTest, SecurityAndIdorTest 等 123 項 Feature 測試 100% 通過，511 assertions）
- [x] 權限越權檢查 (IDOR) 與安全性稽核（FormRequest canAccess 授權機制、草稿公告隔離、會議室/文件權限邊界校驗、公文列印調閱授權校驗、多級流程跨關卡搶審防護）
- [x] 容器化 (Docker) 與 CI/CD 自動化建置（Docker Compose 容器編排 + GitHub Actions 自動化測試流程 `.github/workflows/ci.yml`）
- [x] 前端 UI 視覺精緻化（移除雜亂 Emoji，全面替換為企業級 SVG 向量圖示與專業格式標籤）
