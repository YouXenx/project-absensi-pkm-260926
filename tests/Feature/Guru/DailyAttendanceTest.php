<?php

namespace Tests\Feature\Guru;

use App\AttendanceStatus;
use App\Http\Middleware\AccessDenied;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\HomeroomTeacher;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Policies\AttendancePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Tests\TestCase;

class DailyAttendanceTest extends TestCase
{
    use RefreshDatabase;

    private User $guruA;

    private User $guruB;

    private SchoolClass $class;

    private Subject $subjectA;

    private Subject $subjectB;

    /** @var Collection<int, Student> */
    private Collection $students;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Date::setTestNow('2026-09-14 08:00:00');

        $year = AcademicYear::factory()->active()->create(['year_name' => '2026/2027']);
        $this->guruA = User::factory()->guru()->create();
        $this->guruB = User::factory()->guru()->create();
        $this->class = SchoolClass::factory()->create(['class_name' => 'Kelas 4A']);
        $this->students = Student::factory()->count(3)->for($this->class)->sequence(['name' => 'Ani'], ['name' => 'Budi'], ['name' => 'Citra'])->create();
        Student::factory()->graduated()->for($this->class)->create(['name' => 'Dodi Lulus']);
        HomeroomTeacher::factory()->create(['class_id' => $this->class->class_id, 'academic_year_id' => $year->academic_year_id]);

