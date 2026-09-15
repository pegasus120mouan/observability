# Security

## Applied in Phase 1 and Phase 2

- CSRF on all web form posts
- Password hashing (`hashed` cast)
- Login rate limiting (email + IP)
- API rate limiting (`api` limiter, 60 req/min)
- Agent register 10/min/IP, heartbeat 60/min/agent, metrics 60/min/agent, logs 60/min/agent, APM 60/min/agent
- Metric ingest allow-list (`MetricCatalog`); bulk insert, no per-sample audit noise
- Application metric ingest allow-list (`ApplicationType`, HTTP status codes); error count cannot exceed request count; bulk insert
- Log ingest level allow-list; paused sources skip insert; keyword search is bound `LIKE`
- Sanctum hashed user tokens (separate from agent keys)
- Agent API keys hashed with HMAC-SHA256 (`APP_KEY`); plain key shown once
- Enrollment tokens hashed the same way; shown once
- Policies for Organization, User, AuditLog, Host, Agent, LogEntry, LogSource, Alert, AlertRule, Incident, Dashboard, Application
- Tenant 404 on cross-organization user, host, log-source, alert, incident, dashboard, and application access
- `SUPER_ADMIN` cannot be assigned via organization membership
- Suspended users cannot authenticate
- Suspended organizations block non-super-admins and agent traffic
- Security headers: `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`
- Audit trail for login, logout, user/organization changes, agent register/rotate/revoke, enrollment tokens, alert rules, acknowledge/resolve, incident create/update/resolve, dashboard create/update/delete, application create/update/delete
- Alert webhooks POST JSON with a 5s timeout; failures are logged, not retried blindly
- Secrets stripped from audit payloads (`password`, tokens, `api_key`, enrollment tokens)
- Mass-assignment guarded; `is_super_admin` is never taken from form requests
- Blade `{{ }}` escaping
- Validation on every write endpoint

## Multi-tenant rule

Never trust a client-supplied `organization_id` on a resource body. The tenant comes from `TenantContext` after membership checks.

## Later

- Token rotation for agents is available in the UI
- Encrypted integration credentials, signed webhooks, and stricter Content-Security-Policy once the frontend is stable
