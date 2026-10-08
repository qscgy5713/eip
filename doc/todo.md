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
- [x] 企業公告發布管理、官方附件檔案上傳與安全下載系統 (Announcement Publishing & Attachments Engine：管理員/主管前端發布公告 Modal、多檔附件上傳與單檔 10MB 校驗、公告置頂與緊急度選擇、草稿/正式發布切換、草稿公告嚴格權限隔離防洩露、在職同仁安全下載官方附件、下載稽核日誌 AuditLog 留痕、刪除公告實體檔案自動清理、重要公告連鎖全員系統通知與 Webhook 推播，全系統 189 項 Feature 測試 100% 通過，1032 assertions)
- [x] 員工通訊錄與組織架構全景圖（Directory 查詢與篩選、支援切換「同仁通訊名冊」與「企業組織架構全景圖」唯讀畫布、即時關鍵字模糊搜尋、節點折疊收合、縮放控制與唯讀同仁抽屜）
- [x] 組織架構與員工維護管理後台 (Org Chart & Employee Management Hub：視覺化部門樹狀階層 CRUD、父子隸屬、主管指派、防刪保護、員工帳號建立/異動、自動休假額度初始化、在職/停權/離職狀態切換與登入阻擋防護、重設密碼、全系統 165 項 Feature 測試 100% 通過)
- [x] 互動式組織架構圖、拖曳階層、主管一鍵指派與編制匯出系統 (Interactive Drag-and-Drop Org Chart & Member Engine：一鍵無縫切換【互動視覺組織樹】與【階層清單總覽】雙重視圖、支援原生 HTML5 拖曳部門節點動態調整父子階層與直屬隸屬關係、頂部一級公司直屬部門放置區 Drop Zone、前後端雙層深度遞迴循環依賴防呆阻擋、卡片節點快捷建立子部門/編輯/安全防刪、畫布即時關鍵字模糊搜尋高亮脈衝、主管一鍵指派/解除並自動同步部門隸屬、直屬人數與轄下全體子孫部門總編制統計計算、非原生自訂 Combobox 下拉組件「支援輸入姓名、Email 帳號或工號即時過濾選取同仁」、全公司組織與人員編制表匯出 CSV (支援 UTF-8 BOM Excel 相容與 AuditLog 審計)、樹狀畫布 60%~140% 縮放與一鍵展開/收合控制、全系統 165 項 Feature 測試 100% 通過)

