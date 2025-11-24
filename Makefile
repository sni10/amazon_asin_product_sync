##################
# Config
##################

ENVIRONMENT ?= test
ENV_FILE = ./docker/config-envs/$(ENVIRONMENT)/.env.$(ENVIRONMENT)
COMPOSE_FILES = -f ./docker-compose.yml -f ./docker/config-envs/$(ENVIRONMENT)/docker-compose.override.yml
CONTAINER_NAME ?= amazon_asin_product_sync
DOCKER_EXEC = docker exec -it $(CONTAINER_NAME)
DOCKER_EXEC_WWW = docker exec -it -u www-data $(CONTAINER_NAME)
WORKDIR = /var/www/amazon_asin_product_sync

##################
# Docker Compose
##################

dc_build:
	docker-compose $(COMPOSE_FILES) build --pull

dc_up:
	docker-compose $(COMPOSE_FILES) up -d --build --force-recreate --remove-orphans

dc_down:
	docker-compose $(COMPOSE_FILES) down --volumes --rmi local --remove-orphans

dc_restart:
	docker-compose $(COMPOSE_FILES) down --volumes --rmi local --remove-orphans
	docker-compose $(COMPOSE_FILES) up -d --build --force-recreate

dc_logs:
	docker-compose $(COMPOSE_FILES) logs -f

dc_ps:
	docker-compose $(COMPOSE_FILES) ps

dc_exec:
	$(DOCKER_EXEC) bash

##################
# Logs (Debug)
##################

logs_id_cross_var:
	$(DOCKER_EXEC) tail -f /var/log/supervisor/id_cross_var.out.log

logs_id_cross_var_err:
	$(DOCKER_EXEC) tail -f /var/log/supervisor/id_cross_var.err.log

logs_sheet_to_kafka:
	$(DOCKER_EXEC) tail -f /var/log/supervisor/asin_sheet_to_kafka.out.log

logs_sheet_to_kafka_err:
	$(DOCKER_EXEC) tail -f /var/log/supervisor/asin_sheet_to_kafka.err.log

logs_kafka_to_sheet:
	$(DOCKER_EXEC) tail -f /var/log/supervisor/asin_kafka_to_sheet.out.log

logs_kafka_to_sheet_err:
	$(DOCKER_EXEC) tail -f /var/log/supervisor/asin_kafka_to_sheet.err.log

logs_supervisor:
	$(DOCKER_EXEC) tail -f /var/log/supervisord.log

logs_php:
	$(DOCKER_EXEC) tail -f /var/log/php_errors.log

##################
# Symfony Console (App Commands)
##################

console:
	$(DOCKER_EXEC_WWW) php $(WORKDIR)/bin/console $(cmd)

id_cross_var:
	$(DOCKER_EXEC_WWW) php $(WORKDIR)/bin/console app:id-cross-var

sheet_to_kafka:
	$(DOCKER_EXEC_WWW) php $(WORKDIR)/bin/console app:asin-sheet-to-kafka

kafka_to_sheet:
	$(DOCKER_EXEC_WWW) php $(WORKDIR)/bin/console app:asin-kafka-to-sheet

cache_clear:
	$(DOCKER_EXEC_WWW) php $(WORKDIR)/bin/console cache:clear

##################
# Composer
##################

composer_install:
	$(DOCKER_EXEC_WWW) composer install --no-interaction --prefer-dist -d $(WORKDIR)

composer_update:
	$(DOCKER_EXEC_WWW) composer update --no-interaction --prefer-dist -d $(WORKDIR)

composer_dump:
	$(DOCKER_EXEC_WWW) composer dump-autoload -o -d $(WORKDIR)

##################
# Tests
##################

test:
	$(DOCKER_EXEC_WWW) php $(WORKDIR)/vendor/bin/phpunit -c $(WORKDIR)/phpunit.xml

test_coverage:
	$(DOCKER_EXEC_WWW) php $(WORKDIR)/vendor/bin/phpunit -c $(WORKDIR)/phpunit.xml --coverage-html $(WORKDIR)/var/coverage

test_filter:
	$(DOCKER_EXEC_WWW) php $(WORKDIR)/vendor/bin/phpunit -c $(WORKDIR)/phpunit.xml --filter=$(filter)

##################
# Supervisor
##################

supervisor_start:
	$(DOCKER_EXEC) /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf

supervisor_status:
	$(DOCKER_EXEC) supervisorctl status

supervisor_restart:
	$(DOCKER_EXEC) supervisorctl restart all

##################
# Cleanup
##################

docker_clean:
	docker system prune -af --volumes
	docker builder prune -af
	docker image prune -af
