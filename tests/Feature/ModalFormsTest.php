<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\HomeroomTeacher;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Testing\Fluent\AssertableJson;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The modal protocol used by every CRUD module (components/modal-form + resources/js/modal.js):
 * create/edit endpoints return JSON payloads, writes answer Ajax with JSON, and the old separate pages are gone.
 */
class ModalFormsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Date::setTestNow('2026-09-14 09:00:00');
        $this->admin = User::factory()->admin()->create();
    }

    protected function tearDown(): void
    {
        Date::setTestNow();

        parent::tearDown();
    }

    /**
     * Index route, modal id, whether the page itself has a "Tambah" button (Koreksi Absensi only edits from rows).
     *
     * @return array<string, array{0: string, 1: string, 2: bool}>
     */
    public static function indexPagesWithModals(): array
    {
        return [
            'guru' => ['admin.guru.index', 'teacher-modal', true],
            'kelas' => ['admin.kelas.index', 'class-modal', true],
            'siswa' => ['admin.siswa.index', 'student-modal', true],
            'mapel' => ['admin.mapel.index', 'subject-modal', true],
            'wali kelas' => ['admin.wali-kelas.index', 'homeroom-modal', true],
            'tahun ajaran' => ['admin.tahun-ajaran.index', 'year-modal', true],
            'koreksi absensi' => ['admin.absensi.index', 'attendance-modal', false],
        ];
    }

    #[DataProvider('indexPagesWithModals')]
    public function test_each_index_page_renders_its_modal_and_no_links_to_separate_form_pages(string $routeName, string $modalId, bool $hasCreateButton): void
    {
        AcademicYear::factory()->active()->create();

        $response = $this->actingAs($this->admin)
            ->get(route($routeName))
            ->assertOk()
            ->assertSee('id="'.$modalId.'" data-modal-form', false);

        if ($hasCreateButton) {
            $response->assertSee('data-modal-open="'.$modalId.'"', false);
        }

        $this->assertDoesNotMatchRegularExpression('#href="[^"]*/(create|edit|pindah)(\?[^"]*)?"#', $response->getContent());
    }

    public function test_old_create_page_urls_open_the_modal_on_the_index_page(): void
    {
        $this->actingAs($this->admin)->get('/admin/guru/create')->assertRedirect('/admin/guru#tambah');
        $this->actingAs($this->admin)->get('/admin/kelas/create')->assertRedirect('/admin/kelas#tambah');
        $this->actingAs($this->admin)->get('/admin/siswa/create')->assertRedirect('/admin/siswa#tambah');
    }

    public function test_opening_an_edit_or_payload_url_in_the_browser_goes_back_to_the_index_page(): void
    {
        AcademicYear::factory()->active()->create();
        $student = Student::factory()->create();
        $teacher = User::factory()->guru()->create();
        $subject = Subject::factory()->create(['academic_year_id' => AcademicYear::current()->academic_year_id]);
        $attendance = Attendance::factory()->create(['date' => '2026-09-01']);

        $redirects = [
            route('admin.kelas.edit', $student->class_id) => route('admin.kelas.index'),
            route('admin.siswa.edit', $student) => route('admin.siswa.index'),
            route('admin.siswa.pindah', $student) => route('admin.siswa.index'),
            route('admin.guru.edit', $teacher) => route('admin.guru.index'),
            route('admin.mapel.edit', $subject) => route('admin.mapel.index'),
            route('admin.tahun-ajaran.create') => route('admin.tahun-ajaran.index'),
            route('admin.absensi.edit', $attendance) => route('admin.absensi.index', ['subject_id' => $attendance->subject_id, 'date_from' => '2026-09-01', 'date_to' => '2026-09-01']),
        ];

        foreach ($redirects as $url => $index) {
            $this->actingAs($this->admin)->get($url)->assertRedirect($index);
        }
    }

    public function test_class_create_edit_delete_round_trip_as_json(): void
    {
        $this->actingAs($this->admin)
            ->postJson(route('admin.kelas.store'), ['class_name' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['class_name' => 'Nama kelas wajib diisi.']);

        $this->actingAs($this->admin)
            ->postJson(route('admin.kelas.store'), ['class_name' => 'Kelas 9Z'])
            ->assertOk()
            ->assertExactJson(['message' => 'Kelas 9Z berhasil ditambahkan.']);

        $schoolClass = SchoolClass::firstWhere('class_name', 'Kelas 9Z');

        $this->actingAs($this->admin)
            ->putJson(route('admin.kelas.update', $schoolClass), ['class_name' => 'Kelas 9Y'])
            ->assertOk()
            ->assertJsonPath('message', 'Kelas 9Y berhasil diperbarui.');

        $this->actingAs($this->admin)
            ->getJson(route('admin.kelas.data', ['draw' => 1, 'search' => ['value' => '9Y']]))
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJson(fn (AssertableJson $json) => $json->where('data.0.actions', fn (string $html): bool => str_contains($html, 'data-modal-open="class-modal"') && str_contains($html, 'data-ajax'))->etc());

        $this->actingAs($this->admin)
            ->deleteJson(route('admin.kelas.destroy', $schoolClass))
            ->assertOk()
            ->assertJsonPath('message', 'Kelas 9Y berhasil dihapus.');

        $this->assertModelMissing($schoolClass);
    }

    public function test_business_rule_refusals_are_json_messages_not_redirects(): void
    {
        $student = Student::factory()->create();

        $this->actingAs($this->admin)
            ->deleteJson(route('admin.kelas.destroy', $student->schoolClass))
            ->assertUnprocessable()
            ->assertJsonPath('message', "{$student->schoolClass->class_name} tidak bisa dihapus karena masih memiliki 1 siswa.")
            ->assertJsonMissingPath('errors');

        $this->assertModelExists($student->schoolClass);
    }

    public function test_student_modals_create_edit_and_transfer_as_json(): void
    {
        $classA = SchoolClass::factory()->create(['class_name' => 'Kelas 3A']);
        $classB = SchoolClass::factory()->create(['class_name' => 'Kelas 3B']);

        $this->actingAs($this->admin)
            ->postJson(route('admin.siswa.store'), ['nis' => '2026009001', 'name' => 'Sinta', 'class_id' => $classA->class_id, 'gender' => 'P'])
            ->assertOk()
            ->assertJsonPath('message', 'Siswa Sinta berhasil ditambahkan.');

        $student = Student::firstWhere('nis', '2026009001');

        $this->actingAs($this->admin)
            ->putJson(route('admin.siswa.update', $student), ['nis' => '2026009001', 'name' => 'Sinta Dewi', 'gender' => 'P', 'status' => 'aktif'])
            ->assertOk();
        $this->assertSame('Sinta Dewi', $student->fresh()->name);

        $this->actingAs($this->admin)
            ->patchJson(route('admin.siswa.pindah.update', $student), ['class_id' => $classA->class_id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['class_id' => 'Kelas tujuan sama dengan kelas saat ini.']);

        $this->actingAs($this->admin)
            ->patchJson(route('admin.siswa.pindah.update', $student), ['class_id' => $classB->class_id])
            ->assertOk()
            ->assertJsonPath('alert.title', 'Siswa dipindahkan')
            ->assertJsonPath('alert.text', 'Sinta Dewi pindah dari Kelas 3A ke Kelas 3B.');
    }

    public function test_teacher_row_actions_answer_ajax_with_json(): void
    {
        $teacher = User::factory()->guru()->create(['name' => 'Bu Ani']);

        $this->actingAs($this->admin)
            ->patchJson(route('admin.guru.status', $teacher), ['is_active' => '0'])
            ->assertOk()
            ->assertJsonPath('message', 'Akun guru Bu Ani dinonaktifkan.');

        $this->actingAs($this->admin)
            ->patchJson(route('admin.guru.reset-password', $teacher))
            ->assertOk()
            ->assertJsonPath('alert.title', 'Password direset')
            ->assertJson(fn (AssertableJson $json) => $json->where('alert.html', fn (string $html): bool => str_contains($html, 'data-testid="temporary-password"'))->etc());
    }

    public function test_homeroom_create_payload_disables_classes_that_already_have_a_homeroom_teacher(): void
    {
        $year = AcademicYear::factory()->active()->create(['year_name' => '2026/2027']);
        $taken = HomeroomTeacher::factory()->create(['academic_year_id' => $year->academic_year_id]);
        $free = SchoolClass::factory()->create();

        $html = $this->actingAs($this->admin)
            ->getJson(route('admin.wali-kelas.create', ['class_id' => $free->class_id]))
            ->assertOk()
            ->assertJsonPath('values.class_id', $free->class_id)
            ->json('slots.class_options');

        $this->assertMatchesRegularExpression('/value="'.$taken->class_id.'"\s+disabled/', $html);
        $this->assertDoesNotMatchRegularExpression('/value="'.$free->class_id.'"\s+disabled/', $html);

        $this->actingAs($this->admin)
            ->getJson(route('admin.wali-kelas.data', ['draw' => 1]))
            ->assertJsonPath('recordsTotal', 2)
            ->assertJsonFragment(['teacher_name' => e($taken->teacher->name)]);
    }

    public function test_subject_modal_refuses_while_promotion_is_unfinished(): void
    {
        Date::setTestNow('2026-07-01 08:00:00');
        AcademicYear::factory()->create(['year_name' => '2025/2026']);
        Student::factory()->create();
        Date::setTestNow('2026-07-13 08:00:00');
        AcademicYear::factory()->active()->create(['year_name' => '2026/2027']);

        $this->actingAs($this->admin)
            ->getJson(route('admin.mapel.create'))
            ->assertConflict()
            ->assertJson(fn (AssertableJson $json) => $json->where('message', fn (string $message): bool => str_contains($message, 'Selesaikan proses kenaikan kelas'))->etc());
    }

    public function test_attendance_correction_refreshes_the_summary_cards_with_the_table(): void
    {
        $subject = Subject::factory()->create(['academic_year_id' => AcademicYear::factory()->active()->create()->academic_year_id]);
        $record = Attendance::factory()->create(['subject_id' => $subject->subject_id, 'date' => '2026-09-01', 'status' => 'alpha']);
        $filters = ['draw' => 1, 'date_from' => '2026-09-01', 'date_to' => '2026-09-01'];

        $this->actingAs($this->admin)
            ->putJson(route('admin.absensi.update', $record), ['status' => 'sakit'])
            ->assertOk()
            ->assertJsonPath('message', "Absensi {$record->student->name} (01/09/2026) diperbarui menjadi Sakit.");

        $summary = $this->actingAs($this->admin)
            ->getJson(route('admin.absensi.data', $filters))
            ->json('fragments')['[data-fragment="attendance-summary"]'];

        $this->assertMatchesRegularExpression('/data-status="sakit">.*?kpi-value">1</s', $summary);
        $this->assertMatchesRegularExpression('/data-status="alpha">.*?kpi-value">0</s', $summary);

        $this->actingAs($this->admin)->deleteJson(route('admin.absensi.destroy', $record))->assertOk();
        $this->assertModelMissing($record);
    }

    public function test_guru_gets_json_403_from_admin_modal_endpoints(): void
    {
        $guru = User::factory()->guru()->create();
        $schoolClass = SchoolClass::factory()->create();

        $this->actingAs($guru)->getJson(route('admin.kelas.edit', $schoolClass))->assertForbidden()->assertJsonPath('message', 'Anda tidak punya akses');
        $this->actingAs($guru)->postJson(route('admin.kelas.store'), ['class_name' => 'Diretas'])->assertForbidden();
        $this->actingAs($guru)->deleteJson(route('admin.kelas.destroy', $schoolClass))->assertForbidden();

        $this->assertModelExists($schoolClass);
        $this->assertDatabaseMissing('classes', ['class_name' => 'Diretas']);
    }
}
