# Agent

Phase 4 collector contract. This is not a copy of any vendor agent — it is a small PHP client that registers a host and sends heartbeats, metrics, and logs.

## Auth

- Enrollment token: issued in **Agents → Create token**, shown once, hashed at rest (`HMAC-SHA256` with `APP_KEY`).
- API key: returned once from `POST /api/v1/agent/register`, prefix `saha_`, hashed the same way. Rotate from the Agents UI.
- Heartbeat identity: `X-Agent-Id: {AGT-…}` plus `Authorization: Bearer {api_key}` (or `X-Api-Key`).

Revoked agents receive `401`. Suspended organizations receive `403`.

## Endpoints

Base URL: `{APP_URL}/api/v1`

| Method | Path | Auth | Rate limit | Description |
| --- | --- | --- | --- | --- |
| POST | `/agent/register` | Enrollment token in body | 10 / min / IP | Create host + agent, return API key once |
| POST | `/agent/heartbeat` | Agent credentials | 60 / min / agent | Mark host/agent online |
| GET | `/agent/config` | Agent credentials | same as heartbeat | Heartbeat interval and collector flags |
| POST | `/agent/metrics` | Agent credentials | 60 / min / agent | Batch CPU/RAM/disk/network samples |
| POST | `/agent/logs` | Agent credentials | 60 / min / agent | Batch log entries. Paused sources are skipped. |
| POST | `/agent/services` | Agent credentials | 60 / min / agent | Running daemons (Apache, MySQL, PostgreSQL, …) upserted as applications. |

### Register body

```json
{
  "enrollment_token": "enroll_…",
  "hostname": "web-01.acme.test",
  "ip_address": "10.0.1.11",
  "operating_system": "Ubuntu",
  "os_version": "24.04",
  "architecture": "x86_64",
  "environment": "production",
  "platform": "linux",
  "version": "0.1.0"
}
```

`platform` is `linux` or `windows`. `environment` is `production`, `staging`, `development`, or `test`.

### Heartbeat body

```json
{
  "version": "0.1.0",
  "ip_address": "10.0.1.11",
  "timestamp": "2026-09-15T10:00:00Z"
}
```

Hosts in `maintenance` keep that status even when a heartbeat arrives.

## Offline detection

`php artisan hosts:mark-offline` runs every minute via the scheduler. Hosts and agents with `last_seen_at` older than `PLATFORM_AGENT_OFFLINE_AFTER` minutes (default 5) become `offline`. Maintenance hosts are skipped. This is a status change only — there is no alert engine in Phase 2.

Run the scheduler:

```
* * * * * php /path/to/artisan schedule:run
```

Locally: `php artisan schedule:work`

## Local collector

1. Create an enrollment token in the UI.
2. Put it in `agent/agent.yml` as `enrollment_token`.
3. `php agent/saha-agent.php --once` to register, then omit `--once` to loop heartbeats.

The script writes `agent_id` and `api_key` back into `agent.yml` and clears the enrollment token. Treat that file as a secret.

Windows Task Scheduler or a Linux cron can invoke `php agent/saha-agent.php --once` on the heartbeat interval if you do not want a long-running process.

The collector also POSTs `/agent/metrics`, `/agent/logs`, and `/agent/services` after each heartbeat.

## Later

Service and event payloads will reuse this auth. Process collectors stay off until those phases.
