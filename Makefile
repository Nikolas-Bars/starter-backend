COMPOSE        = docker compose
PHP_CONTAINER  = php
PHP_NAME       = starter-api-php
EXEC           = $(COMPOSE) exec -T $(PHP_CONTAINER)
EXEC_TTY       = $(COMPOSE) exec $(PHP_CONTAINER)

.DEFAULT_GOAL := help
.PHONY: help start env up down restart rebuild logs refresh seed migrate cache-clear copy-vendor \
        lint lint-check rector phpstan test coverage check swagger shell composer artisan

help:
	@echo "Доступные команды:"
	@echo "  start        - Первое разворачивание: .env, сборка, запуск, миграции, моковые пользователи"
	@echo "  up / down    - Запуск / остановка контейнеров"
	@echo "  restart      - Перезапуск контейнеров"
	@echo "  rebuild      - Пересборка образа (после изменения composer.json или Dockerfile)"
	@echo "  logs         - Логи PHP-контейнера (RoadRunner, очередь, планировщик)"
	@echo "  migrate      - Применить миграции"
	@echo "  refresh      - Пересоздать БД и заново залить моковых пользователей"
	@echo "  cache-clear  - Очистка кэша Laravel"
	@echo "  lint         - php-cs-fixer (исправляет)"
	@echo "  lint-check   - php-cs-fixer (только проверка)"
	@echo "  rector       - Rector (dry-run)"
	@echo "  phpstan      - PHPStan level 10 + правила проекта"
	@echo "  test         - PHPUnit (Unit + Feature, SQLite in-memory)"
	@echo "  coverage     - Покрытие тестами (pcov)"
	@echo "  check        - Полная проверка: lint-check + rector + phpstan + test"
	@echo "  swagger      - Перегенерировать OpenAPI-документацию"
	@echo "  shell        - Войти в PHP-контейнер"
	@echo "  composer     - Composer в контейнере: make composer cmd='require vendor/package'"
	@echo "  artisan      - Artisan в контейнере: make artisan cmd='route:list'"

# .env создаётся из .env.example, APP_KEY генерируется на хосте — PHP на маке не нужен
env:
	@if [ ! -f .env ]; then \
		cp .env.example .env; \
		key="base64:$$(openssl rand -base64 32)"; \
		perl -pi -e "s|^APP_KEY=.*|APP_KEY=$$key|" .env; \
		echo ".env создан, APP_KEY сгенерирован"; \
	else \
		echo ".env уже существует"; \
	fi

start: env
	@echo "Сборка образа..."
	@$(COMPOSE) build
	@echo "Запуск контейнеров (ждём healthcheck БД и Redis)..."
	@$(COMPOSE) up -d --wait
	@$(EXEC) php artisan package:discover --ansi
	@$(EXEC) php artisan migrate:fresh --seed --force
	@$(EXEC) php artisan l5-swagger:generate
	@$(MAKE) --no-print-directory copy-vendor
	@echo ""
	@echo "Готово!"
	@echo "  API:      http://localhost:$${APP_PORT:-8090}/api"
	@echo "  Swagger:  http://localhost:$${APP_PORT:-8090}/api/documentation"
	@echo "  Вход:     admin@example.com / Password123"

up:
	@$(COMPOSE) up -d --wait

down:
	@$(COMPOSE) down

restart:
	@$(COMPOSE) restart

rebuild:
	@$(COMPOSE) build
	@$(COMPOSE) up -d --wait --force-recreate
	@$(EXEC) php artisan package:discover --ansi
	@$(EXEC) php artisan migrate --force
	@$(MAKE) --no-print-directory copy-vendor

logs:
	@$(COMPOSE) logs -f $(PHP_CONTAINER)

migrate:
	@$(EXEC) php artisan migrate --force

refresh:
	@$(EXEC) php artisan migrate:fresh --seed --force
	@$(MAKE) --no-print-directory cache-clear

seed:
	@$(EXEC) php artisan db:seed --force

cache-clear:
	@$(EXEC) php artisan optimize:clear

# vendor из образа копируется на хост только ради автодополнения в IDE
copy-vendor:
	@echo "Копирование vendor из контейнера для IDE..."
	@rm -rf vendor && docker cp $(PHP_NAME):/var/www/html/vendor ./vendor > /dev/null

lint:
	@$(EXEC) composer lint

lint-check:
	@$(EXEC) composer lint-check

rector:
	@$(EXEC) composer rector

phpstan:
	@$(EXEC) composer phpstan

test:
	@$(EXEC) composer test

coverage:
	@$(EXEC) php -dpcov.enabled=1 vendor/bin/phpunit --coverage-text

check:
	@$(MAKE) --no-print-directory lint-check
	@$(MAKE) --no-print-directory rector
	@$(MAKE) --no-print-directory phpstan
	@$(MAKE) --no-print-directory test
	@echo "Проверка успешно завершена."

swagger:
	@$(EXEC) php artisan l5-swagger:generate

shell:
	@$(EXEC_TTY) bash

composer:
	@$(EXEC) composer $(cmd)

artisan:
	@$(EXEC) php artisan $(cmd)
