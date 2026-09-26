<?php

namespace App;

enum AttendanceStatus: string
{
    case Present = 'hadir';
    case Permission = 'izin';
    case Sick = 'sakit';
    case Absent = 'alpha';

    public function label(): string
    {
        return match ($this) {
            self::Present => 'Hadir',
            self::Permission => 'Izin',
            self::Sick => 'Sakit',
            self::Absent => 'Alpha',
        };
    }
}
