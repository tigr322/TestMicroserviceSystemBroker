# Сервис уведомлений (Notification Microservice)

Облачный production-ready **сервис SMS и Email уведомлений** на PHP 8.3 / Laravel 13, PostgreSQL, RabbitMQ и Redis.

---

## Содержание

1. [Обзор проекта](#обзор-проекта)
2. [Архитектура](#архитектура)
3. [Стратегия очередей](#стратегия-очередей)
4. [Структура проекта](#структура-проекта)
5. [Установка](#установка)
6. [Переменные окружения](#переменные-окружения)
7. [Миграции](#миграции)
8. [Запуск тестов](#запуск-тестов)
9. [Примеры API](#примеры-api)
10. [Swagger UI](#swagger-ui)
11. [Архитектурные решения](#архитектурные-решения)

---

## Обзор проекта

Сервис принимает запросы на массовую рассылку через REST API, распределяет их по трём приоритетным очередям в RabbitMQ, обрабатывает каждую попытку доставки через mock-провайдеры каналов и отслеживает полный статус доставки (queued → sent → delivered / failed) в PostgreSQL.

**Ключевые гарантии:**

| Гарантия | Механизм |
|----------|----------|
| Доставка хотя бы раз (at-least-once) | `durable`-очереди RabbitMQ + `ack` только после завершения задачи |
| Единственный бизнес-эффект | Ключи идемпотентности в Redis + PostgreSQL |
| Повторные попытки с задержкой | `$backoff = [10, 30, 60]` секунд у Laravel-задачи |
| Приоритетная обработка | Отдельные очереди, потребляемые в порядке high → normal → low |
| Восстановление после перезапуска | Задачи сохраняются через RabbitMQ persistence |

---

## Архитектура

Проект следует **чистой архитектуре (Clean Architecture)** с четырьмя явными слоями:

```
┌─────────────────────────────────────────┐
│           Слой представления            │
│  Controllers · Requests · Resources     │
│  (HTTP-граница, без бизнес-логики)      │
├─────────────────────────────────────────┤
│           Слой приложения               │
│  Services · DTOs                        │
│  (оркестрация use-case'ов)              │
├─────────────────────────────────────────┤
│             Доменный слой               │
│  Entities · Enums · Repository          │
│  Interfaces · Domain Events             │
│  (чистый PHP, без зависимостей фреймворка) │
├─────────────────────────────────────────┤
│        Инфраструктурный слой            │
│  Eloquent Repositories · Providers      │
│  Redis Cache · RabbitMQ driver          │
│  (реализует доменные контракты)         │
└─────────────────────────────────────────┘
```

### Диаграмма последовательности

Полный поток обработки очереди — в файле [`docs/sequence-diagram.mermaid`](docs/sequence-diagram.mermaid).

Кратко:

```
POST /api/notifications/send
  → Валидация
  → Проверка идемпотентности (Redis → PostgreSQL)
  → Сохранение уведомлений (БД, status=queued)
  → Публикация в RabbitMQ (приоритетная очередь)
  → Воркер забирает задачу
  → Устанавливает status=sent
  → Вызывает mock-провайдер
  → успех          → status=delivered
  → временная ошибка → повтор (10с/30с/60с) → ... → delivered | failed
  → постоянная ошибка → status=failed (без повторов)
```

---

## Стратегия очередей

Три отдельные очереди RabbitMQ обрабатывают уведомления с разными приоритетами:

| Очередь | Приоритет | Примеры использования |
|---------|-----------|-----------------------|
| `notifications.high` | Высокий | OTP, оповещения безопасности, подтверждения транзакций |
| `notifications.normal` | Средний | Транзакционные письма, обновления заказов |
| `notifications.low` | Низкий | Маркетинг, рассылки |

**Порядок обработки воркерами** — Horizon (и `queue:work`) настроен потреблять очереди в строгом порядке:

```
notifications.high, notifications.normal, notifications.low
```

Пока в RabbitMQ есть хоть одно сообщение с высоким приоритетом, сообщения с нормальным и низким приоритетом не обрабатываются.

Задержки повторных попыток:

| Попытка | Задержка |
|---------|----------|
| 1-й повтор | 10 с |
| 2-й повтор | 30 с |
| 3-й повтор | 60 с |
| После 3-го повтора | `status=failed`, задача удаляется |

---

## Структура проекта

```
app/
├── Domain/
│   ├── Notification/
│   │   ├── Enums/          # Channel, Priority, NotificationStatus (backed enums PHP 8.3)
│   │   ├── Entities/       # Notification (чистая доменная сущность)
│   │   ├── Repositories/   # NotificationRepositoryInterface
│   │   ├── Events/         # NotificationQueued, Delivered, Failed
│   │   └── Exceptions/     # InvalidStatusTransitionException
│   └── Idempotency/
│       ├── Entities/       # IdempotencyKey
│       └── Repositories/   # IdempotencyKeyRepositoryInterface
├── Application/
│   ├── DTOs/               # SendNotificationDTO, NotificationResultDTO
│   └── Services/           # NotificationService (основной use-case)
├── Infrastructure/
│   ├── Persistence/Eloquent/
│   │   ├── Models/         # NotificationModel, IdempotencyKeyModel
│   │   └── Repositories/   # EloquentNotificationRepository, EloquentIdempotencyKeyRepository
│   ├── Cache/              # RedisIdempotencyCache
│   └── Providers/Notification/
│       ├── NotificationProviderInterface
│       ├── ProviderResult, Temporary/PermanentProviderException
│       ├── SmsProviderMock
│       ├── EmailProviderMock
│       └── NotificationProviderFactory
├── Jobs/
│   └── ProcessNotificationJob.php   # Queue-задача с логикой повторов
└── Presentation/
    └── Http/
        ├── Controllers/    # NotificationController, SubscriberController, SwaggerController
        ├── Requests/       # SendNotificationRequest
        └── Resources/      # NotificationResource

database/
└── migrations/
    ├── 2025_01_01_000001_create_notifications_table.php
    └── 2025_01_01_000002_create_idempotency_keys_table.php

tests/
└── Feature/Notification/
    ├── Scenario1_SuccessfulDeliveryTest.php
    ├── Scenario2_TemporaryFailureTest.php
    ├── Scenario3_PermanentFailureTest.php
    ├── Scenario4_IdempotencyTest.php
    └── Scenario5_PriorityProcessingTest.php

docker/
├── app/Dockerfile
├── app/php.ini
└── nginx/default.conf
```

---

## Установка

### Требования

- Docker ≥ 24
- Docker Compose ≥ 2.20

### Запуск одной командой

```bash
# 1. Клонировать репозиторий
git clone <repo-url>
cd notification-service

# 2. Скопировать файл окружения
cp .env.example .env

# 3. Сгенерировать ключ приложения (во временном контейнере)
docker run --rm -v "$(pwd)":/app -w /app php:8.3-cli php artisan key:generate

# 4. Запустить все сервисы
docker compose up -d

# 5. Дождаться готовности контейнеров и запустить миграции
docker compose exec app php artisan migrate
```

После запуска сервисы доступны по адресам:

| Сервис | URL |
|--------|-----|
| API | http://localhost:8088/api |
| Swagger UI | http://localhost:8088/api/documentation |
| RabbitMQ Management | http://localhost:15672 (guest / guest) |
| Horizon Dashboard | http://localhost:8088/horizon |

---

## Переменные окружения

| Переменная | По умолчанию | Описание |
|------------|--------------|----------|
| `DB_HOST` | `postgres` | Хост PostgreSQL |
| `DB_DATABASE` | `notifications` | Имя базы данных |
| `DB_USERNAME` | `notifications` | Пользователь БД |
| `DB_PASSWORD` | `secret` | Пароль БД |
| `REDIS_HOST` | `redis` | Хост Redis |
| `REDIS_PASSWORD` | `secret` | Пароль Redis |
| `RABBITMQ_HOST` | `rabbitmq` | Хост RabbitMQ |
| `RABBITMQ_USER` | `guest` | Пользователь RabbitMQ |
| `RABBITMQ_PASSWORD` | `guest` | Пароль RabbitMQ |
| `RABBITMQ_EXCHANGE_NAME` | `notifications` | Имя exchange |
| `QUEUE_CONNECTION` | `rabbitmq` | Драйвер очереди |
| `IDEMPOTENCY_TTL` | `86400` | TTL ключа идемпотентности (секунды) |
| `L5_SWAGGER_GENERATE_ALWAYS` | `true` | Автогенерация Swagger-документации |

---

## Миграции

```bash
# Внутри запущенного контейнера app
docker compose exec app php artisan migrate

# С seed-данными (если будут добавлены сидеры)
docker compose exec app php artisan migrate --seed
```

---

## Запуск тестов

Тесты используют SQLite in-memory и драйвер очереди `sync` — внешние сервисы не нужны.

```bash
# Все тесты (через Docker)
docker compose exec app php artisan test

# Или напрямую через Pest
docker compose exec app ./vendor/bin/pest

# Запуск конкретного сценария
docker compose exec app ./vendor/bin/pest tests/Feature/Notification/Scenario1_SuccessfulDeliveryTest.php

# С покрытием (требует Xdebug или PCOV)
docker compose exec app ./vendor/bin/pest --coverage
```

---

## Примеры API

### Отправка уведомлений

```bash
curl -X POST http://localhost:8088/api/notifications/send \
  -H "Content-Type: application/json" \
  -d '{
    "channel": "email",
    "priority": "high",
    "message": "Ваш код подтверждения: 1234",
    "recipient_ids": [1, 2, 3, 4],
    "idempotency_key": "unique-request-id-abc123"
  }'
```

**Ответ (202 Accepted):**

```json
{
  "notification_ids": [101, 102, 103, 104],
  "deduplicated": false,
  "message": "4 notification(s) queued successfully."
}
```

**Повторный запрос с тем же `idempotency_key` (200 OK):**

```json
{
  "notification_ids": [101, 102, 103, 104],
  "deduplicated": true,
  "message": "Duplicate request — original response returned."
}
```

### Получение уведомлений подписчика

```bash
curl http://localhost:8088/api/subscribers/1/notifications
```

**Ответ (200 OK):**

```json
[
  {
    "id": 101,
    "channel": "email",
    "priority": "high",
    "status": "delivered",
    "message": "Ваш код подтверждения: 1234",
    "retry_count": 0,
    "provider_message_id": "email_f47ac10b-58cc-4372",
    "created_at": "2025-01-15T10:30:00+00:00",
    "updated_at": "2025-01-15T10:30:01+00:00"
  }
]
```

Фильтрация по статусу или каналу:

```bash
curl "http://localhost:8088/api/subscribers/1/notifications?status=failed&channel=sms"
```

---

## Swagger UI

Интерактивная документация API доступна по адресу:

```
http://localhost:8088/api/documentation
```

OpenAPI-спецификация автогенерируется из PHP-атрибутов (`#[OA\...]`) на контроллерах и обновляется при каждом запросе в режиме разработки (`L5_SWAGGER_GENERATE_ALWAYS=true`).

---

## Архитектурные решения

### Чистая архитектура вместо fat-контроллеров MVC

Бизнес-логика живёт исключительно в `Application\Services` и доменных сущностях. Контроллеры тонкие — валидируют входные данные, вызывают сервис и возвращают ответ. Это делает ядро тестируемым без HTTP.

### Backed enums PHP 8.3 для доменных примитивов

`Channel`, `Priority` и `NotificationStatus` — backed enums, а не строковые константы. Они обеспечивают исчерпывающие проверки на этапе компиляции, безопасно приводятся в Eloquent и несут доменное поведение (например, `Priority::queue()`, `NotificationStatus::canTransitionTo()`).

### Двухуровневая идемпотентность (Redis + PostgreSQL)

Redis обеспечивает O(1)-поиск с автоматическим истечением TTL. PostgreSQL служит надёжным резервом: если Redis перезапустился или данные вытеснены, сервис читает из БД и прогревает кеш. Это предотвращает как дублирование обработки, так и потерю данных.

### `durable`-очереди RabbitMQ + `ack` после завершения

Каждая очередь и каждое сообщение объявлены как durable. Сигнал `ack` отправляется только после успешного завершения обработчика задачи. Если воркер аварийно завершится в середине задачи, RabbitMQ повторно доставит сообщение следующему доступному воркеру — обеспечивая доставку хотя бы раз.

### Постоянные и временные исключения провайдера

Контракт провайдера различает `TemporaryProviderException` (таймаут сети, rate limit) и `PermanentProviderException` (жёсткий отказ, недействительный получатель). Временные ошибки пробрасываются для активации механизма повторов Laravel; постоянные перехватываются внутри задачи, уведомление помечается как `failed`, задача удаляется — исключая бесполезные повторы.

### Horizon для управления очередями в production

Laravel Horizon работает как отдельный Docker-сервис `horizon`. Он предоставляет метрики очередей в реальном времени, автоматическое масштабирование воркеров по супервизорам и дашборд. Три супервизора — по одному на каждый уровень приоритета — масштабируются независимо, гарантируя, что высокоприоритетные воркеры никогда не голодают.

### Mock-провайдеры с настраиваемыми коэффициентами сбоев

`SmsProviderMock` и `EmailProviderMock` принимают аргументы конструктора `successRate` и `temporaryFailureRate`. В тестах они переопределяются для детерминированной симуляции любого исхода без случайности. Замена на боевой провайдер — одна строка в `NotificationProviderFactory`.
