<?php

namespace Database\Factories;

use App\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attendance>
 */
class AttendanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $status = fake()->randomElement(AttendanceStatus::cases());

        return [
            'subject_id' => Subject::factory(),
            'student_id' => Student::factory(),
            'date' => fake()->unique()->dateTimeBetween('-120 days', 'now')->format('Y-m-d'),
            'status' => $status,
            'description' => in_array($status, [AttendanceStatus::Permission, AttendanceStatus::Sick], true) ? fake('id_ID')->sentence(4) : null,
        ];
    }
}
