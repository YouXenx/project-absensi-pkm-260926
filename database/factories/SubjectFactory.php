<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subject>
 */
class SubjectFactory extends Factory
{
    public const NAMES = [
        'Matematika', 'Bahasa Indonesia', 'Bahasa Inggris', 'IPA', 'IPS',
        'Pendidikan Pancasila', 'Pendidikan Agama', 'PJOK', 'Seni Budaya', 'Informatika',
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subject_name' => fake()->randomElement(self::NAMES).' '.fake()->unique()->numerify('###'),
            'user_id' => User::factory()->guru(),
            'academic_year_id' => AcademicYear::factory(),
            'class_id' => SchoolClass::factory(),
        ];
    }
}
