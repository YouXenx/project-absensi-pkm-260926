<?php

namespace Tests\Feature;

use App\AttendanceStatus;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceRecapTest extends TestCase
{
    use RefreshDatabase;

    private AcademicYear $year;

    private User $guruA;

    private User $guruB;

    private Subject $subjectA;

    private Subject $subjectB;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->year = AcademicYear::factory()->active()->create(['year_name' => '2026/2027']);
        $class = SchoolClass::factory()->create(['class_name' => 'Kelas 3A']);
        $this->student = Student::factory()->for($class)->create(['name' => 'Rina Rekap']);
        $this->guruA = User::factory()->guru()->create();
        $this->guruB = User::factory()->guru()->create();
        $this->subjectA = Subject::factory()->create(['subject_name' => 'Matematika', 'user_id' => $this->guruA->id, 'class_id' => $class->class_id, 'academic_year_id' => $this->year->academic_year_id]);
        $this->subjectB = Subject::factory()->create(['subject_name' => 'Bahasa Inggris', 'user_id' => $this->guruB->id, 'class_id' => $class->class_id, 'academic_year_id' => $this->year->academic_year_id]);

        // Semester 1 of 2026/2027 = Jul–Dec 2026.
        foreach (['2026-08-03' => 'hadir', '2026-08-04' => 'sakit', '2026-08-05' => 'alpha'] as $date => $status) {
            Attendance::factory()->create(['subject_id' => $this->subjectA->subject_id, 'student_id' => $this->student->student_id, 'date' => $date, 'status' => $status]);
        }
        foreach (['2026-08-03' => 'izin', '2026-08-04' => 'hadir'] as $date => $status) {
            Attendance::factory()->create(['subject_id' => $this->subjectB->subject_id, 'student_id' => $this->student->student_id, 'date' => $date, 'status' => $status]);
        }
        // Semester 2 record, excluded from semester 1.
        Attendance::factory()->create(['subject_id' => $this->subjectA->subject_id, 'student_id' => $this->student->student_id, 'date' => '2027-02-01', 'status' => 'hadir']);
    }

    public function test_semester_ranges_follow_the_academic_year(): void
    {
        [$from, $to] = $this->year->semesterRange(1);
        $this->assertSame(['2026-07-01', '2026-12-31'], [$from->toDateString(), $to->toDateString()]);

        [$from, $to] = $this->year->semesterRange(2);
        $this->assertSame(['2027-01-01', '2027-06-30'], [$from->toDateString(), $to->toDateString()]);
    }

    public function test_admin_sees_all_subjects(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.laporan.index', ['semester' => 1]))
            ->assertOk()
            ->assertViewHas('totals', fn ($totals): bool => $totals['total'] === 5 && $totals['hadir'] === 2 && $totals['izin'] === 1 && $totals['sakit'] === 1 && $totals['alpha'] === 1)
            ->assertSee('Rina Rekap')
            ->assertSee('Bahasa Inggris · Kelas 3A');
    }

    public function test_guru_only_sees_subjects_they_teach(): void
    {
        $this->actingAs($this->guruA)
            ->get(route('guru.rekap.index', ['semester' => 1]))
            ->assertOk()
            ->assertViewHas('totals', fn ($totals): bool => $totals['total'] === 3 && $totals[AttendanceStatus::Permission->value] === 0)
            ->assertSee('Matematika · Kelas 3A')
            ->assertDontSee('Bahasa Inggris');
    }

    public function test_guru_cannot_read_another_gurus_subject_by_changing_the_url(): void
    {
        $this->actingAs($this->guruA)
            ->get(route('guru.rekap.index', ['semester' => 1, 'subject_id' => $this->subjectB->subject_id]))
            ->assertOk()
            ->assertViewHas('selectedSubject', null)
            ->assertViewHas('totals', fn ($totals): bool => $totals['total'] === 3)
            ->assertDontSee('Bahasa Inggris');
    }

    public function test_filters_by_subject_and_custom_date_range(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.laporan.index', ['semester' => 1, 'subject_id' => $this->subjectB->subject_id]))
            ->assertViewHas('totals', fn ($totals): bool => $totals['total'] === 2);

        $this->actingAs($admin)
            ->get(route('admin.laporan.index', ['semester' => 'custom', 'date_from' => '2026-08-04', 'date_to' => '2027-02-01']))
            ->assertViewHas('totals', fn ($totals): bool => $totals['total'] === 4);

        $this->actingAs($admin)
            ->get(route('admin.laporan.index', ['semester' => 'custom', 'date_from' => '2026-09-01', 'date_to' => '2026-08-01']))
            ->assertSessionHasErrors('date_to');
    }

    public function test_guru_without_subjects_sees_an_empty_state(): void
    {
        $this->actingAs(User::factory()->guru()->create())
            ->get(route('guru.rekap.index'))
            ->assertOk()
            ->assertSee('Anda belum memiliki mata pelajaran');
    }

    public function test_admin_cannot_use_the_guru_recap_url_and_vice_versa(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get(route('guru.rekap.index'))->assertRedirect(route('admin.dashboard'));
        $this->actingAs($this->guruA)->get(route('admin.laporan.index'))->assertRedirect(route('guru.dashboard'));
    }
}
