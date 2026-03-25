# Notification Service

A Symfony 8 microservice that accepts notification requests over HTTP and delivers them asynchronously via multiple channels (email, SMS). Built on RabbitMQ for message queuing with automatic provider failover and delayed retry on failure.

---

## Architecture

```
POST /api/notifications
    └─> NotificationController       (validates input, returns 202/422)
            └─> NotificationDispatcher
                    ├─> EmailNotificationMessage ──> notifications_email queue
                    └─> SmsNotificationMessage   ──> notifications_sms queue

Worker: messenger:consume notifications_email
    └─> EmailNotificationHandler
            ├─> SesEmailProvider       → success → done
            └─> PlaceholderEmailProvider → success → done
            └─> all fail → throw → Messenger retries (30s, 60s) → notifications_failed

Worker: messenger:consume notifications_sms
    └─> SmsNotificationHandler
            ├─> PlaceholderSmsProvider1
            └─> PlaceholderSmsProvider2
            └─> all fail → throw → Messenger retries (30s, 60s) → notifications_failed
```

Each step (request received, provider success/failure, all providers failed) is also persisted to the `notification_audit` database table for tracking. See [Audit Log](#audit-log).

---

## Setup

**1. Start all containers**
```bash
docker compose up -d
```

**2. Enter the PHP container**
```bash
docker compose exec php bash
```

**3. Install dependencies** (inside container)
```bash
composer install
```

**4. Run database migrations**
```bash
bin/console doctrine:migrations:migrate --no-interaction
```

**5. Create RabbitMQ queues and exchanges**
```bash
bin/console messenger:setup-transports
```

The API is now available at `http://localhost:8000`.

---

## Configuration

### Environment variables

Create a `.env.local` file with your real values:

```
AWS_ACCESS_KEY_ID=AKIA...
AWS_SECRET_ACCESS_KEY=secret
AWS_DEFAULT_REGION=eu-west-1
AWS_SES_FROM_EMAIL=noreply@example.com
```

## Running Workers

Open separate terminal sessions

```bash
# Email worker
bin/console messenger:consume notifications_email -vv

# SMS worker
bin/console messenger:consume notifications_sms -vv
```

---

### Enable / disable channels (`config/services.yaml`)

```yaml
parameters:
    notifications.channels.email.enabled: true
    notifications.channels.sms.enabled: true
```

Set to `false` to disable a channel. Disabled channels are rejected during request validation.

---

## API

### `POST /api/notifications`

**Request**
```json
{
  "user": 123,
  "channels": ["email", "sms"],
  "data": {
    "email": {
      "to": "user@example.com",
      "subject": "Hello",
      "body": "Message body"
    },
    "sms": {
      "to": "+1234567890",
      "body": "Message body"
    }
  }
}
```

**Responses**
- `202 Accepted` — messages queued successfully
- `422 Unprocessable Entity` — validation failed, body contains field errors

---

## Test Cases

### 1. Validation error — missing channel data

Send a request for email but omit the `data.email` field:

```bash
curl -s -X POST http://localhost:8000/api/notifications \
  -H "Content-Type: application/json" \
  -d '{
    "user": 1,
    "channels": ["email"],
    "data": {}
  }' | jq
```

**Expected:** `422` with:
```json
{
  "errors": {
    "isDataValid": ["data must contain an entry for each requested channel"]
  }
}
```

---

### 2. SMS failover → dead-letter queue

Both SMS providers are currently hardcoded to throw `ProviderException`, simulating total provider failure.

**Start the SMS worker** (inside container):
```bash
bin/console messenger:consume notifications_sms -vv
```

**Send an SMS notification:**
```bash
curl -s -X POST http://localhost:8000/api/notifications \
  -H "Content-Type: application/json" \
  -d '{
    "user": 42,
    "channels": ["sms"],
    "data": {
      "sms": {
        "to": "+1234567890",
        "body": "Test message"
      }
    }
  }'
```

**Expected flow:**
1. Worker picks up the message
2. Provider 1 fails → warning logged, tries provider 2
3. Provider 2 fails → `RuntimeException` thrown
4. Messenger retries after **30 seconds** (attempt 1)
5. Both fail again → retries after **60 seconds** (attempt 2)
6. Both fail again → message moved to `notifications_failed` queue

**Verify** in the RabbitMQ management UI at [http://localhost:15672](http://localhost:15672) (guest / guest):
- `notifications_sms` queue: messages pass through
- `notifications_failed` queue: message appears after ~90 seconds

---

### 3. Email — success via fallback provider

`SesEmailProvider` is tried first. Without valid AWS credentials it throws a `ProviderException`, and the handler falls back to `PlaceholderEmailProvider` which succeeds as a no-op.

**Start the email worker** (inside container):
```bash
bin/console messenger:consume notifications_email -vv
```

**Send an email notification:**
```bash
curl -s -X POST http://localhost:8000/api/notifications \
  -H "Content-Type: application/json" \
  -d '{
    "user": 42,
    "channels": ["email"],
    "data": {
      "email": {
        "to": "user@example.com",
        "subject": "Hello",
        "body": "Test message"
      }
    }
  }'
```

**Expected:** Worker logs a warning for SES failure, then `PlaceholderEmailProvider: email sent (no-op)` — message is processed successfully and not retried.

---

### 4. Email — real SES delivery

Requires valid AWS credentials and a verified sender address in SES.

**Prerequisites:**
- `.env.local` with real `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, and `AWS_SES_FROM_EMAIL`
- Sender address verified in the AWS SES console
- SES account out of sandbox (or recipient address also verified)

**Start the email worker** (inside container):
```bash
bin/console messenger:consume notifications_email -vv
```

**Send an email notification:**
```bash
curl -s -X POST http://localhost:8000/api/notifications \
  -H "Content-Type: application/json" \
  -d '{
    "user": 42,
    "channels": ["email"],
    "data": {
      "email": {
        "to": "recipient@example.com",
        "subject": "Hello from SES",
        "body": "Test message"
      }
    }
  }'
```

**Expected:** Worker logs `SES email sent` — no fallback to `PlaceholderEmailProvider`. Email arrives in the recipient's inbox.

---

## RabbitMQ Management UI

[http://localhost:15672](http://localhost:15672) — login: `guest` / `guest`

Queues:
- `notifications_email` — pending email messages
- `notifications_sms` — pending SMS messages
- `notifications_failed` — messages that exhausted all retries

### Observing retries

Symfony Messenger implements delayed retries using a dedicated delay queue per transport. When a message fails, it is published to a delay queue (e.g. `notifications_sms__delay_30000`) with a TTL matching the retry delay. Once the TTL expires, RabbitMQ moves it back to the main queue for reprocessing.

You can observe this in the management UI:
- After a failure, a `__delay_*` queue appears with 1 message and a countdown
- After the TTL expires, the message moves back to the main queue
- After all retries are exhausted, the message appears in `notifications_failed`

---

## Audit Log

Every notification request and its delivery outcome is persisted to the `notification_audit` MySQL table. This allows tracing the full lifecycle of any request using its `correlation_id`.

| Event type | When |
|---|---|
| `request_received` | HTTP request accepted (202) |
| `provider_success` | A provider delivered successfully |
| `provider_failure` | A provider threw an exception (failover attempted) |
| `all_providers_failed` | All providers exhausted, message will be retried |

Each row records: `correlation_id`, `event_type`, `user_id`, `channel`, `provider`, `recipient`, `error`, `created_at`.

**Query all events for a request:**
```sql
SELECT * FROM notification_audit WHERE correlation_id = '<uuid>' ORDER BY created_at;
```

**Query all failed deliveries for a user:**
```sql
SELECT * FROM notification_audit WHERE user_id = 42 AND event_type = 'all_providers_failed' ORDER BY created_at DESC;
```

---

## TODO

- [ ] **Real SMS provider** — implement `TwilioSmsProvider` (or similar) implementing `SmsProviderInterface`
- [ ] **Throttling** *(bonus)* — rate limit notifications per user per channel per hour (e.g. max 300/hour)
- [ ] **Make file or script** to automate common tasks and initial setup as one command
- [ ] **API docs** — add Swagger/OpenAPI docs
- [ ] **Return correlation ID in response** — include `correlation_id` in the 202 response body so callers can use it for audit log lookups without parsing headers
- [ ] **Fix 202 response body** — currently returns `null`; should return a structured JSON body (at minimum `{"correlation_id": "..."}`)
- [ ] **Better validation error responses** — error keys should reflect the actual failing field (e.g. `data.email` instead of the constraint method name `isDataValid`); update API docs accordingly
