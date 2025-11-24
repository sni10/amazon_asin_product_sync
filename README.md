# Amazon ASIN Product Sync — идентификатор и синхронизатор ASIN по UPC/EAN/GTIN

[![Release](https://img.shields.io/github/v/release/sni10/amazon_asin_product_sync?style=for-the-badge&logo=github&logoColor=white)](https://github.com/sni10/amazon_asin_product_sync/releases)
[![Release Workflow](https://img.shields.io/github/actions/workflow/status/sni10/amazon_asin_product_sync/release.yml?style=for-the-badge&logo=githubactions&logoColor=white&label=Release)](https://github.com/sni10/amazon_asin_product_sync/actions/workflows/release.yml)
[![Tests](https://img.shields.io/github/actions/workflow/status/sni10/amazon_asin_product_sync/tests.yml?style=for-the-badge&logo=githubactions&logoColor=white&label=Tests)](https://github.com/sni10/amazon_asin_product_sync/actions/workflows/tests.yml)
[![Coverage](https://img.shields.io/badge/Coverage-0%25-lightgrey?style=for-the-badge&logo=codecov&logoColor=white)](https://github.com/sni10/amazon_asin_product_sync/actions/workflows/tests.yml)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://www.php.net/)
[![Symfony](https://img.shields.io/badge/Symfony-6.4-000000?style=for-the-badge&logo=Symfony&logoColor=white)](https://symfony.com/)
[![Docker](https://img.shields.io/badge/Docker-Ready-2496ED?style=for-the-badge&logo=docker&logoColor=white)](https://www.docker.com/)
[![License](https://img.shields.io/badge/License-Proprietary-red?style=for-the-badge)](LICENSE)

Консольное приложение на Symfony 6.4 / PHP 8.1+, которое идентифицирует и синхронизирует ASIN по UPC/EAN/GTIN через интеграцию с Amazon SP-API, Google Sheets и Kafka.

## Основные возможности

- Идентификация ASIN по UPC/EAN/GTIN через Amazon SP-API
- Синхронизация данных между Google Sheets и Kafka
- Поддержка кросс-вариаций продуктов
- Rate limiting для соблюдения лимитов Amazon API
- Логирование и контроль ошибок через Monolog
- Автоперезапуск воркеров через supervisord

## Архитектура

- **Model** — структуры данных (DataRow, DataCollection) без бизнес-логики
- **Service** — оркестрация бизнес-процессов (AmazonService, ManageAsins, IdentifyProducts)
- **Handler** — реализация паттерна Strategy для работы с источниками (GoogleSheetsHandler, GoogleApiHandler)
- **Kafka** — продюсеры и консьюмеры для обмена сообщениями
- **Command** — входные точки Symfony Console

## Требования

- PHP 8.1+
- Symfony 6.4
- Docker и Docker Compose
- Расширения PHP: rdkafka, amqp, xdebug, mbstring, gd, pdo_pgsql, zip, sockets

## Makefile

```powershell
$env:ENVIRONMENT="test"  # выбор окружения (test/prod)
```

| Команда | Описание |
|---------|----------|
| **Docker** | |
| `make dc_up` | Сборка и запуск контейнеров |
| `make dc_down` | Остановка и очистка |
| `make dc_restart` | Перезапуск |
| `make dc_logs` | Логи docker-compose |
| `make dc_exec` | Вход в контейнер |
| **App Commands** | |
| `make id_cross_var` | Идентификация кросс-вариаций |
| `make sheet_to_kafka` | Синхронизация Sheet → Kafka |
| `make kafka_to_sheet` | Синхронизация Kafka → Sheet |
| `make console cmd="..."` | Произвольная команда |
| `make cache_clear` | Очистка кэша |
| **Логи** | |
| `make logs_id_cross_var` | Логи id-cross-var (stdout) |
| `make logs_id_cross_var_err` | Логи id-cross-var (stderr) |
| `make logs_sheet_to_kafka` | Логи sheet-to-kafka (stdout) |
| `make logs_kafka_to_sheet` | Логи kafka-to-sheet (stdout) |
| `make logs_supervisor` | Логи supervisord |
| `make logs_php` | PHP ошибки |
| **Composer** | |
| `make composer_install` | Установка зависимостей |
| `make composer_update` | Обновление зависимостей |
| **Тесты** | |
| `make test` | Запуск тестов |
| `make test_coverage` | Тесты с покрытием |
| `make test_filter filter="..."` | Фильтр тестов |
| **Supervisor** | |
| `make supervisor_start` | Запуск supervisord |
| `make supervisor_status` | Статус процессов |
| `make supervisor_restart` | Перезапуск всех процессов |

## Ручной запуск через Docker Compose

```powershell
# из корня репозитория
docker-compose -f docker-compose.yml -f docker/config-envs/$env:ENVIRONMENT/docker-compose.override.yml up -d --build

# остановка и очистка
docker-compose down
```

При старте контейнера `supervisord` автоматически запускает консольные команды:

```bash
app:id-cross-var         # Идентификация кросс-вариаций
app:asin-sheet-to-kafka  # Синхронизация Google Sheets → Kafka
app:asin-kafka-to-sheet  # Синхронизация Kafka → Google Sheets
```

## Конфигурация

### Переменные окружения

Переменные окружения берутся из `.env.prod` / `.env.test` в корне репозитория:

- `KAFKA_*` — настройки подключения к Kafka
- `GOOGLE_*` — ключи OAuth/Service Account
- `APP_ENV` / `APP_DEBUG` / `APP_SECRET` — настройки Symfony

### Внешние API

- **Google Sheets/Drive**: `docker/configs-data/credentials.json`, `docker/configs-data/token.json`
- **Amazon SP-API**: `config/amazon_credentials.json`, `config/amazon_proxy.json`

## Отладка

Для отладки использовать удалённый интерпретатор из Docker-контейнера. Конфигурация Xdebug:

```ini
xdebug.mode=debug
xdebug.start_with_request=yes
xdebug.client_host=host.docker.internal
xdebug.client_port=9003
xdebug.idekey=PHPSTORM
```

Логи доступны через Makefile: `make logs_id_cross_var`, `make logs_php`, `make logs_supervisor`.

## Тестирование

```bash
# Запуск всех тестов
make test

# Тесты с покрытием
make test_coverage

# Фильтр по имени
make test_filter filter="AmazonServiceTest"
```

