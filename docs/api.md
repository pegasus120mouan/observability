# API

Base path: `/api/v1`

Authentication: Laravel Sanctum personal access tokens (`Authorization: Bearer {token}`).

Select tenant: `X-Organization-Id` header, or `users.current_organization_id`.

## Envelope

Success:

```json
{
  "success": true,
  "data": {},
  "message": null,
  "meta": {}
}
```

Error:

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {}
}
```

Paginated lists put pagination in `meta`.

## Endpoints (Phase 1)

| Method | Path | Auth | Description |
| --- | --- | --- | --- |
| POST | `/api/v1/auth/login` | No | Issue a token. Rate limited (`throttle:login`). |
| POST | `/api/v1/auth/logout` | Yes | Revoke the current token. |
| GET | `/api/v1/me` | Yes | Current user, role, tenant. |
| GET | `/api/v1/organizations` | Super admin | List tenants. |
| POST | `/api/v1/organizations` | Super admin | Create a tenant. |
| GET | `/api/v1/organizations/{organization}` | Member or super admin | Show a tenant. |
| PUT | `/api/v1/organizations/{organization}` | Admin / super admin | Update a tenant. |
| GET | `/api/v1/users` | Member with `users.view` | Users of the current tenant. |
| POST | `/api/v1/users` | `users.create` | Create a user in the current tenant. |
| GET | `/api/v1/users/{user}` | `users.view` | Show a tenant user (404 if another tenant). |

## Agent endpoints (Phase 2)

Agent routes do **not** use Sanctum. Credentials are enrollment tokens (register) or HMAC-hashed API keys (heartbeat/config). See `docs/agent.md`.

| Method | Path | Auth | Description |
| --- | --- | --- | --- |
| POST | `/api/v1/agent/register` | Enrollment token | Register host + agent. API key shown once. Rate limit 10/min/IP. |
| POST | `/api/v1/agent/heartbeat` | `X-Agent-Id` + Bearer API key | Mark online. Rate limit 60/min/agent. |
| GET | `/api/v1/agent/config` | Same as heartbeat | Heartbeat interval and collector flags. |
| POST | `/api/v1/agent/metrics` | Same as heartbeat | Batch insert samples. Rate limit 60/min/agent. HTTP 202. |
| POST | `/api/v1/agent/logs` | Same as heartbeat | Batch insert log entries. Rate limit 60/min/agent. HTTP 202. |

### POST `/api/v1/auth/login`

```json
{
  "email": "admin@acme.test",
  "password": "password",
  "device_name": "api"
}
```

The plain-text token is returned once in `data.token`. It is stored hashed.

User tokens stay on Sanctum. Agent keys are a separate HMAC scheme and must not be stored in `personal_access_tokens`.

### POST `/api/v1/agent/metrics`

```json
{
  "timestamp": "2026-09-15T10:00:00Z",
  "metrics": [
    {"type": "cpu", "name": "usage", "value": 75, "unit": "percent"},
    {"type": "memory", "name": "usage", "value": 62, "unit": "percent"},
    {"type": "disk", "name": "usage", "value": 82, "unit": "percent"},
    {"type": "network", "name": "rx_bytes", "value": 1200000, "unit": "bytes"},
    {"type": "network", "name": "tx_bytes", "value": 800000, "unit": "bytes"}
  ]
}
```

Unknown type/name pairs are rejected. CPU/memory/disk `usage` at or above 80% sets the host to `warning`, 90% to `critical`. Maintenance hosts keep that status.

### POST `/api/v1/agent/logs`

```json
{
  "timestamp": "2026-09-15T10:00:00Z",
  "logs": [
    {
      "timestamp": "2026-09-15T10:00:01Z",
      "level": "ERROR",
      "source": "apache",
      "message": "AH00094: Command line: apache2 -D FOREGROUND",
      "process": "apache2",
      "user": "www-data",
      "ip_address": "10.0.1.40",
      "metadata": {"request_id": "abc-123"}
    }
  ]
}
```

Levels are `debug`, `info`, `notice`, `warning`, `error`, `critical`, `alert`, and `emergency` (case-insensitive). Missing `source` becomes `agent`. A paused log source is accepted (`skipped`) and not inserted. HTTP 202.


