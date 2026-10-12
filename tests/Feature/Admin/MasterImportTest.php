<?php

namespace Tests\Feature\Admin;

use App\Gender;
use App\Imports\MasterImport;
use App\Models\AcademicYear;
use App\Models\HomeroomTeacher;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Excel import of the master data modules: template -> upload -> preview -> confirm.
 */
class MasterImportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->admin = User::factory()->admin()->create();
    }

    /**
     * @param  list<list<string|int>>  $rows
     */
    private function workbook(string $type, array $rows, bool $withHeader = true): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray($withHeader ? [MasterImport::for($type)->headers(), ...$rows] : $rows, null, 'A1');

        $path = tempnam(sys_get_temp_dir(), 'import').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile($path, "{$type}.xlsx", null, null, true);
    }

    /**
     * Upload, check the preview counts, then confirm.
     *
     * @param  list<list<string|int>>  $rows
     */
    private function import(string $type, array $rows, int $valid, int $invalid): TestResponse
    {
        $this->actingAs($this->admin)
            ->post(route('admin.impor.preview', $type), ['file' => $this->workbook($type, $rows)])
            ->assertOk()
            ->assertViewHas('validCount', $valid)
            ->assertViewHas('invalidCount', $invalid);

        return $this->actingAs($this->admin)->post(route('admin.impor.store', $type), ['expected' => $valid]);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function types(): array
    {
        return array_map(fn (string $type): array => [$type], array_combine(array_keys(MasterImport::TYPES), array_keys(MasterImport::TYPES)));
    }

    #[DataProvider('types')]
    public function test_every_module_offers_a_template_with_its_columns_and_instructions(string $type): void
    {
        AcademicYear::factory()->active()->create();
        SchoolClass::factory()->create(['class_name' => 'Kelas 7A']);

        $response = $this->actingAs($this->admin)->get(route('admin.impor.template', $type))->assertOk();

        $path = tempnam(sys_get_temp_dir(), 'template').'.xlsx';
        file_put_contents($path, $response->streamedContent());
        $workbook = IOFactory::load($path);

        $this->assertSame(MasterImport::for($type)->headers(), array_filter($workbook->getSheet(0)->toArray()[0]));
        $this->assertSame('Petunjuk', $workbook->getSheet(1)->getTitle());
        $this->assertSame('Cara mengisi', $workbook->getSheet(1)->getCell('A1')->getValue());
    }

    public function test_index_pages_carry_the_import_button(): void
    {
        AcademicYear::factory()->active()->create();

        foreach (['guru' => 'admin.guru.index', 'kelas' => 'admin.kelas.index', 'siswa' => 'admin.siswa.index', 'wali-kelas' => 'admin.wali-kelas.index', 'mapel' => 'admin.mapel.index'] as $type => $route) {
            $this->actingAs($this->admin)->get(route($route))
                ->assertOk()
                ->assertSee('data-dialog-open="import-'.$type.'"', false)
                ->assertSee('action="'.route('admin.impor.preview', $type).'"', false)
                ->assertSee('href="'.route('admin.impor.template', $type).'"', false)
                // The file field is picked up by FilePond (resources/js/plugins/file-upload.js).
                ->assertSee('id="import-'.$type.'-file" name="file" type="file" accept=".xlsx,.xls,.csv" required data-file-upload', false);
        }
    }

    public function test_classes_are_imported_and_duplicates_are_reported(): void
    {
        SchoolClass::factory()->create(['class_name' => 'Kelas 7A']);

        $this->import('kelas', [['Kelas 7B'], ['kelas 7a'], ['Kelas 7B'], [str_repeat('x', 51)], ['Kelas 7C']], valid: 2, invalid: 3)
            ->assertRedirect(route('admin.kelas.index'))
            ->assertSessionHas('success', '2 kelas berhasil diimpor dari Excel, 3 baris dilewati.');

        $this->assertSame(['Kelas 7A', 'Kelas 7B', 'Kelas 7C'], SchoolClass::orderBy('class_name')->pluck('class_name')->all());
    }

    public function test_teachers_are_imported_as_active_guru_accounts_and_passwords_stay_hidden(): void
    {
        User::factory()->guru()->create(['email' => 'lama@sekolah.sch.id']);

        $rows = [
            ['Siti Aminah', 'Siti.Aminah@Sekolah.sch.id', 'Rahasia#2026'],
            ['Budi', 'lama@sekolah.sch.id', 'rahasia123'],
            ['Tanpa Email', 'bukan-email', 'rahasia123'],
            ['Password Pendek', 'pendek@sekolah.sch.id', '1234567'],
        ];

        $this->actingAs($this->admin)
            ->post(route('admin.impor.preview', 'guru'), ['file' => $this->workbook('guru', $rows)])
            ->assertOk()
            ->assertSee('Email sudah digunakan')
            ->assertSee('Format email tidak valid.')
            ->assertSee('Password minimal 8 karakter.')
            ->assertDontSee('Rahasia#2026');

        $this->actingAs($this->admin)->post(route('admin.impor.store', 'guru'), ['expected' => 1])
            ->assertRedirect(route('admin.guru.index'))
            ->assertSessionHas('success', '1 akun guru berhasil diimpor dari Excel, 3 baris dilewati.');

        $teacher = User::firstWhere('email', 'siti.aminah@sekolah.sch.id');
        $this->assertSame(UserRole::Guru, $teacher->role);
        $this->assertTrue($teacher->is_active);
        $this->assertTrue(Hash::check('Rahasia#2026', $teacher->password));
        $this->assertSame(2, User::teachers()->count());
    }

    public function test_students_are_imported_with_class_and_gender_resolved(): void
    {
        $class = SchoolClass::factory()->create(['class_name' => 'Kelas 7A']);
        Student::factory()->for($class)->create(['nis' => '2026000001']);

        $rows = [
            [2026000002, 'Budi Santoso', 'kelas 7a', 'L'],
            ['2026000003', 'Siti Aminah', 'Kelas 7A', 'Perempuan'],
            ['2026000001', 'NIS Dobel', 'Kelas 7A', 'L'],
            ['2026000003', 'Dobel di File', 'Kelas 7A', 'P'],
            ['ABC', 'NIS Huruf', 'Kelas 7A', 'L'],
            ['2026000009', 'Kelas Salah', 'Kelas 9Z', 'L'],
            ['2026000010', 'Gender Salah', 'Kelas 7A', 'X'],
        ];

        $this->import('siswa', $rows, valid: 2, invalid: 5)
            ->assertRedirect(route('admin.siswa.index'))
            ->assertSessionHas('success', '2 siswa berhasil diimpor dari Excel, 5 baris dilewati.');

        $this->assertSame(3, Student::count());
        $budi = Student::firstWhere('nis', '2026000002');
        $this->assertSame([$class->class_id, Gender::Male, 'Budi Santoso'], [$budi->class_id, $budi->gender, $budi->name]);
        $this->assertSame(Gender::Female, Student::firstWhere('nis', '2026000003')->gender);
    }

    public function test_homeroom_teachers_and_subjects_are_imported_for_the_active_year(): void
    {
        $year = AcademicYear::factory()->active()->create(['year_name' => '2026/2027']);
        $classA = SchoolClass::factory()->create(['class_name' => 'Kelas 7A']);
        SchoolClass::factory()->create(['class_name' => 'Kelas 7B']);
        $teacher = User::factory()->guru()->create(['email' => 'guru@sekolah.sch.id']);
        User::factory()->guru()->inactive()->create(['email' => 'nonaktif@sekolah.sch.id']);

        $this->import('wali-kelas', [
            ['Kelas 7A', 'Guru@Sekolah.sch.id'],
            ['Kelas 7A', 'guru@sekolah.sch.id'],
            ['Kelas 7B', 'nonaktif@sekolah.sch.id'],
            ['Kelas 9Z', 'guru@sekolah.sch.id'],
        ], valid: 1, invalid: 3)->assertRedirect(route('admin.wali-kelas.index'));

        $this->assertDatabaseHas('homeroom_teachers', ['class_id' => $classA->class_id, 'user_id' => $teacher->id, 'academic_year_id' => $year->academic_year_id]);
        $this->assertSame(1, HomeroomTeacher::count());

        // Subjects: 7A has its homeroom teacher now, 7B does not.
        $this->import('mapel', [
            ['Matematika', 'Kelas 7A', 'guru@sekolah.sch.id'],
            ['  Bahasa   Indonesia ', 'Kelas 7A', 'guru@sekolah.sch.id'],
            ['matematika', 'Kelas 7A', 'guru@sekolah.sch.id'],
            ['IPA', 'Kelas 7B', 'guru@sekolah.sch.id'],
            ['IPS', 'Kelas 7A', 'tidak-ada@sekolah.sch.id'],
        ], valid: 2, invalid: 3)
            ->assertRedirect(route('admin.mapel.index'))
            ->assertSessionHas('success', '2 mata pelajaran berhasil diimpor dari Excel, 3 baris dilewati.');

        $this->assertSame(['Bahasa Indonesia', 'Matematika'], Subject::orderBy('subject_name')->pluck('subject_name')->all());
        $this->assertSame([$year->academic_year_id], Subject::distinct()->pluck('academic_year_id')->all());
    }

    public function test_year_bound_imports_need_an_active_academic_year(): void
    {
        foreach (['wali-kelas' => 'admin.wali-kelas.index', 'mapel' => 'admin.mapel.index'] as $type => $route) {
            $this->actingAs($this->admin)
                ->post(route('admin.impor.preview', $type), ['file' => $this->workbook($type, [['Kelas 7A', 'guru@sekolah.sch.id']])])
                ->assertRedirect(route($route))
                ->assertSessionHas('error', 'Buat dan aktifkan tahun ajaran terlebih dahulu sebelum mengimpor.');
        }
    }

    public function test_nothing_is_saved_before_confirmation_and_a_stale_preview_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.impor.preview', 'kelas'), ['file' => $this->workbook('kelas', [['Kelas 7A'], ['Kelas 7B']])])
            ->assertOk();

        $this->assertSame(0, SchoolClass::count(), 'The preview must not write anything.');

        // Someone adds one of the classes before the admin confirms.
        SchoolClass::factory()->create(['class_name' => 'Kelas 7A']);

        $this->actingAs($this->admin)->post(route('admin.impor.store', 'kelas'), ['expected' => 2])
            ->assertRedirect(route('admin.kelas.index'))
            ->assertSessionHas('error', 'Data berubah sejak preview dibuat. Unggah ulang file Excel untuk memuat hasil terbaru.');

        $this->assertSame(1, SchoolClass::count());

        // The preview is used up: confirming again without a new upload does nothing.
        $this->actingAs($this->admin)->post(route('admin.impor.store', 'kelas'), ['expected' => 1])
            ->assertSessionHas('error', 'Preview impor sudah tidak berlaku. Unggah ulang file Excel.');
    }

    public function test_bad_uploads_are_rejected_with_a_clear_message(): void
    {
        $this->actingAs($this->admin)->from(route('admin.kelas.index'))
            ->post(route('admin.impor.preview', 'kelas'), ['file' => UploadedFile::fake()->create('data.txt', 10), '_import' => 'kelas'])
            ->assertRedirect(route('admin.kelas.index'))
            ->assertSessionHasErrors(['file' => 'File harus berformat .xlsx, .xls, atau .csv.']);

        $this->actingAs($this->admin)->from(route('admin.kelas.index'))
            ->post(route('admin.impor.preview', 'kelas'), ['file' => $this->workbook('kelas', [])])
            ->assertRedirect(route('admin.kelas.index'))
            ->assertSessionHas('error', 'File tidak berisi baris data. Gunakan template dan isi minimal satu baris.');
    }

    public function test_import_is_admin_only_and_limited_to_known_modules(): void
    {
        $guru = User::factory()->guru()->create();

        $this->actingAs($guru)->get(route('admin.impor.template', 'siswa'))->assertRedirect(route('guru.dashboard'));
        $this->actingAs($guru)->post(route('admin.impor.preview', 'siswa'), ['file' => $this->workbook('siswa', [['1', 'A', 'B', 'L']])])->assertRedirect(route('guru.dashboard'));
        $this->actingAs($guru)->post(route('admin.impor.store', 'siswa'), ['expected' => 1])->assertRedirect(route('guru.dashboard'));

        $this->actingAs($this->admin)->get('/admin/impor/tahun-ajaran/template')->assertNotFound();
        $this->assertSame(0, Student::count());
    }
}
