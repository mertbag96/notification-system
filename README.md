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
- **Operations dashboard** at `/` — live health, queue depth, success/failure/latency metrics, and a filterable notifications list with a detail drawer.
- **OpenAPI 3.1** spec + Swagger UI at `/api/docs`.
- **Docker Compose** for one-command setup.
- **GitHub Actions** CI (Pint + PHPUnit + migrations).

---

## Quick start

### 1. Provision a webhook.site inbox (required, ~30 seconds)

Open https://webhook.site, copy the unique URL it shows you (e.g. `https://webhook.site/abcd1234-…`), then click **Edit** on that inbox and set:

| Field | Value |
| --- | --- |
| Status code | `202` |
| Content type | `application/json` |
| Body | `{"messageId":"msg-{{$randomUUID}}","status":"accepted","timestamp":"{{$timestamp}}"}` |

This is the contract the provider expects in order to mark a notification as `sent`. The shared placeholder UUID in `.env.example` is rate-limited (HTTP 429), so notifications will not succeed until you replace it.

### 2. Choose a runtime

#### Option A — Laravel Herd / local PHP

```bash
composer install
npm install
cp .env.example .env
# Edit .env and set WEBHOOK_SITE_URL to your personal inbox from step 1
php artisan key:generate
php artisan migrate --no-interaction
npm run build                       # or `npm run dev` for HMR
php artisan test --compact          # run the test suite
php artisan queue:work --queue=notifications-high,notifications-normal,notifications-low
```

The app is served by Herd at `https://notification-system.test`. Open the dashboard at the root URL.

#### Option B — Docker Compose (one command)

```bash
# Set your webhook.site URL once, then bring everything up
export WEBHOOK_SITE_URL=https://webhook.site/your-uuid-here
docker compose up -d --build
```

Then open:

- Dashboard: [http://localhost:8080/](http://localhost:8080/)
- Swagger UI: [http://localhost:8080/api/docs](http://localhost:8080/api/docs)
- Health: [http://localhost:8080/api/v1/health?api_key=local-development-key](http://localhost:8080/api/v1/health?api_key=local-development-key)
- Metrics: [http://localhost:8080/api/v1/metrics?api_key=local-development-key](http://localhost:8080/api/v1/metrics?api_key=local-development-key)

The `app`, `queue`, and `scheduler` containers all share the same image. MySQL data persists in the `mysql-data` volume.

---

## Configuration

Important environment variables (see `.env.example` for the full list):

| Variable | Default in `.env.example` | Notes |
| --- | --- | --- |
| `WEBHOOK_SITE_URL` | `https://webhook.site/00000000-…` (placeholder) | Replace with your personal inbox URL — see Quick start |
| `WEBHOOK_SITE_TIMEOUT` | `5` | Seconds |
| `NOTIFICATION_RATE_LIMIT_PER_SECOND` | `100` | Per channel |
| `NOTIFICATION_API_KEY` | `local-development-key` | Required as `X-Api-Key` header (or `?api_key=` query parameter for browser GETs). **Rotate for non-local environments.** |
| `NOTIFICATION_IDEMPOTENCY_TTL_HOURS` | `24` | |
| `NOTIFICATION_MAX_BATCH_SIZE` | `1000` | |
| `NOTIFICATION_PROVIDER_429_MAX_ROUNDS` | `50` | Per-notification cap on consecutive HTTP 429 retries before marking `failed` |
| `NOTIFICATION_PROVIDER_429_RETRY_DEFAULT` | `120` | Seconds to wait between 429 rounds when no `Retry-After` header is present |
| `NOTIFICATION_PROVIDER_429_RETRY_CAP` | `3600` | Upper bound on honoured `Retry-After` values |
| `QUEUE_CONNECTION` | `database` | Swap to `redis` for higher-throughput deployments |

Tunables specific to the notification system live in `config/notifications.php`.

> Tests override `NOTIFICATION_API_KEY` to empty via `phpunit.xml` so the suite can hit endpoints without authentication.

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

Folder layout:

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

The examples below assume Laravel Herd at `https://notification-system.test`. For Docker, substitute `http://localhost:8080`. All endpoints under `/api/v1` require the `X-Api-Key` header (or `?api_key=` query parameter on GET requests). `X-Correlation-Id` and `Idempotency-Key` are optional.

### Create a notification

```bash
curl -X POST https://notification-system.test/api/v1/notifications \
  -H 'Content-Type: application/json' \
  -H 'X-Api-Key: local-development-key' \
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
  -H 'X-Api-Key: local-development-key' \
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
curl -H 'X-Api-Key: local-development-key' \
  https://notification-system.test/api/v1/notifications/{id}
```

### Cancel a notification

```bash
curl -X POST -H 'X-Api-Key: local-development-key' \
  https://notification-system.test/api/v1/notifications/{id}/cancel
```

### List with filters

```bash
curl -H 'X-Api-Key: local-development-key' \
  'https://notification-system.test/api/v1/notifications?status=failed&channel=sms&per_page=50'
```

### Metrics

```bash
curl -H 'X-Api-Key: local-development-key' \
  https://notification-system.test/api/v1/metrics | jq
```

### Health

```bash
curl -H 'X-Api-Key: local-development-key' \
  https://notification-system.test/api/v1/health
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
- **`webhook.site` HTTP 429**: free tiers throttle aggressively. Configure your inbox to reply with `202` plus JSON `{"messageId":"…","status":"accepted","timestamp":"…"}` (see Quick start). When a 429 still occurs, the worker **re-dispatches a fresh delayed job** (honouring `Retry-After` when present) so rate-limit rounds do not consume Laravel's `$tries` budget. After `NOTIFICATION_PROVIDER_429_MAX_ROUNDS` consecutive 429s the notification is marked **failed** — rotate to a new inbox UUID or wait for the quota window.
- **Switching to Redis**: change `QUEUE_CONNECTION=redis` and `CACHE_STORE=redis` after enabling the `redis` PHP extension (Docker image already installs it via `pecl install redis`).

---

## Security & sharing

- **Never commit** your real `WEBHOOK_SITE_URL` or any production `NOTIFICATION_API_KEY`. `.env` is gitignored — keep secrets there.
- A webhook.site inbox is publicly viewable to anyone who knows its UUID; treat the URL like a credential.
- Rotate `NOTIFICATION_API_KEY` for any non-local deployment.
- Before pushing, you can sanity-check committed UUIDs with:
  ```bash
  git grep -nE 'webhook\.site/[0-9a-f-]{36}'
  ```
  Every match should be the all-zeros placeholder (or your documented placeholder string), never a real UUID.

---

## Documentation

- OpenAPI spec: [`docs/openapi.yaml`](docs/openapi.yaml) — rendered Swagger UI at `/api/docs`.
