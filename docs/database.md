# Database

Engine for V1: **MySQL 8** only.

Target later: MySQL for relational/config data, ClickHouse or TimescaleDB for metrics, OpenSearch for logs, Redis for cache/queues. See `docs/architecture.md`.

Local `.env` may still use SQLite from the Laravel skeleton. Production and Laragon should use MySQL (`saha_observability`). Tests keep SQLite in-memory.

## Phase 1 tables

```
organizations 1──* organization_user *──1 users
roles 1──* organization_user
roles *──* permissions          (permission_role)
organizations 1──* audit_logs
users 1──* audit_logs
users N──1 organizations        (users.current_organization_id)
```

### organizations

Customer tenant. Status: `active`, `suspended`, `trial`. Retention columns (`metric_retention_days`, `log_retention_days`, `audit_retention_days`) are stored now so later cleanup jobs can read them.

### users

Platform identity. `is_super_admin` is the only global privilege. `status` is `active` or `suspended`.

### roles / permissions / permission_role

System roles seeded from `RoleName` and `PermissionCatalog`. `SUPER_ADMIN` exists as a role record for documentation; memberships may only use ADMIN, ANALYST, OPERATOR, VIEWER.

### organization_user

Unique `(organization_id, user_id)`. Holds `role_id` and membership `status`.

### audit_logs

Append-only (`created_at` only). `organization_id` and `user_id` are nullable for platform-level events such as failed logins.

## Indexes (Phase 1)

- `organizations.slug` unique, `status`
- `users.email` unique, `status`, `is_super_admin`
- `organization_user` unique pair + `status`
- `audit_logs.action`, `created_at`, `(resource_type, resource_id)`

## Phase 2 tables

```
organizations 1──* hosts 1──0..1 agents
organizations 1──* agents
organizations 1──* enrollment_tokens
hosts 0..1──1 agents          (circular nullable FKs)
users 1──* enrollment_tokens  (created_by)
```

### hosts

Monitored machine. Tenant-scoped. `agent_id` is nullable until registration links the pair. Status: `online`, `offline`, `warning`, `critical`, `maintenance`. Environment: `production`, `staging`, `development`, `test`.

### agents

Collector identity. Unique `agent_uid` (`AGT-…`). `api_key_hash` is HMAC-SHA256, never the plain key. Status: `pending`, `online`, `offline`, `revoked`.

### enrollment_tokens

Org-scoped one-time-style tokens used only for `POST /api/v1/agent/register`. Hashed at rest. `expires_at` / `revoked_at` disable them. Plain text is shown once in the UI.

## Indexes (Phase 2)

- `hosts.hostname`, `status`, `last_seen_at`, `agent_id`
- `agents.agent_uid` unique, `(organization_id, status)`, `last_seen_at`
- `enrollment_tokens (organization_id, revoked_at)`

## Phase 3 tables

```
organizations 1──* metric_samples *──1 hosts
```

### metric_samples

Append-only time series (MySQL V1). `metric_type` is `cpu`, `memory`, `disk`, `network`, `load`, `process`, or `uptime`. `metric_name` is allow-listed per type (`usage`, `rx_bytes`, …). Sample time is `collected_at`. `metadata` is optional JSON. No `updated_at`.

Retention: `metrics:prune` deletes rows older than `organizations.metric_retention_days` (default 30). ClickHouse remains a later store behind the same ingest action.

## Indexes (Phase 3)

- `metric_samples (organization_id, collected_at)`
- `metric_samples (host_id, metric_type, collected_at)`
- `metric_samples (metric_type, collected_at)`

## Phase 4 tables

```
organizations 1──* log_sources *──1 hosts
organizations 1──* logs *──1 hosts
log_sources 1──* logs
```

### log_sources

Per-host collector identity (`apache`, `nginx`, `agent`, …). Unique `(organization_id, host_id, name)`. `type` is `agent`, `file`, `syslog`, `journald`, or `windows_event`. `status` is `active`, `paused`, or `error`. Paused sources skip ingest. `configuration` is optional JSON.

### logs

Append-only log rows (MySQL V1). Model class is `LogEntry` (`$table = 'logs'`). Sample time is `logged_at`. Level is `debug` through `emergency`. `username` stores the process user (column is not `user`). Optional `source`, `facility`, `event_id`, `ip_address`, `process`, `metadata`. No `updated_at`.

Retention: `logs:prune` deletes rows older than `organizations.log_retention_days` (default 90). OpenSearch remains a later store behind the same ingest action. Explorer search is SQL `LIKE` until then.

## Indexes (Phase 4)

- `log_sources` unique `(organization_id, host_id, name)`, `(host_id, status)`
- `logs (organization_id, logged_at)`
- `logs (host_id, logged_at)`
- `logs (source_id, logged_at)`
- `logs (level, logged_at)`

