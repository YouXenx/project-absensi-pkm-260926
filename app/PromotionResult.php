<?php

namespace App;

enum PromotionResult: string
{
    case Promoted = 'naik';
    case Retained = 'tinggal';
    case Graduated = 'lulus';

    public function label(): string
    {
        return match ($this) {
            self::Promoted => 'Naik kelas',
            self::Retained => 'Tinggal kelas',
            self::Graduated => 'Lulus',
        };
    }
}
