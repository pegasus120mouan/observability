# Architecture

SAHA Observability is a **modular monolith**. Phase 1 stays inside Laravel's standard `app/` tree. Domain folders such as `modules/Hosts` will be introduced when those domains have their own HTTP, jobs, and persistence — not before.

## Product identity

`config/platform.php` owns the display name, short name, default retention, and login throttle. Blade and mail should read `config('platform.*')`, never hard-coded product strings.

## Multi-tenancy

Single MySQL database. Tenant key: `organization_id`.

- An **organization** is a customer.
- A **user** may belong to several organizations through `organization_user`.
- The role is **per membership**, not global.
- `SUPER_ADMIN` is a platform flag on `users.is_super_admin`. It cannot be assigned as an organization role.

Request flow:

1. Authenticate (session or Sanctum token).
2. `EnsureActiveUser` rejects suspended accounts.
3. `SetCurrentOrganization` resolves the tenant from `X-Organization-Id`, the session, or `users.current_organization_id`.
4. `TenantContext` stores the current organization (Laravel `Context`, hidden from logs).
5. Policies check both permission and organization membership.
6. Tenant queries are scoped to the current organization. Cross-tenant lookups return **404**.

`BelongsToOrganization` + `OrganizationScope` apply to hosts, agents, enrollment tokens, metric samples, log sources, log entries, alert rules, alerts, incidents, incident events, dashboards, dashboard widgets, applications, and application metrics. Cross-tenant host, log-source, alert, incident, dashboard, or application URLs return **404**.

## Authorization

| Role | Phase 8 |
| --- | --- |
| SUPER_ADMIN | All organizations, users, hosts, agents, metrics, logs, alerts, incidents, dashboards, and applications |
| ADMIN | Manage the current organization, users, hosts, agents, log sources, alert rules, dashboards, and applications; view metrics, logs, and alerts; create and resolve incidents |
| ANALYST | View organization, users, roles, audit logs, hosts, agents, metrics, logs, alerts, applications; acknowledge/resolve alerts; work incidents; create and edit dashboards |
| OPERATOR | View organization, users, roles, hosts, agents, metrics, logs, alerts, dashboards, and applications; acknowledge/resolve alerts; work incidents |
| VIEWER | View organization, users, roles, hosts, metrics, logs, alerts, incidents, dashboards, and applications |

Permissions live in `App\Support\PermissionCatalog` and are mirrored in `permissions` / `permission_role` for display and later UI editing.

## Layers

- HTTP: controllers, form requests, API resources
- Actions: create/update organization and users; register agent; rotate key; create enrollment token; ingest metric samples; ingest log entries; evaluate alert rules; create/update incidents and record timeline events; create/update dashboards and widgets; create/update applications; ingest application metrics
- Models + policies
- `AuditLogger` for sensitive actions
- No repository per model. Add repositories later only at storage boundaries (ClickHouse, OpenSearch, etc.)

## Storage strategy

Laravel stays the application layer. Each class of data has a dedicated store in the target architecture. Repositories and ingestion jobs sit between controllers and storage so the app can switch engines later without rewriting HTTP code.

| Data | Target store | Why |
| --- | --- | --- |
| Users, organizations, roles | MySQL | Relational data, transactions, foreign keys |
| Agent configuration | MySQL | Relations and integrity |
| Hosts, servers, equipment | MySQL | Classic CRUD |
| Alert rules | MySQL | Complex relations |
| Incidents | MySQL | Transactions + relations |
| Dashboards | MySQL | Configuration |
| Applications | MySQL | Inventory and golden-signal samples |
| Audit / permissions | MySQL | Reliability and security |
| CPU / RAM / disk metrics | ClickHouse or TimescaleDB | High-volume time series |
| Logs | OpenSearch / Elasticsearch | Fast search over huge volumes |
| Traces / APM | ClickHouse or Elasticsearch | High volume + analytical search |
| Cache / queues | Redis | Low latency |
| Threat intelligence | OpenSearch or PostgreSQL | Search + structured data |

**V1 rule:** only MySQL is connected. Metrics, logs, and traces will be stored in MySQL first (indexed, batched, retained), then moved behind the same services when ClickHouse / OpenSearch are introduced. Redis can be enabled for cache and queues without a data-model rewrite.

SQLite is acceptable only for local convenience and PHPUnit (`:memory:`). It is not the production engine.
