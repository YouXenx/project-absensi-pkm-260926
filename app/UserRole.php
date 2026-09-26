<?php

namespace App;

enum UserRole: string
{
    case Admin = 'admin';
    case Guru = 'guru';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Guru => 'Guru',
        };
    }

    /**
     * Named route of the dashboard this role lands on after login.
     */
    public function dashboardRoute(): string
    {
        return match ($this) {
            self::Admin => 'admin.dashboard',
            self::Guru => 'guru.dashboard',
        };
    }
}
