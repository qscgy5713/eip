# EIP (Enterprise Information Portal) 企業資訊入口網

一個為現代企業打造的企業資訊入口網系統，整合組織架構權限、企業公告、線上簽核工作流、考勤打卡與內部資源管理。

---

## 專案簡介
EIP 旨在打破企業內部資訊孤島，提供一站式的行政與協作入口，支援多組織階層、細部權限管控 (RBAC) 與靈活的工作流簽核引擎。

### 目前已實作功能特色
- 🏢 **組織架構與 RBAC 角色**：支援多階層部門、四種角色權限體系 (`admin`, `manager`, `employee`, `hr`)。
- 📢 **企業公告系統**：置頂公告、緊急重要度分級、全體同仁閱覽追蹤與未讀紅點提示。
- 📋 **電子表單與審批工作流引擎**：請假單、報銷單、採購單，動態 JSON Schema 欄位與多關卡審核歷程。
- 👥 **同仁通訊錄**：跨部門搜尋、分機、職稱與公務 Email 檢視。
- ⏰ **考勤打卡與出勤月報**：即時上班/下班打卡、IP/地點記錄、工時自動計算、主管即時團隊出勤概況。
- 📅 **會議室借用與公用行事曆**：會議室設備管理、日期時段看板、防衝突排他性校驗、與會人數上限防呆、個人行程管理。

## 技術棧 (Tech Stack)
- **後端 (Backend)**: Laravel 12 (PHP 8.4-FPM)
- **前端 (Frontend)**: Inertia.js + Vue 3 + Tailwind CSS + Vite
- **資料庫 (Database)**: PostgreSQL 16 (原生 JSONB 支援動態表單欄位)
- **快取與訊息佇列 (Cache & Queue)**: Redis (Alpine)
- **Web 伺服器 (Web Server)**: Nginx (Alpine)
- **容器化 (Containerization)**: Docker Compose

## 目錄結構
```text
eip/
├── docker/              # Docker 服務設定 (PHP Dockerfile、Nginx 設定)
├── doc/                 # 專案規劃與過程文檔
│   ├── plan.md          # 系統規劃與實施藍圖
│   ├── todo.md          # 專案待辦清單
│   └── worklog.md       # 工作日誌
├── app/                 # Laravel 後端核心業務邏輯與模型
├── database/            # 遷移檔 (Migrations) 與假資料填充 (Seeders)
├── resources/js/        # Vue 3 前端頁面與 Inertia 組件
├── docker-compose.yml   # 容器編排設定 (app, web, db, redis)
├── Makefile             # 常用開發與維運快速指令集
└── README.md            # 專案說明文件
```

## 快速啟動 (Makefile 捷徑)

專案提供便捷的 `Makefile` 指令，可直接透過 `make <目標>` 操作：

| 指令 | 功能說明 |
| :--- | :--- |
| `make init` | **初次完整啟動**（建置映像檔、啟動容器、跑遷移與假資料） |
| `make up` | 啟動所有容器服務（背景執行） |
| `make down` | 停止並移除所有容器服務 |
| `make restart` | 重新啟動所有容器服務 |
| `make ps` | 查看當前容器運行狀態 |
| `make logs` | 即時監看所有容器日誌 |
| `make bash` | 進入 `app` (PHP-FPM) 容器命令列 |
| `make test` | 執行自動化測試套件 |
| `make migrate` | 執行資料庫遷移 |
| `make seed` | 重新填入 EIP 假資料 |
| `make npm-dev` | 啟動 Vite 前端熱重載開發伺服器 |
| `make help` | 查詢所有可用指令與說明 |

### 原生 Docker Compose 指令

### 1. 啟動所有容器服務
```bash
docker compose up -d
```
服務將在背景啟動：
- **Web 前台**：http://localhost:8000
- **PostgreSQL**：`localhost:5432` (使用者: `eip_user`, 資料庫: `eip_db`, 密碼: `eip_password`)
- **Redis**：`localhost:6379`

### 2. 執行資料庫遷移與假資料填充 (初次安裝)
```bash
docker compose exec app php artisan migrate --force
docker compose exec app php artisan db:seed --force
```

### 3. 前端熱重載 (開發時可選)
```bash
npm run dev
```
若無須熱重載，Nginx 已直接提供編譯完成之靜態資源。

---

## 預設測試帳號 (密碼皆為 `password`)
| 帳號 Email | 角色 | 姓名 | 所屬部門 |
| :--- | :--- | :--- | :--- |
| `admin@eip.local` | 系統管理員 / 總裁 | 王總裁 | 總經理室 |
| `manager@eip.local` | 主管 (具審批權限) | 張經理 | 研發工程部 |
| `employee@eip.local` | 一般員工 | 陳同仁 | 研發工程部 |
| `hr@eip.local` | 人資行政專員 | 林專員 | 人資行政部 |

---

## 執行自動化測試
```bash
docker compose exec app php artisan test
```

## 文檔索引
- [系統架構與實施規劃 (Plan)](file:///Users/yujuchen/www/eip/doc/plan.md)
- [專案待辦清單 (Todo)](file:///Users/yujuchen/www/eip/doc/todo.md)
- [開發工作日誌 (Worklog)](file:///Users/yujuchen/www/eip/doc/worklog.md)

---

## 開發規範
- 過程性/規劃性文件一律放置於 `doc/` 目錄。
- 開發與修改遵循嚴謹的安全性檢驗與 Code Review。
