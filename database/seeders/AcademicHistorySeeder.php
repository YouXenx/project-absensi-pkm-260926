<?php

namespace Database\Seeders;

use App\AttendanceStatus;
use App\Models\AcademicYear;
use App\Models\HomeroomTeacher;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AcademicHistorySeeder extends Seeder
{
    public const YEAR_NAME = '2025/2026';

    public const SUBJECTS = ['Matematika', 'Bahasa Indonesia', 'IPA'];

    /**
     * A completed academic year to test the yearly flow against: homeroom teachers, subjects and
     * semester-2 attendance for every class, taught by the seeded guru accounts.
     * Skipped when any academic year already exists.
     */
    public function run(): void
    {
        if (AcademicYear::query()->exists()) {
            $this->command?->warn('Tahun ajaran sudah ada, AcademicHistorySeeder dilewati.');

            return;
        }

        $teachers = User::teachers()->active()->orderBy('id')->get();

        if ($teachers->isEmpty()) {
            $this->command?->warn('Belum ada akun guru aktif, jalankan UserSeeder terlebih dahulu.');

            return;
        }

        $year = AcademicYear::create(['year_name' => self::YEAR_NAME]);
        $year->activate();

        $schoolDays = collect(range(0, 13))
            ->map(fn (int $offset): CarbonImmutable => CarbonImmutable::create(2026, 5, 4)->addDays($offset))
            ->reject(fn (CarbonImmutable $day): bool => $day->isWeekend())
            ->values();

        SchoolClass::query()->with(['students' => fn ($query) => $query->active()])->orderBy('class_name')->get()
            ->each(function (SchoolClass $schoolClass, int $classIndex) use ($teachers, $year, $schoolDays): void {
                HomeroomTeacher::create([
                    'class_id' => $schoolClass->class_id,
                    'user_id' => $teachers[$classIndex % $teachers->count()]->id,
                    'academic_year_id' => $year->academic_year_id,
                ]);

                foreach (self::SUBJECTS as $subjectIndex => $subjectName) {
                    $subject = Subject::create([
                        'subject_name' => $subjectName,
                        'user_id' => $teachers[($classIndex + $subjectIndex) % $teachers->count()]->id,
                        'class_id' => $schoolClass->class_id,
                        'academic_year_id' => $year->academic_year_id,
                    ]);

                    $rows = [];

                    foreach ($schoolClass->students as $student) {
                        foreach ($schoolDays as $day) {
                            $status = fake()->randomElement([
                                ...array_fill(0, 16, AttendanceStatus::Present),
                                AttendanceStatus::Permission,
                                AttendanceStatus::Sick,
                                AttendanceStatus::Absent,
                            ]);

                            $rows[] = [
                                'subject_id' => $subject->subject_id,
                                'student_id' => $student->student_id,
                                'date' => $day->toDateString(),
                                'status' => $status->value,
                                'description' => $status === AttendanceStatus::Sick ? 'Sakit' : null,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];
                        }
                    }

                    DB::table('attendances')->insert($rows);
                }
            });
    }
}
