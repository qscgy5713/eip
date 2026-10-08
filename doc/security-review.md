# EIP 系統企業級全方位資安審查報告 (Comprehensive Security Code Review)

> **審查日期**：2026-10-08  
> **審查範疇**：認證授權體系、IDOR 防護、檔案存取邊界、注入防禦 (SQLi/XSS/CSV)、SSRF、競態條件與業務邏輯漏洞  
> **系統架構**：Laravel 12 (PHP 8.4) + Inertia.js (Vue 3) + PostgreSQL 16 + Redis + Nginx  

---

## 執行摘要 (Executive Summary)

本專案整體具備非常優秀的企業級防禦縱深（Defense-in-Depth），全系統 100% 採用 Eloquent ORM 參數化查詢杜絕 SQL 注入，前端 Vue 3 純文字節點全面免除 XSS 風險，IDOR 檢查落實於考勤、公文調閱與列印。

然而，經深度靜態程式碼審查（Static Code Analysis）與架構關聯比對，發現以下 **7 項主要資安弱點與改善要點**，依風險等級分類如下：

| 編號 | 弱點項目 | 風險等級 | 影響範疇 | 修復難度 |
| :---: | :--- | :---: | :--- | :---: |
| **SEC-01** | 機密證明文件與未公開草稿附件存於公開根目錄 (Data Exposure via Public Disk) | **高 (High)** | 表單附件、公告草稿附件 | 低 |
| **SEC-02** | 跨物件歷史版本調閱越權 (Scoped Route Model Binding IDOR) | **高 (High)** | 企業文件庫 (`DocumentController`) | 低 |
| **SEC-03** | 缺乏全域活躍會話即時撤銷中介層 (Active Session Revocation) | **中 (Medium)** | 停權/離職帳號會話管理 | 低 |
| **SEC-04** | 人資帳號可直接建立最高權限管理員 (Privilege Escalation in User Creation) | **中 (Medium)** | 組織人員管理 (`OrgManagementController`) | 低 |
| **SEC-05** | 請假額度並發競態條件透支 (Concurrency TOCTOU in Leave Balance) | **中 (Medium)** | 休假簽核引擎 (`WorkflowService`) | 中 |
| **SEC-06** | Webhook 端點缺乏內網與私有 IP 限制 (Potential SSRF) | **中 (Medium)** | 外部生態整合 (`WebhookService`) | 低 |
| **SEC-07** | 報表匯出未過濾公式字元 (CSV Formula Injection) | **低 (Low)** | 考勤月報、組織名冊匯出 | 低 |

---

## 詳細弱點剖析與修復建議

### 🔴 SEC-01：機密證明文件與草稿附件存於公開根目錄 (High)
- **弱點位置**：
  - `app/Http/Controllers/FormRequestController.php` (`$file->store('form_attachments', 'public')`)
  - `app/Http/Controllers/AnnouncementController.php` (`$file->store('announcement_attachments', 'public')`)
- **風險成因**：
  - 系統將員工上傳之請假病歷診斷證明、公假證明、敏感報銷發票，以及尚未公開之草稿公告附件儲存在 `public` disk（對應 `/storage/...`）。
  - Nginx 靜態檔案處理規則 `location / { try_files $uri ...; }` 會在請求命中靜態檔案時直接回傳，繞過 Laravel Controller 中的 `canAccess` 權限檢查。
  - 若檔案路徑被枚舉或洩漏，未授權訪客可直接透過瀏覽器下載機密個資與公文佐證。
- **修復方針**：
  1. 將表單附件與機密公告附件改為儲存於預設私有磁碟（`local` disk，即 `storage/app/private_attachments`），外部 Nginx 無法直接存取。
  2. 下載時統一透過 Controller 經由授權檢查後以 `Storage::disk('local')->download(...)` 串流輸出。

---

### 🔴 SEC-02：跨物件歷史版本調閱越權 (Scoped Route Model Binding IDOR) (High)
- **弱點位置**：
  - `app/Http/Controllers/DocumentController.php` 中的 `download()` 與 `preview()`
  - 路由定義：`/documents/{document}/download/{version?}` 與 `/documents/{document}/preview/{version?}`
- **風險成因**：
  - 方法簽章：`public function download(Request $request, Document $document, ?DocumentVersion $version = null)`。
  - 權限檢查為：`$document->canAccess($request->user())`，只檢查了傳入的 `$document`。
  - 若攻擊者傳入其有權存取的 `$document` (ID=1)，但指定無權限之機密文件所屬的 `$version` (ID=99)，由於缺少版本與主文件的隸屬比對，系統會將 `$targetVersion` 指向 ID=99 的機密實體檔案並直接下載，形成跨物件 IDOR 繞過。
- **修復方針**：
  - 在 `download` 與 `preview` 方法開頭加入關聯驗證：
    ```php
    if ($version && $version->document_id !== $document->id) {
        abort(404, '指定的版本記錄不屬於此文件。');
    }
    ```

---