## 階段 2：行政流程與協同工作
- [x] 電子表單簽核工作流引擎（請假單、報銷單、加班單、補打卡單、請購單、差旅單等動態 JSON Schema 欄位）
- [x] 自訂動態表單設計器（Custom Form Builder：支援管理員/主管自由配置欄位型態、選項與驗證，建立後全員立即套用發起申請）
- [x] 主管審批與簽核歷程記錄機制（Approval Records、核准/駁回附言、單據狀態即時更新）
- [x] 簽核職務代理人機制（Delegation Engine：出差休假代簽人設定、生效期間自動判定、代理待審通知連鎖、歷程代簽標籤與 IDOR 權限加固）
- [x] 打卡考勤系統與 HR 月報結算（GPS/IP 限制、打卡紀錄、異常判定、主管團隊出勤、月度考勤統計看板、月度彙總與每日明細 CSV/Excel 格式化匯出、敏感個資匯出審計）
- [x] 智慧 GPS 經緯度地理圍欄打卡與外勤/遠端判定（Geofence Engine：Haversine 距離計算演算法、台北總部/自訂半徑判定、內勤辦公室/外勤遠端自動標籤、出勤明細 Google Maps 定位連結、團隊出勤狀態同步）
- [x] 考勤打卡管理後台與超出半徑強制填寫事由機制（Attendance Settings & Strict Reason Modal：管理員/HR專屬後台視覺化配置公司名稱、詳細地址、經緯度座標與允許打卡半徑；GPS 一鍵定位填入；支援未設定/清空公司位置即自動啟用「全遠端自由打卡」模式、無需設定距離且不限制事由；動態 SystemSetting 快取存儲即時生效；超出半徑打卡前後端雙層嚴格阻擋未填事由；彈出專屬事由輸入 Modal 且未填寫事由一律不給打卡；全系統 123 項測試 100% 通過）
- [x] 行事曆與會議室借用管理（會議室管理、防衝突排程、日曆預約、超額人數校驗）
- [x] 會議室與會同仁邀請、設備需求借用與行事曆/工作台連鎖系統 (Meeting Attendees Invitation & Equipment Engine：預約時即時模糊搜尋邀請多位在職同仁、自動人數下限聯動、會議室設備需求借用勾選、受邀同仁連鎖站內會議邀請通知、取消會議連鎖取消通知、個人工作台 Dashboard 即將開始會議行程自動納入受邀會議並標示主辦/受邀狀態、綜合行事曆 Calendar 聚合與會同仁名冊與設備標籤，全系統 195 項 Feature 測試 100% 通過，1092 assertions)
- [x] 企業全景綜合行事曆看板（Calendar Hub：整合會議室借用、同仁休假/出勤單據、企業重大公告日程，支援月曆切換、三色事件標籤、彈窗明細與部門篩選）
- [x] 電子簽核公文單據正式列印與 PDF 存證匯出（Print-Ready View：A4 版型、公文編號、申請資訊、審核簽署鏈歷程、電子核准印章、列印防偽留痕與 AuditLog 審計）
- [x] 電子表單檢附證明文件與附件安全上傳/下載系統（支援請假就醫證明、公文單據等多檔上傳、10MB 限制與類型校驗、防越權 IDOR 下載保護、A4 列印存證留痕與 AuditLog 下載稽核）
- [x] 多層級簽核與條件分支流程引擎（Workflow Engine：支援依請假天數 >3 天動態追加人資複核、報銷金額 >=10,000 元追加財務/管理員複核、巨額報支 >=50,000 總經理決行；前端響應式 Stepper 進度管線、公文列印多級印章鏈、自訂表單多級簽核模式與 111 項測試 100% 通過）
- [x] 特休與假別額度管理系統 (Leave Balance & Quota Engine：支援同仁查閱個人特休/病事假/補休剩餘天數與年度配額、HR/Admin 跨同仁額度管理與一鍵年度初始化、請假單填寫即時額度顯示與超額防呆、提單審批流程與 pending/used 扣減連鎖機制、全系統 131 項測試 100% 通過)
- [x] 主管審批中心與一鍵批次簽核系統 (Approvals Hub & Batch Approve Engine：集中管理所有待審單據、待審指標統計卡片、表單種類/代理/同仁多維篩選、多選 Checkbox 批次核准/駁回、審批批註附言、自動多級關卡流轉與休假額度連鎖處理、防越級搶審安全機制、全套自動化測試 139 項 100% 通過)
- [x] 表單簽核協同加簽 (Add-Sign) 與轉簽派審 (Forward/Transfer) 系統 (Collaborative Workflow Engine：支援主管審批時全權轉簽給新主管決行並保留交接軌跡、臨時跨部門同仁或專家會辦加簽邀請、加簽不影響主流程關卡推進與結案生命週期、會辦完成意見自動通知並回流主審主管、防重複加簽與防轉/加簽給自己邊界防呆、多筆待辦情境優先精準匹配指派記錄、全系統 155 項 Feature 測試 100% 通過)
- [x] 忘刷/補打卡單結案自動同步考勤紀錄引擎 (Attendance Regularization Sync Engine：同仁提交「忘刷/補打卡單 CLOCK_ADJUST」經多級簽核核准結案時，系統自動查找或建立該員工當日 attendances 紀錄，智慧解析出勤時間，補齊上班或下班打卡時間，重新計算工時並將出勤狀態校正為 normal 正常出勤，寫入單號備註與 AuditLog 審計留痕，考勤首頁提供未簽退/遲到早退異常一鍵發起補打卡與日期自動預填)
- [x] 休假管理中心與請假歷史對帳表系統 (Leave Balance Ledger & Apply Link：在休假額度中心 LeaveBalances/Index.vue 提供「發起請假申請」快捷按鈕與關聯表單，並於額度卡片下方建立「我的請假申請與扣抵明細對帳表」，完整列出近期請假單號、假別類型、請假期間、天數、事由、審批折抵狀態與單據詳情連結)
- [x] 加班單核准結案自動折算補休額度引擎 (Overtime to Compensatory Leave Credit Engine：同仁提交「加班申請單 OVERTIME」選擇「換取補休時數」經多級簽核終審結案時，系統自動以法定 8 小時 = 1 天標準工時將加班時數精準折算為天數，自動在 leave_balances 累加補休 compensatory 額度、註記加班單號留痕、發送系統通知並記錄 AuditLog 審計日誌；休假中心整合「請假支出扣額」與「加班換補休入帳」雙頁籤對帳存摺)
- [x] 表單申請主動撤回與作廢機制 (Form Request Withdrawal & Re-apply Engine：申請人本人或管理員於待審中一鍵撤回單據、請假額度自動 100% 釋放恢復、進行中待審記錄標記作廢、通知原審核主管、留存 AuditLog 與 Webhook 推播，並提供「複製重新申請」自動預填原單據欄位功能，全系統 182 項 Feature 測試 100% 通過)

