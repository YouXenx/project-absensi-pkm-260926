<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\HomeroomTeacher;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HomeroomTeacher>
 */
class HomeroomTeacherFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'class_id' => SchoolClass::factory(),
            'user_id' => User::factory()->guru(),
            'academic_year_id' => AcademicYear::factory(),
        ];
    }
}