### 🟡 SEC-03：缺乏全域活躍會話即時撤銷中介層 (Active Session Revocation) (Medium)
- **弱點位置**：
  - `app/Http/Requests/Auth/LoginRequest.php` vs. `routes/web.php` 全域中介層
- **風險成因**：
  - 目前帳號狀態驗證僅存在於 `LoginRequest::authenticate()`。
  - 當使用者已登入時，若 HR 或管理員於後台將其標記為 `suspended`（停權）或 `resigned`（離職），在該同仁 Session 到期或主動登出前，Laravel 內建的 `auth` 中介層仍視其為合法登入者，該同仁仍可持續瀏覽內部機密資訊與發送請求。
- **修復方針**：
  - 新增 `EnsureUserIsActive` 中介層或於 `HandleInertiaRequests` 中檢測：
    ```php
    if (Auth::check() && Auth::user()->status !== 'active') {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login')->withErrors(['email' => '您的帳號已停用或離職，無法存取系統。']);
    }
    ```

---

### 🟡 SEC-04：人資帳號可直接建立最高權限管理員 (Privilege Escalation) (Medium)
- **弱點位置**：
  - `app/Http/Controllers/OrgManagementController.php` (`storeUser`)
- **風險成因**：
  - 在 `updateUser` 中有嚴密防禦：`if (!$operator->isAdmin() && ($user->isAdmin() || $request->input('role') === 'admin')) abort(403);`。
  - 但在 `storeUser` 中，驗證規則為 `'role' => 'required|string|in:admin,manager,employee,hr'`，且沒有阻擋非 Admin 的 HR 使用者將新帳號設定為 `admin`。
  - HR 帳號可透過建立新使用者直接取得一個新的 Admin 帳號，造成水平越權升級為垂直越權。
- **修復方針**：
  - 在 `storeUser` 加入與 `updateUser` 一致的角色權限檢驗：
    ```php
    if (!$operator->isAdmin() && $validated['role'] === 'admin') {
        abort(403, '僅系統管理員有權指派管理員身分。');
    }
    ```

---

### 🟡 SEC-05：請假額度並發競態條件透支 (Concurrency TOCTOU) (Medium)
- **弱點位置**：
  - `app/Http/Controllers/FormRequestController.php` (`store`)
  - `app/Services/LeaveBalanceService.php` (`checkAvailability` 與 `holdBalance`)
- **風險成因**：
  - 送單時先呼叫 `checkAvailability`，隨後建立 `FormRequest`，最後才呼叫 `holdBalance`。
  - 這兩步沒有包覆在同一個資料庫交易 (`DB::transaction`) 中，且沒有使用悲觀排他鎖 (`lockForUpdate()`)。
  - 同仁若利用並發工具同時發送 2 筆申請，兩筆都會通過可用額度檢查，最終造成額度扣減透支（超額請假）。
- **修復方針**：
  - 將休假額度檢查與凍結封裝至原子交易中，並對 `LeaveBalance` 記錄加上 `lockForUpdate()`。

---

### 🟡 SEC-06：Webhook 端點缺乏內網與私有 IP 限制 (Potential SSRF) (Medium)
- **弱點位置**：
  - `app/Http/Controllers/WebhookController.php` (`store`)
  - `app/Services/WebhookService.php` (`send`)
- **風險成因**：
  - Webhook URL 僅驗證了 `'url' => ['required', 'url', 'max:500']`。
  - 若管理員輸入 `http://127.0.0.1:xxx`、`http://169.254.169.254`（雲端中繼資料）或內部服務（`http://db:5432`, `http://redis:6379`），系統伺服器會向內部網路發起 HTTP 請求，引發 SSRF 探測風險。
  - 此外，`Webhook` 模型未隱藏 `secret`，使簽章密鑰直接傳至前端。
- **修復方針**：
  - 在儲存或發送時驗證主機 IP，禁止解析為私有網段（`10.0.0.0/8`, `172.16.0.0/12`, `192.168.0.0/16`, `127.0.0.0/8`, `169.254.0.0/16`）。
  - 在 `Webhook` 模型中加入 `protected $hidden = ['secret'];`。

---

### 🟢 SEC-07：報表匯出未過濾公式字元 (CSV Formula Injection) (Low)
- **弱點位置**：
  - `AttendanceReportController::exportSummary`、`AttendanceReportController::exportDetails`
  - `OrgManagementController::exportRoster`
- **風險成因**：
  - 匯出 CSV 時直接寫入員工姓名、工號、部門名稱。
  - 若內容以 `=`, `+`, `-`, `@`, `\t` 開頭，Excel 或試算表開啟時可能將文字解析為公式並執行外部指令。
- **修復方針**：
  - 實作安全的 CSV 單元格跳脫函式，凡是以 `=, +, -, @` 開頭的文字欄位，自動前綴單引號 `'`。

---

## 總結與防禦成效評估

EIP 目前已具備健全的角色權限與審計體系。上述 7 項弱點均屬於企業級架構加固範疇。建議依照風險等級自高至低依序進行強化，打造零死角的企業入口網資安防線。
