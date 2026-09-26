<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentPromotion;
use App\Models\User;
use App\PromotionResult;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentPromotion>
 */
class StudentPromotionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'academic_year_id' => AcademicYear::factory(),
            'student_id' => Student::factory(),
            'from_class_id' => SchoolClass::factory(),
            'to_class_id' => SchoolClass::factory(),
            'result' => PromotionResult::Promoted,
            'processed_by' => User::factory()->admin(),
        ];
    }

    public function graduated(): static
    {
        return $this->state(['result' => PromotionResult::Graduated, 'to_class_id' => null]);
    }
}
