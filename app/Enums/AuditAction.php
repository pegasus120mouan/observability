<?php

namespace App\Enums;

enum AuditAction: string
{
    case Login = 'login';
    case Logout = 'logout';
    case LoginFailed = 'login_failed';
    case UserCreated = 'user.created';
    case UserUpdated = 'user.updated';
    case UserDeleted = 'user.deleted';
    case PasswordChanged = 'user.password_changed';
    case OrganizationCreated = 'organization.created';
    case OrganizationUpdated = 'organization.updated';
    case OrganizationDeleted = 'organization.deleted';
    case OrganizationSwitched = 'organization.switched';
    case AgentRegistered = 'agent.registered';
    case AgentRevoked = 'agent.revoked';
    case AgentKeyRotated = 'agent.key_rotated';
    case EnrollmentTokenCreated = 'agent.enrollment_created';
    case EnrollmentTokenRevoked = 'agent.enrollment_revoked';
    case AlertRuleCreated = 'alert_rule.created';
    case AlertRuleUpdated = 'alert_rule.updated';
    case AlertRuleDeleted = 'alert_rule.deleted';
    case AlertAcknowledged = 'alert.acknowledged';
    case AlertResolved = 'alert.resolved';
    case IncidentCreated = 'incident.created';
    case IncidentUpdated = 'incident.updated';
    case IncidentResolved = 'incident.resolved';
    case DashboardCreated = 'dashboard.created';
    case DashboardUpdated = 'dashboard.updated';
    case DashboardDeleted = 'dashboard.deleted';
    case ApplicationCreated = 'application.created';
    case ApplicationUpdated = 'application.updated';
    case ApplicationDeleted = 'application.deleted';
}