        $this->subjectA = Subject::factory()->create(['subject_name' => 'Matematika', 'user_id' => $this->guruA->id, 'class_id' => $this->class->class_id, 'academic_year_id' => $year->academic_year_id]);
        $this->subjectB = Subject::factory()->create(['subject_name' => 'IPA', 'user_id' => $this->guruB->id, 'class_id' => $this->class->class_id, 'academic_year_id' => $year->academic_year_id]);
    }

    protected function tearDown(): void
    {
        Date::setTestNow();

        parent::tearDown();
    }

    // ---------------------------------------------------------------- 1. Mapel yang diampu

    public function test_subject_list_only_contains_own_subjects_of_the_active_year(): void
    {
        $oldYear = AcademicYear::factory()->create(['year_name' => '2025/2026']);
        Subject::factory()->create(['subject_name' => 'Mapel Lama', 'user_id' => $this->guruA->id, 'academic_year_id' => $oldYear->academic_year_id]);

        $this->actingAs($this->guruA)->get(route('guru.mapel.index'))->assertOk();

        $this->actingAs($this->guruA)
            ->getJson(route('guru.mapel.data', ['draw' => 1]))
            ->assertOk()
            ->assertJsonPath('recordsTotal', 1)
            ->assertJsonPath('data.0.subject_name', 'Matematika')
            ->assertJsonPath('data.0.active_students_count', 3)
            ->assertJsonMissing(['subject_name' => 'IPA'])
            ->assertJsonMissing(['subject_name' => 'Mapel Lama']);
    }

    // ---------------------------------------------------------------- 2. Daftar siswa

    public function test_choosing_a_subject_shows_the_active_students_of_its_class(): void
    {
        $this->actingAs($this->guruA)
            ->get(route('guru.absensi.index'))
            ->assertOk()
            ->assertSee('Matematika · Kelas 4A')
            ->assertDontSee('IPA · Kelas 4A');

        $this->actingAs($this->guruA)
            ->get(route('guru.absensi.index', ['subject_id' => $this->subjectA->subject_id]))
            ->assertOk()
            ->assertSeeInOrder(['Ani', 'Budi', 'Citra'])
            ->assertDontSee('Dodi Lulus')
            ->assertSee('data-mark-all="hadir"', false)
            ->assertSee('name="date" value="2026-09-14"', false);
    }

    public function test_guru_cannot_open_the_roster_of_another_gurus_subject(): void
    {
        $this->actingAs($this->guruA)
            ->get(route('guru.absensi.index', ['subject_id' => $this->subjectB->subject_id]))
            ->assertRedirect(route('guru.dashboard'))
            ->assertSessionHas('alert.text', AccessDenied::MESSAGE);
    }

    // ---------------------------------------------------------------- 3 & 4. Input

    public function test_bulk_attendance_for_the_whole_class(): void
    {
        [$ani, $budi, $citra] = $this->students;

        $this->actingAs($this->guruA)
            ->post(route('guru.absensi.store', $this->subjectA), [
                'date' => '2026-09-14',
                'attendance' => [
                    $ani->student_id => ['status' => 'hadir'],
                    $budi->student_id => ['status' => 'sakit', 'description' => 'Demam'],
                    $citra->student_id => ['status' => 'hadir'],
                ],
            ])
            ->assertRedirect(route('guru.absensi.index', ['subject_id' => $this->subjectA->subject_id]))
            ->assertSessionHas('alert.text', fn (string $text): bool => str_contains($text, '2 hadir, 0 izin, 1 sakit, 0 alpha'));

        $this->assertSame(3, Attendance::where('subject_id', $this->subjectA->subject_id)->where('date', '2026-09-14')->count());
        $this->assertDatabaseHas('attendances', ['student_id' => $budi->student_id, 'status' => 'sakit', 'description' => 'Demam', 'date' => '2026-09-14']);
    }

    public function test_bulk_save_requires_a_status_for_every_student(): void
    {
        [$ani, $budi] = $this->students;

        $this->actingAs($this->guruA)
            ->post(route('guru.absensi.store', $this->subjectA), [
                'date' => '2026-09-14',
                'attendance' => [$ani->student_id => ['status' => 'hadir'], $budi->student_id => ['description' => 'lupa']],
            ])
            ->assertSessionHasErrors("attendance.{$budi->student_id}.status");

        $this->assertSame(0, Attendance::count());
    }

    public function test_single_student_save_only_stores_that_row(): void
    {
        [$ani, $budi] = $this->students;

        $this->actingAs($this->guruA)
            ->post(route('guru.absensi.store-one', [$this->subjectA, $budi]), [
                'date' => '2026-09-14',
                'attendance' => [
                    $ani->student_id => ['status' => ''],
                    $budi->student_id => ['status' => 'izin', 'description' => 'Acara keluarga'],
                ],
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertSame(1, Attendance::count());
        $this->assertDatabaseHas('attendances', ['student_id' => $budi->student_id, 'status' => 'izin']);
    }

    // ---------------------------------------------------------------- 5. Edit hari ini

    public function test_saving_again_today_updates_instead_of_duplicating(): void
    {
        $ani = $this->students[0];

        foreach (['alpha', 'hadir'] as $status) {
            $this->actingAs($this->guruA)->post(route('guru.absensi.store-one', [$this->subjectA, $ani]), [
                'date' => '2026-09-14',
                'attendance' => [$ani->student_id => ['status' => $status]],
            ])->assertSessionHasNoErrors();
        }

        $this->assertSame(1, Attendance::count());
        $this->assertSame(AttendanceStatus::Present, Attendance::first()->status);
    }

    public function test_guru_can_only_submit_for_today(): void
    {
        $ani = $this->students[0];

        foreach (['2026-09-13', '2026-09-15'] as $date) {
            $this->actingAs($this->guruA)
                ->post(route('guru.absensi.store-one', [$this->subjectA, $ani]), ['date' => $date, 'attendance' => [$ani->student_id => ['status' => 'hadir']]])
                ->assertSessionHasErrors('date');
        }

        $this->assertSame(0, Attendance::count());
    }

    public function test_page_opened_yesterday_cannot_be_submitted_after_midnight(): void
    {
        $ani = $this->students[0];
        $formDate = now()->toDateString();

        Date::setTestNow('2026-09-15 00:05:00');

        $this->actingAs($this->guruA)
            ->post(route('guru.absensi.store-one', [$this->subjectA, $ani]), ['date' => $formDate, 'attendance' => [$ani->student_id => ['status' => 'hadir']]])
            ->assertSessionHasErrors('date');
    }

    public function test_today_record_can_be_edited_by_its_guru_but_is_locked_the_next_day(): void
    {
        $attendance = Attendance::factory()->create(['subject_id' => $this->subjectA->subject_id, 'student_id' => $this->students[0]->student_id, 'date' => '2026-09-14', 'status' => 'alpha']);

        $this->actingAs($this->guruA)->getJson(route('guru.riwayat.edit', $attendance))->assertOk()->assertJsonPath('confirm', null);
        $this->actingAs($this->guruA)
            ->put(route('guru.riwayat.update', $attendance), ['status' => 'hadir', 'description' => 'Terlambat'])
            ->assertRedirect(route('guru.riwayat.index', ['subject_id' => $this->subjectA->subject_id, 'date' => '2026-09-14']))
            ->assertSessionHas('success');
        $this->assertSame(AttendanceStatus::Present, $attendance->fresh()->status);

        Date::setTestNow('2026-09-15 07:00:00');

        $this->actingAs($this->guruA)
            ->put(route('guru.riwayat.update', $attendance), ['status' => 'alpha'])
            ->assertRedirect(route('guru.dashboard'))
            ->assertSessionHas('alert.text', AttendancePolicy::PAST_DATE_MESSAGE);
        $this->actingAs($this->guruA)->getJson(route('guru.riwayat.edit', $attendance))->assertForbidden()->assertJsonPath('message', AttendancePolicy::PAST_DATE_MESSAGE);

        $this->assertSame(AttendanceStatus::Present, $attendance->fresh()->status);
    }

    // ---------------------------------------------------------------- Scoping between gurus

    public function test_guru_cannot_write_or_read_another_gurus_attendance(): void
    {
        $ani = $this->students[0];
        $recordOfB = Attendance::factory()->create(['subject_id' => $this->subjectB->subject_id, 'student_id' => $ani->student_id, 'date' => '2026-09-14', 'status' => 'hadir']);

        $this->actingAs($this->guruA)
            ->post(route('guru.absensi.store', $this->subjectB), ['date' => '2026-09-14', 'attendance' => [$ani->student_id => ['status' => 'alpha']]])
            ->assertRedirect(route('guru.dashboard'));
        $this->actingAs($this->guruA)->put(route('guru.riwayat.update', $recordOfB), ['status' => 'alpha'])->assertRedirect(route('guru.dashboard'));
        $this->actingAs($this->guruA)->get(route('guru.riwayat.edit', $recordOfB))->assertRedirect(route('guru.dashboard'));

        $this->assertSame(AttendanceStatus::Present, $recordOfB->fresh()->status);

        $this->actingAs($this->guruA)
            ->getJson(route('guru.riwayat.data', ['draw' => 1, 'date' => '2026-09-14', 'subject_id' => $this->subjectB->subject_id]))
            ->assertJsonPath('recordsTotal', 0);
    }

    public function test_student_outside_the_class_cannot_be_recorded(): void
    {
        $stranger = Student::factory()->create();

        $this->actingAs($this->guruA)
            ->post(route('guru.absensi.store', $this->subjectA), ['date' => '2026-09-14', 'attendance' => [$stranger->student_id => ['status' => 'hadir']]])
            ->assertSessionHasErrors('attendance');

        $this->assertSame(0, Attendance::count());
    }

    public function test_subjects_of_an_old_year_cannot_take_attendance(): void
    {
        $oldSubject = Subject::factory()->create(['user_id' => $this->guruA->id, 'class_id' => $this->class->class_id, 'academic_year_id' => AcademicYear::factory()->create(['year_name' => '2020/2021'])->academic_year_id]);

        $this->actingAs($this->guruA)
            ->post(route('guru.absensi.store', $oldSubject), ['date' => '2026-09-14', 'attendance' => [$this->students[0]->student_id => ['status' => 'hadir']]])
            ->assertSessionHas('alert.text', 'Absensi hanya bisa diisi untuk mata pelajaran di tahun ajaran aktif.');

        $this->assertSame(0, Attendance::count());
    }

    // ---------------------------------------------------------------- 6. Rekap per tanggal

    public function test_daily_history_filters_by_subject_and_date(): void
    {
        [$ani, $budi] = $this->students;
        Attendance::factory()->create(['subject_id' => $this->subjectA->subject_id, 'student_id' => $ani->student_id, 'date' => '2026-09-14', 'status' => 'hadir']);
        Attendance::factory()->create(['subject_id' => $this->subjectA->subject_id, 'student_id' => $budi->student_id, 'date' => '2026-09-14', 'status' => 'sakit']);
        Attendance::factory()->create(['subject_id' => $this->subjectA->subject_id, 'student_id' => $ani->student_id, 'date' => '2026-09-11', 'status' => 'alpha']);
        Attendance::factory()->create(['subject_id' => $this->subjectB->subject_id, 'student_id' => $ani->student_id, 'date' => '2026-09-14', 'status' => 'izin']);

        $this->actingAs($this->guruA)
            ->get(route('guru.riwayat.index'))
            ->assertOk()
            ->assertViewHas('summary', fn ($summary): bool => $summary->all() === ['hadir' => 1, 'izin' => 0, 'sakit' => 1, 'alpha' => 0]);

        $this->actingAs($this->guruA)
            ->getJson(route('guru.riwayat.data', ['draw' => 1]))
            ->assertJsonPath('recordsTotal', 2);

        $response = $this->actingAs($this->guruA)
            ->getJson(route('guru.riwayat.data', ['draw' => 2, 'date' => '2026-09-11', 'subject_id' => $this->subjectA->subject_id]))
            ->assertJsonPath('recordsTotal', 1)
            ->assertJsonPath('data.0.date', '11/09/2026');

        $this->assertStringContainsString('Terkunci', $response->json('data.0.actions'));
    }
}
