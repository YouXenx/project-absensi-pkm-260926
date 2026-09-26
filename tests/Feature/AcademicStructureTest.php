<?php

namespace Tests\Feature;

use App\AttendanceStatus;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\HomeroomTeacher;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class AcademicStructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_relationships_resolve_in_both_directions(): void
    {
        $year = AcademicYear::factory()->active()->create(['year_name' => '2025/2026']);
        $guru = User::factory()->guru()->create();
        $class = SchoolClass::factory()->create();
        $student = Student::factory()->for($class)->create();

        $homeroom = HomeroomTeacher::factory()->create(['class_id' => $class->class_id, 'user_id' => $guru->id, 'academic_year_id' => $year->academic_year_id]);
        $subject = Subject::factory()->create(['user_id' => $guru->id, 'academic_year_id' => $year->academic_year_id, 'class_id' => $class->class_id]);
        $attendance = Attendance::factory()->create(['subject_id' => $subject->subject_id, 'student_id' => $student->student_id, 'status' => AttendanceStatus::Sick]);

        $this->assertTrue($homeroom->teacher->is($guru));
        $this->assertTrue($homeroom->schoolClass->is($class));
        $this->assertTrue($homeroom->academicYear->is($year));

        $this->assertTrue($subject->teacher->is($guru));
        $this->assertTrue($subject->schoolClass->is($class));
        $this->assertTrue($subject->academicYear->is($year));
        $this->assertTrue($subject->attendances->first()->is($attendance));

        $this->assertTrue($attendance->subject->is($subject));
        $this->assertTrue($attendance->student->is($student));
        $this->assertSame(AttendanceStatus::Sick, $attendance->status);

        $this->assertTrue($year->homeroomTeachers->first()->is($homeroom));
        $this->assertTrue($year->subjects->first()->is($subject));
        $this->assertTrue($class->homeroomTeachers->first()->is($homeroom));
        $this->assertTrue($class->subjects->first()->is($subject));
        $this->assertTrue($student->attendances->first()->is($attendance));
        $this->assertTrue($guru->homeroomAssignments->first()->is($homeroom));
        $this->assertTrue($guru->subjects->first()->is($subject));
    }

    public function test_only_one_academic_year_can_be_active(): void
    {
        $first = AcademicYear::factory()->active()->create();
        $second = AcademicYear::factory()->create();

        $second->activate();

        $this->assertFalse($first->fresh()->is_active);
        $this->assertTrue($second->fresh()->is_active);
        $this->assertTrue(AcademicYear::current()->is($second));

        $this->expectException(UniqueConstraintViolationException::class);
        AcademicYear::factory()->active()->create();
    }

    public function test_assignments_must_point_to_a_guru_account(): void
    {
        $admin = User::factory()->admin()->create();

        $this->expectException(InvalidArgumentException::class);
        Subject::factory()->create(['user_id' => $admin->id]);
    }

    public function test_homeroom_assignments_require_a_guru_account_too(): void
    {
        $this->expectException(InvalidArgumentException::class);
        HomeroomTeacher::factory()->create(['user_id' => User::factory()->admin()]);
    }

    public function test_one_homeroom_teacher_per_class_per_year(): void
    {
        $homeroom = HomeroomTeacher::factory()->create();

        $this->expectException(UniqueConstraintViolationException::class);
        HomeroomTeacher::factory()->create([
            'class_id' => $homeroom->class_id,
            'academic_year_id' => $homeroom->academic_year_id,
        ]);
    }

    public function test_one_attendance_per_student_per_subject_per_day(): void
    {
        $attendance = Attendance::factory()->create(['date' => '2026-09-14']);

        $this->expectException(UniqueConstraintViolationException::class);
        Attendance::factory()->create([
            'subject_id' => $attendance->subject_id,
            'student_id' => $attendance->student_id,
            'date' => '2026-09-14',
        ]);
    }

    public function test_history_cannot_be_deleted_through_its_parents(): void
    {
        $attendance = Attendance::factory()->create();

        foreach ([$attendance->subject, $attendance->student, $attendance->subject->academicYear] as $parent) {
            try {
                $parent->delete();
                $this->fail(class_basename($parent).' should be protected by ON DELETE RESTRICT.');
            } catch (QueryException) {
                $this->assertModelExists($parent);
            }
        }

        $this->assertModelExists($attendance);
    }

    public function test_deleting_an_empty_class_removes_its_homeroom_assignment(): void
    {
        $homeroom = HomeroomTeacher::factory()->create();

        $homeroom->schoolClass->delete();

        $this->assertModelMissing($homeroom);
    }

    public function test_subjects_can_be_scoped_to_one_guru(): void
    {
        $guruA = User::factory()->guru()->create();
        $guruB = User::factory()->guru()->create();
        Subject::factory()->count(2)->create(['user_id' => $guruA->id]);
        Subject::factory()->create(['user_id' => $guruB->id]);

        $this->assertSame(2, Subject::forTeacher($guruA)->count());
        $this->assertSame(1, Subject::forTeacher($guruB->id)->count());
    }

    public function test_admin_gets_a_sweetalert_error_instead_of_a_crash_when_deleting_protected_data(): void
    {
        $this->withoutVite();
        $admin = User::factory()->admin()->create();

        $subject = Subject::factory()->create();
        $this->actingAs($admin)
            ->delete(route('admin.kelas.destroy', $subject->class_id))
            ->assertRedirect(route('admin.kelas.index'))
            ->assertSessionHas('error', fn (string $message): bool => str_contains($message, '1 mapel'));
        $this->assertModelExists($subject->schoolClass);

        $attendance = Attendance::factory()->create();
        $this->actingAs($admin)
            ->delete(route('admin.siswa.destroy', $attendance->student_id))
            ->assertRedirect(route('admin.siswa.index'))
            ->assertSessionHas('error', fn (string $message): bool => str_contains($message, '1 data absensi'));
        $this->assertModelExists($attendance->student);
    }
}
