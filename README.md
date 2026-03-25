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

**To test real SES delivery:** Fill in `AWS_*` variables in `.env.local` and verify your sender address in the AWS SES console. The `SesEmailProvider` will then send a real email on the first attempt.

---

## RabbitMQ Management UI

[http://localhost:15672](http://localhost:15672) — login: `guest` / `guest`

Queues:
- `notifications_email` — pending email messages
- `notifications_sms` — pending SMS messages
- `notifications_failed` — messages that exhausted all retries

---

## TODO

- [ ] **Real SMS provider** — implement `TwilioSmsProvider` (or similar) implementing `SmsProviderInterface`
- [ ] **Throttling** *(bonus)* — rate limit notifications per user per channel per hour (e.g. max 300/hour)
- [ ] **Make file or script** to automate common tasks and initial setup as one command
