<?php

namespace App\Enums;

enum RoleName: string
{
    case SuperAdmin = 'SUPER_ADMIN';
    case Admin = 'ADMIN';
    case Analyst = 'ANALYST';
    case Operator = 'OPERATOR';
    case Viewer = 'VIEWER';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::Admin => 'Admin',
            self::Analyst => 'Analyst',
            self::Operator => 'Operator',
            self::Viewer => 'Viewer',
        };
    }

    public function isAssignableToOrganization(): bool
    {
        return $this !== self::SuperAdmin;
    }

    /**
     * @return list<self>
     */
    public static function organizationRoles(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $role): bool => $role->isAssignableToOrganization(),
        ));
    }
}
