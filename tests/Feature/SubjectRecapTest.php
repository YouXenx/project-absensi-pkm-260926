<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Tests\TestCase;

/**
 * "Rekap per Mapel": attendance percentage per student for one subject.
 */
class SubjectRecapTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $guru;

    private Subject $ownSubject;

    private Subject $otherSubject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Date::setTestNow('2026-03-10 08:00:00');

        $this->admin = User::factory()->admin()->create();
        $this->guru = User::factory()->guru()->create(['name' => 'Bu Sari']);
        $year = AcademicYear::factory()->active()->create(['year_name' => '2025/2026']);
        $class = SchoolClass::factory()->create(['class_name' => 'Kelas 4A']);

        $this->ownSubject = Subject::factory()->create([
            'subject_name' => 'Matematika',
            'user_id' => $this->guru->id,
            'class_id' => $class->class_id,
            'academic_year_id' => $year->academic_year_id,
        ]);
        $this->otherSubject = Subject::factory()->create([
            'subject_name' => 'IPA',
            'class_id' => $class->class_id,
            'academic_year_id' => $year->academic_year_id,
        ]);

        // Ani: 3 of 4 present (75%), Budi: 4 of 4 (100%), across three meeting days.
        $ani = Student::factory()->for($class)->create(['name' => 'Ani', 'nis' => '4001']);
        $budi = Student::factory()->for($class)->create(['name' => 'Budi', 'nis' => '4002']);

        foreach (['2026-03-02', '2026-03-03', '2026-03-04', '2026-03-05'] as $index => $date) {
            Attendance::factory()->create(['subject_id' => $this->ownSubject->subject_id, 'student_id' => $ani->student_id, 'date' => $date, 'status' => $index === 0 ? 'sakit' : 'hadir']);
            Attendance::factory()->create(['subject_id' => $this->ownSubject->subject_id, 'student_id' => $budi->student_id, 'date' => $date, 'status' => 'hadir']);
        }

        Attendance::factory()->create(['subject_id' => $this->otherSubject->subject_id, 'student_id' => $ani->student_id, 'date' => '2026-03-02', 'status' => 'alpha']);
    }

    protected function tearDown(): void
    {
        Date::setTestNow();

        parent::tearDown();
    }

    public function test_admin_sees_the_percentage_per_student_for_the_selected_subject(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.laporan.mapel', ['subject_id' => $this->ownSubject->subject_id, 'semester' => 2]))
            ->assertOk()
            ->assertSee('Rekap per Mata Pelajaran')
            ->assertSee('Matematika · Kelas 4A')
            ->assertSeeInOrder(['Ani', '75', 'Budi', '100']);

        $this->assertSame(4, $response->viewData('meetings'));
        $this->assertSame(87.5, $response->viewData('averageAttendance'));
        $this->assertSame(['hadir' => 7, 'izin' => 0, 'sakit' => 1, 'alpha' => 0, 'total' => 8], $response->viewData('totals')->all());
    }

    public function test_guru_only_gets_their_own_subjects_even_with_another_subject_id(): void
    {
        $response = $this->actingAs($this->guru)
            ->get(route('guru.rekap.mapel', ['subject_id' => $this->otherSubject->subject_id, 'semester' => 2]))
            ->assertOk();

        // The id of a colleague's subject falls back to the guru's own subject; no foreign data is shown.
        $this->assertTrue($response->viewData('selectedSubject')->is($this->ownSubject));
        $this->assertSame([$this->ownSubject->subject_id], $response->viewData('subjects')->pluck('subject_id')->all());
        $response->assertDontSee('IPA');
    }

    public function test_a_range_without_meetings_is_reported_as_empty(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.laporan.mapel', ['subject_id' => $this->ownSubject->subject_id, 'semester' => 'custom', 'date_from' => '2026-05-01', 'date_to' => '2026-05-31']))
            ->assertOk()
            ->assertViewHas('meetings', 0)
            ->assertViewHas('averageAttendance', 0.0)
            ->assertSee('Belum ada absensi untuk mapel ini pada periode tersebut.');
    }

    public function test_the_page_is_linked_from_both_sidebars(): void
    {
        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertSee('href="'.route('admin.laporan.mapel').'"', false);
        $this->actingAs($this->guru)->get(route('guru.dashboard'))->assertSee('href="'.route('guru.rekap.mapel').'"', false);
    }
}
