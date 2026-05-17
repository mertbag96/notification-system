# Notification System

Event-driven notification service that accepts single and batch notification requests via REST, queues them with **priority** and **per-channel rate limiting**, dispatches them to a simulated external provider (`webhook.site`), and exposes real-time **status**, **metrics**, and **health** endpoints.

Built on **Laravel 11 / PHP 8.3** following a strict thin-controller + Action/Service + DTO + API-Resource architecture.

---

## Features

- REST API (`/api/v1`) with versioning and a standard `{ data, meta, errors }` envelope.
- Single & batch create (up to **1 000** notifications per request).
- **SMS / Email / Push** channels with channel-specific validation.
- **Priority queues** (`high`, `normal`, `low`).
- **Rate limiting** — configurable (default **100 msg/s per channel**) via a cache-backed token bucket.
- **Idempotency** via `Idempotency-Key` header (24 h TTL, configurable).
- **Retry policy** with exponential backoff and jitter (`[2, 5, 15, 60, 300]` seconds, 5 tries).
- **Circuit breaker** per channel after 50 consecutive failures (30 s cooldown).
- **Cancellation** of `pending` / `queued` notifications.
- **Scheduled delivery** via `scheduled_at` + minute scheduler.
- **Template system** with `{{variable}}` substitution.
- **Observability**: correlation IDs (request → job → provider), structured JSON logs (`storage/logs/notifications-*.log`), real-time metrics endpoint (queue depth, success/failure rates, p50/p95/p99 latency).
- **Health endpoint** with DB / cache / queue checks.
- **OpenAPI 3.1** spec + Swagger UI at `/api/docs`.
- **Docker Compose** for one-command setup.
- **GitHub Actions** CI (Pint + PHPUnit + migrations).

---

## Quick start

### Option A — Laravel Herd / local

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --no-interaction
php artisan test --compact          # run the test suite
php artisan queue:work --queue=notifications-high,notifications-normal,notifications-low
```

The app is served by Herd at `https://notification-system.test`.

### Option B — Docker Compose (one command)

```bash
docker compose up -d --build
```

Then open:

