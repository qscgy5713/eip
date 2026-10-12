.PHONY: help init up down restart build ps logs bash test migrate migrate-fresh seed cache-clear npm-dev npm-build backup db-restore

# 預設目標顯示說明
.DEFAULT_GOAL := help

# 顏色設定
CYAN  := $(shell printf '\033[36m')
GREEN := $(shell printf '\033[32m')
RESET := $(shell printf '\033[0m')

##@ 說明與資訊
help: ## 顯示所有可用的 Makefile 指令與說明
	@echo "$(CYAN)EIP 企業資訊入口網 - 開發維運指令集$(RESET)\n"
	@awk 'BEGIN {FS = ":.*##"} /^[a-zA-Z_-]+:.*?##/ { printf "  $(GREEN)%-16s$(RESET) %s\n", $$1, $$2 } /^##@/ { printf "\n$(CYAN)%s$(RESET)\n", substr($$0, 5) }' $(MAKEFILE_LIST)

##@ 專案初始化與建置
init: ## 完整初次建置與啟動（建置映像檔、啟動容器、跑遷移與假資料）
	@echo "$(CYAN)正在初始化 EIP 系統...$(RESET)"
	@docker compose up -d --build
	@docker compose exec app php artisan migrate --force
	@docker compose exec app php artisan db:seed --force
	@npm run build
	@echo "$(GREEN)初始化完成！請訪問 http://localhost:8000$(RESET)"

build: ## 重新建置 Docker 容器映像檔
	docker compose build

##@ 容器服務管理
up: ## 啟動所有 Docker 容器（背景執行）
	docker compose up -d

down: ## 停止並移除所有容器服務
	docker compose down

restart: ## 重新啟動所有容器服務
	docker compose restart

ps: ## 查看當前容器運行狀態與連接埠
	docker compose ps

logs: ## 查看所有容器的即時輸出日誌 (Ctrl+C 離開)
	docker compose logs -f

app-logs: ## 查看 app (PHP-FPM) 容器日誌
	docker compose logs -f app

web-logs: ## 查看 web (Nginx) 容器日誌
	docker compose logs -f web

db-logs: ## 查看 db (PostgreSQL) 容器日誌
	docker compose logs -f db

bash: ## 進入 app 容器互動式終端機
	docker compose exec -it app bash

##@ 應用程式與資料庫 (Laravel)
migrate: ## 執行資料庫遷移
	docker compose exec app php artisan migrate

migrate-fresh: ## 重設資料庫並重新填入假資料 (注意: 會清空現有資料)
	docker compose exec app php artisan migrate:fresh --seed

seed: ## 重新執行 EIP 假資料填充
	docker compose exec app php artisan db:seed

test: ## 執行 PHPUnit 自動化測試套件
	docker compose exec app php artisan test

cache-clear: ## 清理所有 Laravel 快取 (配置、路由、檢視表)
	docker compose exec app php artisan optimize:clear

tinker: ## 進入 Laravel Tinker 互動控制台
	docker compose exec -it app php artisan tinker

##@ 前端資源管理 (Vite & Vue)
npm-dev: ## 啟動 Vite 前端熱重載開發伺服器
	npm run dev

npm-build: ## 編譯前端生產環境靜態資產 (Vue / CSS)
	npm run build

##@ 備份與還原
BACKUP_DIR ?= backups
BACKUP_KEEP ?= 14

backup: ## 備份 PostgreSQL 與上傳檔案到 backups/(保留最近 14 份,可用 BACKUP_KEEP 調整)
	@mkdir -p $(BACKUP_DIR)
	@ts=$$(date +%Y%m%d_%H%M%S); \
	docker compose exec -T db sh -c 'pg_dump -U "$$POSTGRES_USER" "$$POSTGRES_DB"' | gzip > $(BACKUP_DIR)/eip_db_$$ts.sql.gz && \
	test -s $(BACKUP_DIR)/eip_db_$$ts.sql.gz && gzip -t $(BACKUP_DIR)/eip_db_$$ts.sql.gz && \
	tar -czf $(BACKUP_DIR)/eip_files_$$ts.tar.gz -C storage/app . && \
	echo "$(GREEN)備份完成:$(BACKUP_DIR)/eip_db_$$ts.sql.gz、$(BACKUP_DIR)/eip_files_$$ts.tar.gz$(RESET)"
	@ls -1t $(BACKUP_DIR)/eip_db_*.sql.gz 2>/dev/null | tail -n +$$(($(BACKUP_KEEP)+1)) | xargs -I{} rm -f {}
	@ls -1t $(BACKUP_DIR)/eip_files_*.tar.gz 2>/dev/null | tail -n +$$(($(BACKUP_KEEP)+1)) | xargs -I{} rm -f {}

db-restore: ## 從備份還原資料庫(會覆蓋現有資料!用法:make db-restore FILE=backups/eip_db_xxx.sql.gz CONFIRM=yes)
	@test -n "$(FILE)" || (echo "請指定 FILE=備份檔路徑" && exit 1)
	@test -f "$(FILE)" || (echo "找不到檔案 $(FILE)" && exit 1)
	@test "$(CONFIRM)" = "yes" || (echo "這會覆蓋現有資料庫。確認要還原請加上 CONFIRM=yes" && exit 1)
	@docker compose exec -T db sh -c 'psql -U "$$POSTGRES_USER" -d postgres -c "DROP DATABASE IF EXISTS \"$$POSTGRES_DB\" WITH (FORCE)" -c "CREATE DATABASE \"$$POSTGRES_DB\" OWNER \"$$POSTGRES_USER\""'
	@gunzip -c "$(FILE)" | docker compose exec -T db sh -c 'psql -q -o /dev/null -v ON_ERROR_STOP=1 -U "$$POSTGRES_USER" -d "$$POSTGRES_DB"'
	@echo "$(GREEN)還原完成。$(RESET)"
