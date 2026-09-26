<?php

namespace Database\Factories;

use App\Gender;
use App\Models\SchoolClass;
use App\Models\Student;
use App\StudentStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $gender = fake()->randomElement(Gender::cases());

        return [
            'nis' => fake()->unique()->numerify('2026######'),
            'name' => fake('id_ID')->name($gender === Gender::Male ? 'male' : 'female'),
            'class_id' => SchoolClass::factory(),
            'gender' => $gender,
            'status' => StudentStatus::Active,
        ];
    }

    public function graduated(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => StudentStatus::Graduated,
        ]);
    }
}
