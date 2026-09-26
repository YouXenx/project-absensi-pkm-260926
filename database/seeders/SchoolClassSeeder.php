<?php

namespace Database\Seeders;

use App\Models\SchoolClass;
use Illuminate\Database\Seeder;

class SchoolClassSeeder extends Seeder
{
    /**
     * Kelas 1A, 1B ... 6B. Safe to run more than once.
     */
    public function run(): void
    {
        foreach (range(1, 6) as $grade) {
            foreach (['A', 'B'] as $section) {
                SchoolClass::firstOrCreate(['class_name' => "Kelas {$grade}{$section}"]);
            }
        }
    }
}
