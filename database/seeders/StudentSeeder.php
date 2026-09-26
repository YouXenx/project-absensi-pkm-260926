<?php

namespace Database\Seeders;

use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Database\Seeder;

class StudentSeeder extends Seeder
{
    public const STUDENTS_PER_CLASS = 8;

    /**
     * Dummy students for every class that has none yet, so re-running never duplicates data.
     */
    public function run(): void
    {
        SchoolClass::query()
            ->whereDoesntHave('students')
            ->each(function (SchoolClass $schoolClass): void {
                Student::factory()
                    ->count(self::STUDENTS_PER_CLASS)
                    ->for($schoolClass)
                    ->create();
            });
    }
}