- Swagger UI: [http://localhost:8080/api/docs](http://localhost:8080/api/docs)
- Health: [http://localhost:8080/api/v1/health](http://localhost:8080/api/v1/health)
- Metrics: [http://localhost:8080/api/v1/metrics](http://localhost:8080/api/v1/metrics)

The `app`, `queue`, and `scheduler` containers all share the same image. MySQL data persists in the `mysql-data` volume.

---

## Configuration

Important environment variables (see `.env.example` for the full list):


| Variable                             | Default                                  | Notes                                  |
| ------------------------------------ | ---------------------------------------- | -------------------------------------- |
| `WEBHOOK_SITE_URL`                   | *empty*                                  | Your `https://webhook.site/{uuid}` URL |
| `WEBHOOK_SITE_TIMEOUT`               | `5`                                      | Seconds                                |
| `NOTIFICATION_RATE_LIMIT_PER_SECOND` | `100`                                    | Per channel                            |
| `NOTIFICATION_API_KEY`               | *empty in tests*                         | Sent as `X-Api-Key` header             |
| `NOTIFICATION_IDEMPOTENCY_TTL_HOURS` | `24`                                     |                                        |
| `NOTIFICATION_MAX_BATCH_SIZE`        | `1000`                                   |                                        |
| `QUEUE_CONNECTION`                   | `database` (local) / `database` (Docker) | Swap to `redis` for production         |


Tunables specific to the notification system live in `config/notifications.php`.

---

## Architecture

```
HTTP → Correlation-ID middleware → API-key middleware → Idempotency middleware
     → Controller (thin)
     → FormRequest (validation + authorization)
     → Action / Service (business logic)
     → DTO (NotificationData / BatchData / ProviderResponseData)
     → Eloquent (Notification, NotificationBatch, NotificationAttempt, …)
     → JsonResource
     → Response { data, meta, errors }

SendNotificationJob (per-priority queue)
     → Rate limiter (per channel)
     → Circuit breaker (per channel)
     → NotificationProvider (webhook.site)
     → Records NotificationAttempt + metrics + structured log
```

Folder layout (strict per `PLAN.md`):

```
app/
├── Actions/          # CreateNotification, CreateBatchNotification, ListNotifications, CancelNotification, RenderTemplate
├── Console/Commands/ # DispatchScheduledNotifications, PruneIdempotencyKeys
├── Data/             # Immutable DTOs
├── Enums/            # NotificationChannel, NotificationStatus, NotificationPriority
├── Exceptions/       # Domain exceptions (auto-render to JSON envelope)
├── Http/
│   ├── Controllers/Api/V1/
│   ├── Middleware/   # CorrelationId, ApiKey, Idempotency, ForceJsonResponse
│   ├── Requests/Api/V1/
│   └── Resources/
├── Jobs/             # SendNotificationJob
├── Models/           # Notification, NotificationBatch, NotificationAttempt, NotificationTemplate, IdempotencyKey
├── Providers/
├── Services/
│   ├── Providers/    # NotificationProvider (interface) + WebhookSiteProvider
│   ├── CircuitBreaker, ContentValidator, MetricsCollector, RateLimiterService, TemplateRenderer
└── Support/          # CorrelationId helper
```

---

## API examples

> All endpoints accept the optional `X-Correlation-Id` and `Idempotency-Key` headers.

### Create a notification

```bash
curl -X POST https://notification-system.test/api/v1/notifications \
  -H 'Content-Type: application/json' \
  -H 'Idempotency-Key: 8e1c4b7e-7a5c-4d8a-9bd1-1e8a2f3c4d5e' \
  -d '{
    "channel": "sms",
    "priority": "high",
    "recipient": "+14155550100",
    "content": "Welcome aboard!"
  }'
```

### Batch create

```bash
curl -X POST https://notification-system.test/api/v1/notifications/batch \
  -H 'Content-Type: application/json' \
  -d '{
    "notifications": [
      {"channel": "sms",   "recipient": "+14155550100", "content": "A"},
      {"channel": "email", "recipient": "jane@example.com", "content": "B"},
      {"channel": "push",  "recipient": "device-token-32chars-xxxxxxxx", "content": "C"}
    ]
  }'
```

### Show a notification

```bash
curl https://notification-system.test/api/v1/notifications/{id}
```

### Cancel a notification

```bash
curl -X POST https://notification-system.test/api/v1/notifications/{id}/cancel
```

### List with filters

```bash
curl 'https://notification-system.test/api/v1/notifications?status=failed&channel=sms&per_page=50'
```

### Metrics

```bash
curl https://notification-system.test/api/v1/metrics | jq
```

### Health

```bash
curl https://notification-system.test/api/v1/health
```

---

## Testing

Single command, per the assessment requirement:

```bash
php artisan test --compact
```

The suite runs PHPUnit feature + unit tests against an in-memory SQLite database. `Http::fake()` stubs every external `webhook.site` call so tests stay deterministic.

Other useful commands:

```bash
vendor/bin/pint --format agent             # auto-fix code style
vendor/bin/pint --test --format agent      # CI-style style gate
php artisan test --compact --filter=Cancel # filter by test name
```

---

## Operational notes

- **Queue workers**: run one worker per priority tier in production for stricter isolation, e.g. `--queue=notifications-high` only.
- **Scheduler**: the `notifications:dispatch-scheduled` command runs every minute and only queues notifications whose `scheduled_at` is now in the past.
- **Idempotency keys** auto-expire after 24 h; `notifications:prune-idempotency` runs nightly at 03:00.
- **`webhook.site` HTTP 429**: free tiers often throttle clients; configure the webhook to reply with **202** or **200** plus JSON `{ "messageId", "status":"accepted", "timestamp" }`. The worker **releases** jobs with backoff (honours `Retry-After` when present) instead of failing immediately. If you exhaust the capped number of consecutive 429s, the notification is marked **failed** — open a fresh inbox URL at webhook.site or wait for the quota window.
- **Switching to Redis**: change `QUEUE_CONNECTION=redis` and `CACHE_STORE=redis` after enabling the `redis` PHP extension (Docker image already installs it via `pecl install redis`).

---

## Documentation

- OpenAPI: `[docs/openapi.yaml](docs/openapi.yaml)`
- Plan & decisions: `[PLAN.md](PLAN.md)`
- Architecture rules for AI agents: `[AGENTS.md](AGENTS.md)`

---

## License

MIT.