## 階段 3：資產管理與生態整合
- [x] 企業文件庫與檔案版本控制（分類資料夾、文件上傳、版本歷程回溯、下載權限控管、密件隔離）
- [x] 企業文件知識庫中繼資料與密件權限配置管理 (Document Metadata & Restricted Roles Management：支援文件上傳者、主管與管理員線上即時維護文件標題、所屬分類、歸屬部門、備註說明，並支援動態配置密件存取角色白名單 restricted_roles，非授權職級同仁嚴格隔離防洩，包含 AuditLog 審計留痕與 IDOR 防越權更新校驗)
- [x] iCalendar (.ics / RFC 5545) 標準行事曆匯出引擎 (iCalendar Export Engine：支援會議室單筆預約匯出 meeting-{id}.ics 附帶 VEVENT、ORGANIZER 與 ATTENDEE 邀請名冊，以及全景綜合行事曆依月份全量匯出 eip-calendar-{month}.ics，無縫聚合會議時段、請假差勤全天事件與企業正式公告日程，支援分類與部門多維篩選，可直接一鍵匯入 Google 日曆、Apple Calendar 與 Microsoft Outlook，全系統 202 項 Feature 測試 100% 通過，1133 assertions)
- [x] 企業文件庫線上安全預覽引擎（支援 PDF 高解析度翻頁內嵌、圖片/文字檔直接預覽、Office 文件引導、新分頁全螢幕開啟與 AuditLog 預覽稽核）
- [x] 頂部導覽列 (Header Bar) 自適應排版重構（修復字元跑版擠壓、響應式間距優化、管理員專屬項目收納為系統管理 Dropdown）
- [x] 系統儀表板與個人工作台全景升級 (Dashboard & Daily Workspace Hub：待我審批無縫納入職務代理主管單據並醒目標示代理標籤、個人休假與補休可用額度摘要小卡、主管/人事/管理員專屬團隊今日出勤快報卡、五大高頻行政快捷導航工作列、即時會議提醒、全系統 182 項 Feature 測試 100% 通過)
- [x] 站內通知中心與推播通知整合（Laravel Notifications、導覽列小鈴鐺即時未讀數與下拉預覽、簽核/會議室/公告事件即時發送、一鍵全讀）
- [x] 外部通訊群組 Webhook 整合生態（Slack / Discord / Teams 即時推播、HMAC-SHA256 數位簽章防偽、雙向跨平台 Payload 相容、連線 Ping 測試與開關切換）
- [x] 系統審計稽核日誌 (Audit Trail) 與安全性加固（操作人員、IP、動作分類、變更詳情 JSON、管理員專屬查詢篩選）

## 階段 4：測試與正式上線
- [x] 單元與整合測試（EipTest, MultilevelWorkflowTest, DelegationTest, FormPrintTest, CalendarTest, GeofencingAttendanceTest, AttendanceSettingTest, AttendanceAmendmentSyncTest, OvertimeCompensatoryCreditTest, SecurityAndIdorTest, OrgManagementTest, FormCollaborativeApprovalTest, LeaveBalanceTest, WebhookTest, DashboardTest, FormWithdrawalTest, AnnouncementManagementTest, MeetingRoomAttendeesAndEquipmentTest, DocumentUpdateAndIcsExportTest 等 202 項 Feature 測試 100% 通過，1133 assertions）
- [x] 權限越權檢查 (IDOR) 與安全性稽核（FormRequest canAccess 授權機制、草稿公告隔離、會議室/文件權限邊界校驗、公文列印調閱授權校驗、多級流程跨關卡搶審防護）
- [x] 容器化 (Docker) 與 CI/CD 自動化建置（Docker Compose 容器編排 + GitHub Actions 自動化測試流程 `.github/workflows/ci.yml`）
- [x] 前端 UI 視覺精緻化（移除雜亂 Emoji，全面替換為企業級 SVG 向量圖示與專業格式標籤）
