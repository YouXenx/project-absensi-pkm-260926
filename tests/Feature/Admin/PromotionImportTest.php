<?php

namespace Tests\Feature\Admin;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentPromotion;
use App\Models\User;
use App\PromotionImport;
use App\PromotionResult;
use App\StudentStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Bulk promotion from an Excel file: template, validation preview, and the confirmed run.
 */
class PromotionImportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private AcademicYear $year;

    private SchoolClass $class1;

    private SchoolClass $class2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->admin = User::factory()->admin()->create();
        AcademicYear::factory()->create(['year_name' => '2025/2026']);
        $this->year = AcademicYear::factory()->active()->create(['year_name' => '2026/2027']);
        $this->class1 = SchoolClass::factory()->create(['class_name' => 'Kelas 1A']);
        $this->class2 = SchoolClass::factory()->create(['class_name' => 'Kelas 2A']);
    }

    /**
     * @param  list<list<string>>  $rows
     */
    private function workbook(array $rows, bool $withHeader = true): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray($withHeader ? [PromotionImport::HEADERS, ...$rows] : $rows, null, 'A1');

        $path = tempnam(sys_get_temp_dir(), 'promotion').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile($path, 'kenaikan.xlsx', null, null, true);
    }

    public function test_template_download_lists_the_classes_and_special_statuses(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.kenaikan.template'));

        $response->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->assertDownload('Template Kenaikan Kelas 2026-2027.xlsx');

        $path = tempnam(sys_get_temp_dir(), 'template').'.xlsx';
        file_put_contents($path, $response->streamedContent());
        $spreadsheet = IOFactory::load($path);

        $this->assertSame(PromotionImport::HEADERS, $spreadsheet->getSheet(0)->rangeToArray('A1:D1')[0]);
        $help = collect($spreadsheet->getSheetByName('Petunjuk')->toArray())->flatten()->filter()->implode(' | ');
        $this->assertStringContainsString('Kelas 1A', $help);
        $this->assertStringContainsString(PromotionImport::GRADUATE, $help);
    }

    public function test_preview_reports_valid_rows_and_the_reason_for_every_rejected_row(): void
    {
        $naik = Student::factory()->for($this->class1)->create(['nis' => '1001', 'name' => 'Budi']);
        $tinggal = Student::factory()->for($this->class1)->create(['nis' => '1002', 'name' => 'Sinta']);
        $lulus = Student::factory()->for($this->class2)->create(['nis' => '1003', 'name' => 'Sari']);
        $lulusSudah = Student::factory()->for($this->class2)->create(['nis' => '1004', 'status' => StudentStatus::Graduated]);
        $sudahDiproses = Student::factory()->for($this->class1)->create(['nis' => '1005']);
        Student::factory()->for($this->class1)->create(['nis' => '1006', 'name' => 'Kelas asal salah']);
        Student::factory()->for($this->class1)->create(['nis' => '1007', 'name' => 'Tujuan tidak dikenal']);
        StudentPromotion::factory()->create([
            'student_id' => $sudahDiproses->student_id,
            'academic_year_id' => $this->year->academic_year_id,
            'from_class_id' => $this->class1->class_id,
            'to_class_id' => $this->class2->class_id,
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.kenaikan.impor'), [
            'file' => $this->workbook([
                ['1001', 'Budi', 'Kelas 1A', 'Kelas 2A'],
                ['1002', 'Sinta', 'Kelas 1A', PromotionImport::RETAIN],
                ['1003', 'Sari', 'Kelas 2A', PromotionImport::GRADUATE],
                ['1004', 'Sudah lulus', 'Kelas 2A', 'Kelas 1A'],
                ['1005', 'Sudah diproses', 'Kelas 1A', 'Kelas 2A'],
                ['9999', 'Tidak ada', 'Kelas 1A', 'Kelas 2A'],
                ['1001', 'Budi lagi', 'Kelas 1A', 'Kelas 2A'],
                ['1006', 'Kelas asal salah', 'Kelas 2A', 'Kelas 2A'],
                ['1007', 'Tujuan tidak dikenal', 'Kelas 1A', 'Kelas 9Z'],
            ]),
        ]);

        $response->assertOk()
            ->assertViewHas('validCount', 3)
            ->assertViewHas('invalidCount', 6)
            ->assertViewHas('decisions', fn ($decisions): bool => $decisions->all() === [
                $naik->student_id => (string) $this->class2->class_id,
                $tinggal->student_id => 'stay',
                $lulus->student_id => 'graduate',
            ]);

        $errors = collect($response->viewData('entries'))->pluck('error', 'row');
        $this->assertNull($errors[2]);
        $this->assertSame('Status siswa Lulus, tidak ikut kenaikan kelas.', $errors[5]);
        $this->assertSame('Siswa sudah diproses di tahun ajaran ini.', $errors[6]);
        $this->assertSame('NIS tidak ditemukan di data siswa.', $errors[7]);
        $this->assertSame('NIS ganda di dalam file ini.', $errors[8]);
        $this->assertStringContainsString('Kelas asal tidak cocok', $errors[9]);
        $this->assertStringContainsString('Kelas tujuan tidak dikenal', $errors[10]);

        // Nothing is written by a preview.
        $this->assertSame(1, StudentPromotion::count());
        $this->assertSame($this->class1->class_id, $naik->fresh()->class_id);
    }

    public function test_confirmed_import_moves_graduates_and_retains_students_across_classes(): void
    {
        $naik = Student::factory()->for($this->class1)->create(['nis' => '2001']);
        $tinggal = Student::factory()->for($this->class1)->create(['nis' => '2002']);
        $lulus = Student::factory()->for($this->class2)->create(['nis' => '2003']);

        $decisions = $this->actingAs($this->admin)
            ->post(route('admin.kenaikan.impor'), [
                'file' => $this->workbook([
                    ['2001', 'Naik', 'Kelas 1A', 'kelas 2a'],
                    ['2002', 'Tinggal', '', PromotionImport::RETAIN],
                    ['2003', 'Lulus', 'Kelas 2A', PromotionImport::GRADUATE],
                ]),
            ])
            ->viewData('decisions');

        $this->actingAs($this->admin)
            ->post(route('admin.kenaikan.impor.store'), ['decisions' => $decisions->all()])
            ->assertRedirect(route('admin.kenaikan.index'))
            ->assertSessionHas('alert.text', fn (string $text): bool => str_contains($text, '3 siswa diproses dari file Excel')
                && str_contains($text, '1 Naik kelas')
                && str_contains($text, '1 Lulus'));

        $this->assertSame($this->class2->class_id, $naik->fresh()->class_id);
        $this->assertSame($this->class1->class_id, $tinggal->fresh()->class_id);
        $this->assertSame(StudentStatus::Graduated, $lulus->fresh()->status);
        $this->assertSame(3, $this->year->promotions()->count());
        $this->assertDatabaseHas('student_promotions', [
            'student_id' => $naik->student_id,
            'from_class_id' => $this->class1->class_id,
            'to_class_id' => $this->class2->class_id,
            'result' => PromotionResult::Promoted->value,
            'processed_by' => $this->admin->id,
        ]);
    }

    public function test_a_stale_preview_is_rejected_instead_of_processing_a_student_twice(): void
    {
        $student = Student::factory()->for($this->class1)->create(['nis' => '3001']);

        $decisions = $this->actingAs($this->admin)
            ->post(route('admin.kenaikan.impor'), ['file' => $this->workbook([['3001', 'Budi', 'Kelas 1A', 'Kelas 2A']])])
            ->viewData('decisions');

        // Someone processes the same student through the normal form first.
        $this->actingAs($this->admin)->post(route('admin.kenaikan.store'), [
            'from_class_id' => $this->class1->class_id,
            'decisions' => [$student->student_id => 'stay'],
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->admin)
            ->post(route('admin.kenaikan.impor.store'), ['decisions' => $decisions->all()])
            ->assertSessionHas('error', fn (string $message): bool => str_contains($message, 'sudah diproses'));

        $this->assertSame(1, $this->year->promotions()->count());
        $this->assertSame($this->class1->class_id, $student->fresh()->class_id);
    }

    public function test_upload_is_validated_and_an_empty_file_is_refused(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.kenaikan.impor'), ['file' => UploadedFile::fake()->create('data.txt', 10)])
            ->assertSessionHasErrors(['file' => 'File harus berformat .xlsx, .xls, atau .csv.']);

        $this->actingAs($this->admin)
            ->post(route('admin.kenaikan.impor'), ['file' => $this->workbook([])])
            ->assertSessionHas('error', fn (string $message): bool => str_contains($message, 'tidak berisi baris data'));
    }

    public function test_guru_cannot_import_promotions(): void
    {
        $guru = User::factory()->guru()->create();

        $this->actingAs($guru)->get(route('admin.kenaikan.template'))->assertRedirect(route('guru.dashboard'));
        $this->actingAs($guru)->post(route('admin.kenaikan.impor'), ['file' => $this->workbook([['1', 'x', 'Kelas 1A', 'Kelas 2A']])])->assertRedirect(route('guru.dashboard'));
        $this->actingAs($guru)->post(route('admin.kenaikan.impor.store'), ['decisions' => [1 => 'stay']])->assertRedirect(route('guru.dashboard'));

        $this->assertSame(0, StudentPromotion::count());
    }
}
