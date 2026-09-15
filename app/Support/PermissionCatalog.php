<?php

namespace App\Support;

use App\Enums\RoleName;

final class PermissionCatalog
{
    public const ORGANIZATIONS_VIEW = 'organizations.view';

    public const ORGANIZATIONS_UPDATE = 'organizations.update';

    public const ORGANIZATIONS_MANAGE = 'organizations.manage';

    public const USERS_VIEW = 'users.view';

    public const USERS_CREATE = 'users.create';

    public const USERS_UPDATE = 'users.update';

    public const USERS_DELETE = 'users.delete';

    public const ROLES_VIEW = 'roles.view';

    public const SETTINGS_UPDATE = 'settings.update';

    public const AUDIT_VIEW = 'audit.view';

    public const HOSTS_VIEW = 'hosts.view';

    public const HOSTS_UPDATE = 'hosts.update';

    public const AGENTS_VIEW = 'agents.view';

    public const AGENTS_MANAGE = 'agents.manage';

    public const METRICS_VIEW = 'metrics.view';

    public const LOGS_VIEW = 'logs.view';

    public const LOGS_MANAGE = 'logs.manage';

    public const ALERTS_VIEW = 'alerts.view';

    public const ALERTS_UPDATE = 'alerts.update';

    public const ALERTS_MANAGE = 'alerts.manage';

    public const INCIDENTS_VIEW = 'incidents.view';

    public const INCIDENTS_UPDATE = 'incidents.update';

    public const DASHBOARDS_VIEW = 'dashboards.view';

    public const DASHBOARDS_MANAGE = 'dashboards.manage';

    public const APPLICATIONS_VIEW = 'applications.view';

    public const APPLICATIONS_MANAGE = 'applications.manage';

    /**
     * @return array<string, string>
     */
    public static function all(): array
    {
        return [
            self::ORGANIZATIONS_VIEW => 'View the current organization',
            self::ORGANIZATIONS_UPDATE => 'Update organization profile and retention settings',
            self::ORGANIZATIONS_MANAGE => 'Create, suspend, and delete organizations',
            self::USERS_VIEW => 'View organization users',
            self::USERS_CREATE => 'Invite or create organization users',
            self::USERS_UPDATE => 'Update organization users and roles',
            self::USERS_DELETE => 'Remove users from the organization',
            self::ROLES_VIEW => 'View roles and permissions',
            self::SETTINGS_UPDATE => 'Update organization settings',
            self::AUDIT_VIEW => 'View audit logs',
            self::HOSTS_VIEW => 'View hosts and infrastructure status',
            self::HOSTS_UPDATE => 'Update host display name and environment',
            self::AGENTS_VIEW => 'View agents and enrollment tokens',
            self::AGENTS_MANAGE => 'Enroll, rotate, and revoke agents',
            self::METRICS_VIEW => 'View host metrics and charts',
            self::LOGS_VIEW => 'Search and export logs',
            self::LOGS_MANAGE => 'Pause or resume log sources',
            self::ALERTS_VIEW => 'View alerts and alert rules',
            self::ALERTS_UPDATE => 'Acknowledge and resolve alerts',
            self::ALERTS_MANAGE => 'Create and edit alert rules',
            self::INCIDENTS_VIEW => 'View incidents and timelines',
            self::INCIDENTS_UPDATE => 'Create, assign, comment, and resolve incidents',
            self::DASHBOARDS_VIEW => 'View saved dashboards',
            self::DASHBOARDS_MANAGE => 'Create and edit dashboards and widgets',
            self::APPLICATIONS_VIEW => 'View applications and APM samples',
            self::APPLICATIONS_MANAGE => 'Create and edit applications',
        ];
    }

    /**
     * @return list<string>
     */
    public static function forRole(RoleName $role): array
    {
        return match ($role) {
            RoleName::SuperAdmin => array_keys(self::all()),
            RoleName::Admin => [
                self::ORGANIZATIONS_VIEW,
                self::ORGANIZATIONS_UPDATE,
                self::USERS_VIEW,
                self::USERS_CREATE,
                self::USERS_UPDATE,
                self::USERS_DELETE,
                self::ROLES_VIEW,
                self::SETTINGS_UPDATE,
                self::AUDIT_VIEW,
                self::HOSTS_VIEW,
                self::HOSTS_UPDATE,
                self::AGENTS_VIEW,
                self::AGENTS_MANAGE,
                self::METRICS_VIEW,
                self::LOGS_VIEW,
                self::LOGS_MANAGE,
                self::ALERTS_VIEW,
                self::ALERTS_UPDATE,
                self::ALERTS_MANAGE,
                self::INCIDENTS_VIEW,
                self::INCIDENTS_UPDATE,
                self::DASHBOARDS_VIEW,
                self::DASHBOARDS_MANAGE,
                self::APPLICATIONS_VIEW,
                self::APPLICATIONS_MANAGE,
            ],
            RoleName::Analyst => [
                self::ORGANIZATIONS_VIEW,
                self::USERS_VIEW,
                self::ROLES_VIEW,
                self::AUDIT_VIEW,
                self::HOSTS_VIEW,
                self::AGENTS_VIEW,
                self::METRICS_VIEW,
                self::LOGS_VIEW,
                self::ALERTS_VIEW,
                self::ALERTS_UPDATE,
                self::INCIDENTS_VIEW,
                self::INCIDENTS_UPDATE,
                self::DASHBOARDS_VIEW,
                self::DASHBOARDS_MANAGE,
                self::APPLICATIONS_VIEW,
            ],
            RoleName::Operator => [
                self::ORGANIZATIONS_VIEW,
                self::USERS_VIEW,
                self::ROLES_VIEW,
                self::HOSTS_VIEW,
                self::AGENTS_VIEW,
                self::METRICS_VIEW,
                self::LOGS_VIEW,
                self::ALERTS_VIEW,
                self::ALERTS_UPDATE,
                self::INCIDENTS_VIEW,
                self::INCIDENTS_UPDATE,
                self::DASHBOARDS_VIEW,
                self::APPLICATIONS_VIEW,
            ],
            RoleName::Viewer => [
                self::ORGANIZATIONS_VIEW,
                self::USERS_VIEW,
                self::ROLES_VIEW,
                self::HOSTS_VIEW,
                self::METRICS_VIEW,
                self::LOGS_VIEW,
                self::ALERTS_VIEW,
                self::INCIDENTS_VIEW,
                self::DASHBOARDS_VIEW,
                self::APPLICATIONS_VIEW,
            ],
        };
    }
}
