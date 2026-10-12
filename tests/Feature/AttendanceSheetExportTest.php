<?php

namespace Tests\Feature;

use App\Gender;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

/**
 * Excel export of the attendance recap in the school's "Daftar Hadir Siswa" layout.
 */
class AttendanceSheetExportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $guru;

    private AcademicYear $year;

    private SchoolClass $class;

    private Subject $math;

    private Subject $science;

    private Student $ani;

    private Student $budi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->admin = User::factory()->admin()->create();
        $this->guru = User::factory()->guru()->create();
        $this->year = AcademicYear::factory()->active()->create(['year_name' => '2026/2027']);
        $this->class = SchoolClass::factory()->create(['class_name' => 'XII']);
        $this->math = Subject::factory()->create(['subject_name' => 'Matematika', 'user_id' => $this->guru->id, 'class_id' => $this->class->class_id, 'academic_year_id' => $this->year->academic_year_id]);
        $this->science = Subject::factory()->create(['subject_name' => 'Fisika', 'class_id' => $this->class->class_id, 'academic_year_id' => $this->year->academic_year_id]);
        $this->ani = Student::factory()->for($this->class)->create(['name' => 'Ani', 'nis' => '20241001', 'gender' => Gender::Female]);
        $this->budi = Student::factory()->for($this->class)->create(['name' => 'Budi', 'nis' => '20241002', 'gender' => Gender::Male]);

        // July 2026. Day 20: Ani present in both subjects, Budi sick in both.
        // Day 21: Ani present in one and absent in the other (a tie), Budi present in maths only.
        $this->mark($this->math, $this->ani, '2026-07-20', 'hadir');
        $this->mark($this->science, $this->ani, '2026-07-20', 'hadir');
        $this->mark($this->math, $this->budi, '2026-07-20', 'sakit');
        $this->mark($this->science, $this->budi, '2026-07-20', 'sakit');
        $this->mark($this->math, $this->ani, '2026-07-21', 'hadir');
        $this->mark($this->science, $this->ani, '2026-07-21', 'alpha');
        $this->mark($this->math, $this->budi, '2026-07-21', 'hadir');
        // August: only Ani has a record, Budi must still be on the sheet.
        $this->mark($this->math, $this->ani, '2026-08-03', 'izin');
    }

    private function mark(Subject $subject, Student $student, string $date, string $status): void
    {
        Attendance::factory()->create(['subject_id' => $subject->subject_id, 'student_id' => $student->student_id, 'date' => $date, 'status' => $status]);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function workbook(TestResponse $response): Spreadsheet
    {
        $response->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $path = tempnam(sys_get_temp_dir(), 'sheet').'.xlsx';
        file_put_contents($path, $response->streamedContent());

        return IOFactory::load($path);
    }

    public function test_admin_gets_one_sheet_per_class_and_month_in_the_schools_layout(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.laporan.daftar-hadir', ['semester' => 1, 'class_id' => $this->class->class_id]));
        $response->assertDownload('Daftar Hadir XII 2026-07-01 sd 2026-12-31.xlsx');
        $workbook = $this->workbook($response);

        $this->assertSame(['XII Jul 2026', 'XII Agt 2026'], $workbook->getSheetNames());

        $july = $workbook->getSheetByName('XII Jul 2026');
        $this->assertSame('DAFTAR HADIR SISWA', $july->getCell('A1')->getValue());
        $this->assertSame('SMA BINA INDONESIA GEMILANG BOARDING SCHOOL', $july->getCell('A2')->getValue());
        $this->assertSame('TAHUN PELAJARAN 2026/2027', $july->getCell('A3')->getValue());
        $this->assertSame('SEMESTER 1 (GANJIL)', $july->getCell('A4')->getValue());
        $this->assertSame('KELAS  : XII', $july->getCell('A6')->getValue());
        $this->assertStringContainsString('BULAN  : Juli 2026', implode(' ', array_filter($july->rangeToArray('E6:AM6')[0])));

        // Heading: NOMOR | NAMA SISWA | L/P | TANGGAL 1..31 | REKAP H S I A.
        $this->assertSame(['NOMOR', null, 'NAMA SISWA', 'L/P', 'TANGGAL'], $july->rangeToArray('A7:E7')[0]);
        $this->assertSame(range(1, 31), array_map('intval', $july->rangeToArray('E8:AI8')[0]));
        $this->assertSame('REKAP', $july->getCell('AJ7')->getValue());
        $this->assertSame(['H', 'S', 'I', 'A'], $july->rangeToArray('AJ8:AM8')[0]);
        $this->assertNotEmpty($july->getDrawingCollection(), 'The sheet carries the school logo.');
    }

    public function test_each_student_gets_one_mark_per_day_and_the_month_totals(): void
    {
        $july = $this->workbook($this->actingAs($this->admin)->get(route('admin.laporan.daftar-hadir', ['semester' => 1])))->getSheetByName('XII Jul 2026');

        // Row 9: Ani. Day 20 (column X) present; day 21 (column Y) one present and one absent -> the tie goes to A.
        $this->assertEquals([1, '20241001', 'Ani', 'P'], $july->rangeToArray('A9:D9')[0]);
        $this->assertSame(['H', 'A'], $july->rangeToArray('X9:Y9')[0]);
        $this->assertSame([1, 0, 0, 1], array_map('intval', $july->rangeToArray('AJ9:AM9')[0]));

        // Row 10: Budi. Sick on the 20th, present on the 21st.
        $this->assertEquals([2, '20241002', 'Budi', 'L'], $july->rangeToArray('A10:D10')[0]);
        $this->assertSame(['S', 'H'], $july->rangeToArray('X10:Y10')[0]);
        $this->assertSame([1, 1, 0, 0], array_map('intval', $july->rangeToArray('AJ10:AM10')[0]));

        // Days without a record stay empty, to be filled in by hand like on the paper sheet.
        $this->assertNull($july->getCell('E9')->getValue());
    }

    public function test_every_student_of_the_class_is_on_every_months_sheet(): void
    {
        $august = $this->workbook($this->actingAs($this->admin)->get(route('admin.laporan.daftar-hadir', ['semester' => 1])))->getSheetByName('XII Agt 2026');

        $this->assertSame(['Ani', 'Budi'], [$august->getCell('C9')->getValue(), $august->getCell('C10')->getValue()]);
        $this->assertSame('I', $august->getCell('G9')->getValue());
        $this->assertSame([0, 0, 0, 0], array_map('intval', $august->rangeToArray('AJ10:AM10')[0]));
    }

    public function test_a_subject_filter_shows_that_subjects_own_marks(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.laporan.daftar-hadir', [
            'subject_id' => $this->science->subject_id, 'semester' => 'custom', 'date_from' => '2026-07-01', 'date_to' => '2026-07-31',
        ]));
        $workbook = $this->workbook($response);

        $this->assertSame(['XII Jul 2026'], $workbook->getSheetNames());
        $sheet = $workbook->getSheet(0);
        $this->assertSame('KELAS  : XII     MAPEL  : Fisika', $sheet->getCell('A6')->getValue());
        // Fisika only: Ani was absent on the 21st, Budi has no Fisika record that day.
        $this->assertSame('A', $sheet->getCell('Y9')->getValue());
        $this->assertNull($sheet->getCell('Y10')->getValue());
    }

    public function test_guru_only_exports_the_subjects_they_teach(): void
    {
        $sheet = $this->workbook($this->actingAs($this->guru)->get(route('guru.rekap.daftar-hadir', ['semester' => 1])))->getSheetByName('XII Jul 2026');

        // Only Matematika counts for this guru: Ani was present there on the 21st, so no tie and no A.
        $this->assertSame('H', $sheet->getCell('Y9')->getValue());
        $this->assertSame('S', $sheet->getCell('X10')->getValue());

        $otherGuru = User::factory()->guru()->create();
        $this->actingAs($otherGuru)->get(route('guru.rekap.daftar-hadir', ['semester' => 1, 'subject_id' => $this->math->subject_id]))
            ->assertRedirect();
    }

    public function test_a_period_without_attendance_is_reported_instead_of_an_empty_file(): void
    {
        $this->actingAs($this->admin)->get(route('admin.laporan.daftar-hadir', ['semester' => 2]))
            ->assertRedirect(route('admin.laporan.index', ['semester' => 2]))
            ->assertSessionHas('error', 'Belum ada absensi pada periode ini untuk diekspor.');
    }

    public function test_recap_pages_point_their_excel_button_at_the_sheet(): void
    {
        $this->actingAs($this->admin)->get(route('admin.laporan.index', ['semester' => 1, 'class_id' => $this->class->class_id]))
            ->assertSee('data-export-excel-url="'.e(route('admin.laporan.daftar-hadir', ['semester' => 1, 'class_id' => $this->class->class_id])).'"', false);

        $this->actingAs($this->guru)->get(route('guru.rekap.mapel', ['subject_id' => $this->math->subject_id, 'semester' => 1]))
            ->assertSee('data-export-excel-url="'.e(route('guru.rekap.daftar-hadir', [
                'academic_year_id' => $this->year->academic_year_id, 'subject_id' => $this->math->subject_id,
                'semester' => 'custom', 'date_from' => '2026-07-01', 'date_to' => '2026-12-31',
            ])).'"', false);
    }
}