## Phase 5 tables

```
organizations 1──* alert_rules 1──* alerts *──1 hosts
```

### alert_rules

Org-scoped evaluation definitions. `metric_type` is `cpu`, `memory`, `disk`, `host_offline`, or `log_error`. `condition` is `gt` / `gte` / `lt` / `lte`. `threshold` is required except for `host_offline`. `duration` is minutes the condition must hold (or the log-error window). `notification_channels` is JSON `[{channel: mail|webhook, target: ...}]`.

### alerts

Triggered instances. Status: `open`, `acknowledged`, `resolved`, `closed`. Severity: `low`, `medium`, `high`, `critical`. Unique open/acknowledged row per rule+host is enforced in the evaluator, not with a unique index. Recovery auto-resolves active alerts when the condition clears.

`alerts:evaluate` runs every minute (after `hosts:mark-offline`) and dispatches `EvaluateAlertJob` per organization. New opens dispatch `SendAlertNotificationJob` (mail + webhook).

## Indexes (Phase 5)

- `alert_rules (organization_id, enabled)`
- `alerts (organization_id, status)`
- `alerts (alert_rule_id, host_id, status)`
- `alerts (host_id, triggered_at)`

## Phase 6 tables

```
organizations 1──* incidents *──1 hosts
organizations 1──* incident_events *──1 incidents
users 0..1──* incidents          (assigned_to)
users 0..1──* incident_events
incidents 1──* alerts
```

### incidents

Org-scoped response record. Status is forward-only: `open` → `investigating` → `mitigated` → `resolved` → `closed` (ranks may skip forward, never backward). Priority is `p1`–`p4` and defaults from severity when omitted. `detected_at` is required; `resolved_at` / `closed_at` are set on those transitions. Display reference is `INC-{id}`.

An alert may be promoted once via `alerts.incident_id`. Promoting does not auto-create an incident for every alert.

### incident_events

Append-only timeline (`created_at` only). Types: `created`, `status_changed`, `assigned`, `comment`, `alert_linked`, `updated`. `user_id` is nullable for system rows. `metadata` is optional JSON.

## Indexes (Phase 6)

- `incidents (organization_id, status)`
- `incidents (organization_id, detected_at)`
- `incidents (assigned_to, status)`
- `incident_events (incident_id, created_at)`
- `alerts.incident_id` (nullable FK, `nullOnDelete`)

## Phase 7 tables

```
organizations 1──* dashboards 1──* dashboard_widgets
users 0..1──* dashboards          (created_by)
```

### dashboards

Org-scoped saved view. `is_default` is exclusive per organization (enforced in `CreateDashboardAction` / `UpdateDashboardAction`, not with a unique index). `created_by` is nullable (`nullOnDelete`).

Creating with `seed_layout` adds starter widgets: online hosts, open alerts, open incidents, error logs, CPU/memory timeseries, active alerts, recent incidents.

### dashboard_widgets

Layout tiles. `type` is `stat`, `timeseries`, `hosts`, `alerts`, `incidents`, or `logs`. `width` is a Bootstrap column span (3, 4, 6, or 12). `sort_order` is the grid order. `config` is JSON (`metric`, `metric_type`/`metric_name`/`range`/`host_id`, or `limit`). `host_id` in config must belong to the same organization.

## Indexes (Phase 7)

- `dashboards (organization_id, is_default)`
- `dashboards (organization_id, name)`
- `dashboard_widgets (dashboard_id, sort_order)`

## Phase 8 tables

```
organizations 1──* applications 0..1──* hosts
applications 1──* application_metrics
```

### applications

Org-scoped service inventory. Unique on `(organization_id, slug, environment)` so the same name can exist in production and staging. `type` is `php`, `laravel`, `nodejs`, `java`, `python`, `dotnet`, `api`, or `other`. `status` is derived from the latest APM sample (`healthy` / `warning` / `critical` / `unknown`). `host_id` is optional (`nullOnDelete`). Agents upsert by slug + environment.

### application_metrics

Append-only golden-signal samples (`created_at` only): `request_count`, `error_count`, `response_time_avg`, `response_time_p95`, `status_codes` JSON. V1 stores these in MySQL. Distributed traces (OpenTelemetry spans) are catalogued (`ApmSpanKind`) but not persisted yet.

## Indexes (Phase 8)

- `applications (organization_id, slug, environment)` unique
- `applications (organization_id, status)`
- `applications (organization_id, name)`
- `application_metrics (application_id, collected_at)`
- `application_metrics (organization_id, collected_at)`

## Later tables (not migrated yet)

integrations.

High-volume tables will stay in MySQL for V1 with batch inserts, indexes, and retention jobs. ClickHouse / OpenSearch are explicitly out of scope until a later phase.
