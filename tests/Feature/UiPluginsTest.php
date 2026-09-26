<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Requests\DataTableRequest;
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
 * Server side of the UI plugins: export mode for DataTables Buttons, dashboard chart data,
 * and the markup hooks that make each page load only the plugins it needs.
 */
class UiPluginsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Date::setTestNow('2026-09-16 10:00:00');
        $this->admin = User::factory()->admin()->create();
    }

    protected function tearDown(): void
    {
        Date::setTestNow();

        parent::tearDown();
    }

    public function test_export_mode_returns_every_filtered_row_instead_of_one_page(): void
    {
        $classA = SchoolClass::factory()->create(['class_name' => 'Kelas 1A']);
        Student::factory()->count(130)->for($classA)->create();
        Student::factory()->count(5)->create();

        $this->actingAs($this->admin)
            ->getJson(route('admin.siswa.data', ['draw' => 1, 'length' => 10, 'class_id' => $classA->class_id]))
            ->assertJsonCount(10, 'data');

        $this->actingAs($this->admin)
            ->getJson(route('admin.siswa.data', ['draw' => 1, 'length' => -1, 'export' => 1, 'class_id' => $classA->class_id]))
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 130)
            ->assertJsonCount(130, 'data');
    }

    public function test_all_rows_can_only_be_requested_in_export_mode(): void
    {
        $this->actingAs($this->admin)
            ->getJson(route('admin.siswa.data', ['draw' => 1, 'length' => -1]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('length');

        $this->actingAs($this->admin)
            ->getJson(route('admin.siswa.data', ['draw' => 1, 'length' => 500]))
            ->assertUnprocessable();

        $this->assertSame(5000, DataTableRequest::MAX_EXPORT_ROWS);
    }

    public function test_export_mode_keeps_the_guru_scope(): void
    {
        $guru = User::factory()->guru()->create();
        $year = AcademicYear::factory()->active()->create();
        $own = Subject::factory()->create(['user_id' => $guru->id, 'academic_year_id' => $year->academic_year_id]);
        Attendance::factory()->count(3)->sequence(fn ($sequence) => ['date' => '2026-09-16', 'subject_id' => $own->subject_id])->create();
        Attendance::factory()->count(4)->create(['date' => '2026-09-16']);

        $this->actingAs($guru)
            ->getJson(route('guru.riwayat.data', ['draw' => 1, 'length' => -1, 'export' => 1, 'date' => '2026-09-16']))
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_dashboard_charts_count_students_per_class_and_attendance_per_week(): void
    {
        $classA = SchoolClass::factory()->create(['class_name' => 'Kelas 1A']);
        SchoolClass::factory()->create(['class_name' => 'Kelas 1B']);
        $students = Student::factory()->count(4)->for($classA)->create();
        Student::factory()->graduated()->for($classA)->create();

        $subject = Subject::factory()->create(['class_id' => $classA->class_id]);
        // Week of Monday 14/09/2026 (the latest week with data) and week of Monday 03/08/2026.
        Attendance::factory()->for($students[0])->create(['subject_id' => $subject->subject_id, 'date' => '2026-09-14', 'status' => 'hadir']);
        Attendance::factory()->for($students[1])->create(['subject_id' => $subject->subject_id, 'date' => '2026-09-15', 'status' => 'hadir']);
        Attendance::factory()->for($students[2])->create(['subject_id' => $subject->subject_id, 'date' => '2026-09-15', 'status' => 'sakit']);
        Attendance::factory()->for($students[3])->create(['subject_id' => $subject->subject_id, 'date' => '2026-08-05', 'status' => 'alpha']);
        // Older than eight weeks and in the future: both ignored.
        Attendance::factory()->for($students[0])->create(['subject_id' => $subject->subject_id, 'date' => '2026-06-01', 'status' => 'hadir']);
        Attendance::factory()->for($students[1])->create(['subject_id' => $subject->subject_id, 'date' => '2026-09-30', 'status' => 'hadir']);

        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk();

        $response->assertViewHas('classChart', fn (array $chart): bool => $chart['labels']->all() === ['Kelas 1A', 'Kelas 1B']
            && $chart['datasets'][0]['data']->all() === [4, 0]);

        $response->assertViewHas('attendanceChart', function (array $chart): bool {
            $byStatus = collect($chart['datasets'])->mapWithKeys(fn (array $dataset): array => [$dataset['label'] => $dataset['data']]);

            return count($chart['labels']) === DashboardController::ATTENDANCE_CHART_WEEKS
                && $chart['labels'][0] === '27/07'
                && $chart['labels'][7] === '14/09'
                && $chart['from'] === '27/07/2026'
                && $chart['to'] === '20/09/2026'
                && $byStatus['Hadir'] === [0, 0, 0, 0, 0, 0, 0, 2]
                && $byStatus['Sakit'] === [0, 0, 0, 0, 0, 0, 0, 1]
                && $byStatus['Alpha'] === [0, 1, 0, 0, 0, 0, 0, 0]
                && $chart['total'] === 4;
        });

        $response->assertSee('data-chart-source="class-chart-data"', false)
            ->assertSee('data-chart-source="attendance-chart-data"', false);
    }

    public function test_dashboard_without_attendance_shows_an_empty_state_instead_of_a_chart(): void
    {
        SchoolClass::factory()->create();

        $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertViewHas('attendanceChart', null)
            ->assertSee('Belum ada data absensi.')
            ->assertDontSee('data-chart-source="attendance-chart-data"', false);
    }

    public function test_pages_carry_the_plugin_hooks_they_need(): void
    {
        AcademicYear::factory()->active()->create();

        $this->actingAs($this->admin)->get(route('admin.siswa.index'))
            ->assertSee('data-export-title="Data Siswa"', false)
            ->assertSee('id="student-modal-class_id" name="class_id" required class="select" data-searchable', false);

        $this->actingAs($this->admin)->get(route('admin.absensi.index'))
            ->assertSee('data-range-start="records"', false)
            ->assertSee('data-range-end="records"', false)
            ->assertSee('data-export-title="Koreksi Absensi"', false);

        $this->actingAs($this->admin)->get(route('admin.laporan.index'))
            ->assertSee('data-export-table', false)
            ->assertSee('data-datepicker data-range-start="recap"', false);

        // Pages without these elements do not pull the plugin chunks in.
        $this->actingAs($this->admin)->get(route('admin.kelas.index'))
            ->assertDontSee('data-export-title', false)
            ->assertDontSee('data-searchable', false)
            ->assertDontSee('data-datepicker', false);
    }

    public function test_auto_submit_selects_no_longer_use_inline_onchange(): void
    {
        AcademicYear::factory()->create(['year_name' => '2025/2026']);
        AcademicYear::factory()->active()->create(['year_name' => '2026/2027']);

        $this->actingAs($this->admin)
            ->get(route('admin.kenaikan.index'))
            ->assertSee('data-auto-submit', false)
            ->assertDontSee('onchange=', false);
    }
}
