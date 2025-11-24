##################
# Config
##################

APP_ENV ?= test
ENV_FILE = .env.$(APP_ENV)
COMPOSE_FILES = -f docker-compose.yml -f docker/config-envs/$(APP_ENV)/docker-compose.override.yml
DC = docker compose --env-file $(ENV_FILE) $(COMPOSE_FILES)

# Production config (без override)
DC_PROD = docker compose --env-file .env.prod

##################
# Docker Compose - Test/Dev
##################

.PHONY: build up down restart logs ps shell

build:
	$(DC) build

up:
	$(DC) up -d

down:
	$(DC) down -v

restart: down up

logs:
	$(DC) logs -f

ps:
	$(DC) ps

shell:
	$(DC) exec php bash

##################
# Docker Compose - Production
##################

.PHONY: prod-build prod-up prod-down prod-restart prod-logs

prod-build:
	$(DC_PROD) build

prod-up:
	$(DC_PROD) up -d

prod-down:
	$(DC_PROD) down -v

prod-restart: prod-down prod-up

prod-logs:
	$(DC_PROD) logs -f

##################
# Symfony Console (App Commands)
##################

.PHONY: console id-cross-var sheet-to-kafka kafka-to-sheet cache-clear

console:
	$(DC) exec php php bin/console $(CMD)

id-cross-var:
	$(DC) exec php php bin/console app:id-cross-var

sheet-to-kafka:
	$(DC) exec php php bin/console app:asin-sheet-to-kafka

kafka-to-sheet:
	$(DC) exec php php bin/console app:asin-kafka-to-sheet

cache-clear:
	$(DC) exec php php bin/console cache:clear

##################
# Testing
##################

.PHONY: test test-coverage test-html test-unit test-filter

test:
	$(DC) exec php vendor/bin/phpunit --colors=always --testdox

test-coverage:
	$(DC) exec php vendor/bin/phpunit --coverage-text --colors=always --testdox

test-html:
	$(DC) exec php vendor/bin/phpunit --coverage-html=var/coverage-report

test-unit:
	$(DC) exec php vendor/bin/phpunit tests/Unit/ --colors=always --testdox

# Usage: make test-filter FILTER=DataRowTest
test-filter:
	$(DC) exec php vendor/bin/phpunit --filter=$(FILTER) --colors=always --testdox

##################
# Logs (Debug)
##################

.PHONY: logs-id-cross-var logs-sheet-to-kafka logs-kafka-to-sheet logs-supervisor logs-php

logs-id-cross-var:
	$(DC) exec php tail -f /var/log/supervisor/id_cross_var.out.log

logs-id-cross-var-err:
	$(DC) exec php tail -f /var/log/supervisor/id_cross_var.err.log

logs-sheet-to-kafka:
	$(DC) exec php tail -f /var/log/supervisor/asin_sheet_to_kafka.out.log

logs-kafka-to-sheet:
	$(DC) exec php tail -f /var/log/supervisor/asin_kafka_to_sheet.out.log

logs-supervisor:
	$(DC) exec php tail -f /var/log/supervisord.log

logs-php:
	$(DC) exec php tail -f /var/log/php_errors.log

##################
# Composer
##################

.PHONY: composer-install composer-update composer-dump

composer-install:
	$(DC) exec php composer install

composer-update:
	$(DC) exec php composer update

composer-dump:
	$(DC) exec php composer dump-autoload

##################
# Supervisor
##################

.PHONY: supervisor-start supervisor-status supervisor-restart

supervisor-start:
	$(DC) exec php /usr/bin/supervisord -c /etc/supervisord.conf

supervisor-status:
	$(DC) exec php supervisorctl status

supervisor-restart:
	$(DC) exec php supervisorctl restart all

##################
# Setup & Init
##################

.PHONY: init setup

# First-time setup for test environment
init: build up composer-install
	@echo "Test environment initialized successfully!"

# Quick setup (assumes containers already exist)
setup: up
	@echo "Environment ready!"

##################
# Cleanup
##################

.PHONY: clean docker-prune

clean:
	$(DC) down -v --rmi local --remove-orphans

docker-prune:
	docker system prune -af --volumes
	docker builder prune -af

##################
# Help
##################

.PHONY: help

help:
	@echo "Usage: make [target] [APP_ENV=test|prod]"
	@echo ""
	@echo "Docker (Test/Dev):"
	@echo "  build              Build containers"
	@echo "  up                 Start containers"
	@echo "  down               Stop and remove containers"
	@echo "  restart            Restart containers"
	@echo "  logs               Follow container logs"
	@echo "  ps                 List containers"
	@echo "  shell              Open bash in PHP container"
	@echo ""
	@echo "Docker (Production):"
	@echo "  prod-build         Build production containers"
	@echo "  prod-up            Start production containers"
	@echo "  prod-down          Stop production containers"
	@echo "  prod-restart       Restart production containers"
	@echo ""
	@echo "Symfony Console:"
	@echo "  console            Run console command (CMD=...)"
	@echo "  id-cross-var       Run ASIN cross-variation identification"
	@echo "  sheet-to-kafka     Sync Google Sheets to Kafka"
	@echo "  kafka-to-sheet     Sync Kafka to Google Sheets"
	@echo "  cache-clear        Clear Symfony cache"
	@echo ""
	@echo "Testing:"
	@echo "  test               Run all tests"
	@echo "  test-coverage      Run tests with coverage report"
	@echo "  test-html          Generate HTML coverage report"
	@echo "  test-unit          Run unit tests only"
	@echo "  test-filter        Run specific test (FILTER=testName)"
	@echo ""
	@echo "Logs:"
	@echo "  logs-id-cross-var  View id-cross-var logs"
	@echo "  logs-sheet-to-kafka View sheet-to-kafka logs"
	@echo "  logs-kafka-to-sheet View kafka-to-sheet logs"
	@echo "  logs-supervisor    View supervisor logs"
	@echo "  logs-php           View PHP error logs"
	@echo ""
	@echo "Composer:"
	@echo "  composer-install   Install dependencies"
	@echo "  composer-update    Update dependencies"
	@echo "  composer-dump      Dump autoload"
	@echo ""
	@echo "Supervisor:"
	@echo "  supervisor-start   Start supervisord"
	@echo "  supervisor-status  Check process status"
	@echo "  supervisor-restart Restart all processes"
	@echo ""
	@echo "Setup:"
	@echo "  init               Full initialization (build + up + composer)"
	@echo "  setup              Quick setup (up only)"
	@echo ""
	@echo "Cleanup:"
	@echo "  clean              Remove containers and images"
	@echo "  docker-prune       Full Docker cleanup"
