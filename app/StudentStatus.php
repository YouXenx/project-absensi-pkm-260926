<?php

namespace App;

/**
 * Where a student stands. Only "aktif" students are part of class rosters, attendance and the yearly promotion;
 * the other three keep the student (and their attendance history) in the database without showing up in daily work.
 */
enum StudentStatus: string
{
    case Active = 'aktif';
    case Transferred = 'pindah';
    case Graduated = 'lulus';
    case Dropped = 'keluar';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktif',
            self::Transferred => 'Pindah sekolah',
            self::Graduated => 'Lulus',
            self::Dropped => 'Keluar',
        };
    }

    /**
     * Adminator badge classes for partials/student-status.blade.php.
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::Active => 'success dot',
            self::Transferred => 'info',
            self::Graduated => 'purple',
            self::Dropped => 'danger',
        };
    }

    /**
     * Enrolled students appear in rosters, attendance and promotion.
     */
    public function isEnrolled(): bool
    {
        return $this === self::Active;
    }

    /**
     * Statuses that mean the student left the school during the year.
     *
     * @return list<self>
     */
    public static function exits(): array
    {
        return [self::Transferred, self::Dropped];
    }
}
