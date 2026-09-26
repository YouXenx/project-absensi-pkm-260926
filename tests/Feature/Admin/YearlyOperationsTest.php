<?php

namespace Tests\Feature\Admin;

use App\Http\Middleware\AccessDenied;
use App\Http\Requests\Admin\PromotionRequest;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\HomeroomTeacher;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentPromotion;
use App\Models\Subject;
use App\Models\User;
use App\PromotionResult;
use App\StudentStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Tests\TestCase;

class YearlyOperationsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->admin = User::factory()->admin()->create();
    }

    protected function tearDown(): void
    {
        Date::setTestNow();

        parent::tearDown();
    }

    // ---------------------------------------------------------------- 1. Tahun ajaran

    public function test_first_academic_year_is_created_active(): void
    {
        $this->actingAs($this->admin)->getJson(route('admin.tahun-ajaran.create'))->assertOk()->assertJsonPath('confirm', null)->assertJsonPath('slots.incomplete', '');

        $this->actingAs($this->admin)
            ->post(route('admin.tahun-ajaran.store'), ['year_name' => '2025/2026'])
            ->assertRedirect(route('admin.tahun-ajaran.index'))
            ->assertSessionHas('alert.icon', 'success');

        $this->assertTrue(AcademicYear::current()->year_name === '2025/2026');
    }

    public function test_new_academic_year_deactivates_the_previous_one(): void
    {
        $previous = AcademicYear::factory()->active()->create(['year_name' => '2025/2026']);

        $this->actingAs($this->admin)
            ->postJson(route('admin.tahun-ajaran.store'), ['year_name' => '2026/2027'])
            ->assertOk()
            ->assertJsonPath('alert.title', 'Tahun ajaran aktif')
            ->assertJson(fn ($json) => $json->where('alert.html', fn (string $html): bool => str_contains($html, route('admin.kenaikan.index')))->etc());

        $this->assertFalse($previous->fresh()->is_active);
        $this->assertSame('2026/2027', AcademicYear::current()->year_name);
        $this->assertSame(1, AcademicYear::active()->count());
    }

    public function test_academic_year_name_is_validated(): void
    {
        AcademicYear::factory()->active()->create(['year_name' => '2026/2027']);

        foreach (['2026-2027' => 'year_name', '2027/2029' => 'year_name', '2026/2027' => 'year_name', '2025/2026' => 'year_name'] as $name => $field) {
            $this->actingAs($this->admin)
                ->post(route('admin.tahun-ajaran.store'), ['year_name' => $name, 'acknowledge_incomplete' => '1'])
                ->assertSessionHasErrors($field);
        }
    }

    public function test_unfinished_active_year_needs_acknowledgement_before_a_new_one(): void
    {
        AcademicYear::factory()->create(['year_name' => '2024/2025']);
        $current = AcademicYear::factory()->active()->create(['year_name' => '2025/2026']);
        Student::factory()->create();

        $this->assertFalse($current->setupProgress()['complete']);

        $this->actingAs($this->admin)
            ->getJson(route('admin.tahun-ajaran.create'))
            ->assertJsonPath('values.year_name', '2026/2027')
            ->assertJsonPath('confirm', 'Buat tahun ajaran baru dan nonaktifkan 2025/2026?')
            ->assertJson(fn ($json) => $json->where('slots.incomplete', fn (string $html): bool => str_contains($html, 'Proses tahun ajaran 2025/2026 belum selesai') && str_contains($html, 'name="acknowledge_incomplete"'))->etc());

        $this->actingAs($this->admin)
            ->post(route('admin.tahun-ajaran.store'), ['year_name' => '2026/2027'])
            ->assertSessionHasErrors('acknowledge_incomplete');
        $this->assertTrue($current->fresh()->is_active);

        $this->actingAs($this->admin)
            ->post(route('admin.tahun-ajaran.store'), ['year_name' => '2026/2027', 'acknowledge_incomplete' => '1'])
            ->assertSessionHasNoErrors();
        $this->assertFalse($current->fresh()->is_active);
    }

    public function test_later_steps_require_an_active_academic_year(): void
    {
        foreach (['admin.kenaikan.index', 'admin.wali-kelas.create', 'admin.mapel.create'] as $route) {
            $this->actingAs($this->admin)
                ->get(route($route))
                ->assertRedirect(route('admin.tahun-ajaran.index'))
                ->assertSessionHas('alert.icon', 'warning');

            // The modal gets the reason as JSON instead of opening.
            $this->actingAs($this->admin)
                ->getJson(route($route))
                ->assertConflict()
                ->assertJsonPath('message', 'Belum ada tahun ajaran aktif. Buat tahun ajaran terlebih dahulu.');
        }
    }

    // ---------------------------------------------------------------- 2. Kenaikan kelas

    public function test_promotion_preview_changes_nothing(): void
    {
        [$year, $from, $to] = $this->promotionSetup();
        $student = Student::factory()->for($from)->create();

        $this->actingAs($this->admin)
            ->get(route('admin.kenaikan.index', ['from_class_id' => $from->class_id]))
            ->assertOk()
            ->assertSee($student->name)
            ->assertSee('data-apply-all="decision"', false);

        $this->actingAs($this->admin)
            ->post(route('admin.kenaikan.preview'), ['from_class_id' => $from->class_id, 'decisions' => [$student->student_id => (string) $to->class_id]])
            ->assertOk()
            ->assertSee('Naik ke '.$to->class_name.': 1')
            ->assertSee('data-confirm="Proses kenaikan kelas untuk 1 siswa', false);

        $this->assertSame($from->class_id, $student->fresh()->class_id);
        $this->assertSame(0, StudentPromotion::count());
    }

    public function test_promotion_moves_retains_and_graduates_students_and_logs_history(): void
    {
        [$year, $from, $to] = $this->promotionSetup();
        $promoted = Student::factory()->for($from)->create();
        $retained = Student::factory()->for($from)->create();
        $graduated = Student::factory()->for($from)->create();
        $skipped = Student::factory()->for($from)->create();

        $this->actingAs($this->admin)
            ->post(route('admin.kenaikan.store'), [
                'from_class_id' => $from->class_id,
                'decisions' => [
                    $promoted->student_id => (string) $to->class_id,
                    $retained->student_id => PromotionRequest::STAY,
                    $graduated->student_id => PromotionRequest::GRADUATE,
                    $skipped->student_id => PromotionRequest::SKIP,
                ],
            ])
            ->assertRedirect(route('admin.kenaikan.index', ['from_class_id' => $from->class_id]))
            ->assertSessionHas('alert.text', fn (string $text): bool => str_contains($text, '3 siswa diproses'));

        $this->assertSame($to->class_id, $promoted->fresh()->class_id);
        $this->assertSame($from->class_id, $retained->fresh()->class_id);
        $this->assertSame($from->class_id, $graduated->fresh()->class_id, 'Graduated students are not moved to another class.');
        $this->assertSame(StudentStatus::Graduated, $graduated->fresh()->status);
        $this->assertSame(StudentStatus::Active, $skipped->fresh()->status);

        $this->assertDatabaseHas('student_promotions', ['student_id' => $promoted->student_id, 'academic_year_id' => $year->academic_year_id, 'from_class_id' => $from->class_id, 'to_class_id' => $to->class_id, 'result' => 'naik', 'processed_by' => $this->admin->id]);
        $this->assertDatabaseHas('student_promotions', ['student_id' => $graduated->student_id, 'to_class_id' => null, 'result' => 'lulus']);
        $this->assertDatabaseHas('student_promotions', ['student_id' => $retained->student_id, 'result' => 'tinggal']);
        $this->assertDatabaseMissing('student_promotions', ['student_id' => $skipped->student_id]);
    }

    public function test_a_student_is_promoted_only_once_per_year(): void
    {
        [, $from, $to] = $this->promotionSetup();
        $third = SchoolClass::factory()->create();
        $student = Student::factory()->for($from)->create();

        $this->actingAs($this->admin)->post(route('admin.kenaikan.store'), ['from_class_id' => $from->class_id, 'decisions' => [$student->student_id => (string) $to->class_id]]);

        // Now in the target class, but already processed this year: cannot be pushed further.
        $this->actingAs($this->admin)
            ->get(route('admin.kenaikan.index', ['from_class_id' => $to->class_id]))
            ->assertDontSee('name="decisions['.$student->student_id.']"', false);

        $this->actingAs($this->admin)
            ->post(route('admin.kenaikan.store'), ['from_class_id' => $to->class_id, 'decisions' => [$student->student_id => (string) $third->class_id]])
            ->assertSessionHasErrors('decisions');

        $this->assertSame($to->class_id, $student->fresh()->class_id);
    }

    public function test_promotion_rejects_invalid_decisions(): void
    {
        [, $from] = $this->promotionSetup();
        $student = Student::factory()->for($from)->create();
        $otherClassStudent = Student::factory()->create();

        $cases = [
            [$student->student_id => (string) $from->class_id],
            [$student->student_id => 'banana'],
            [$student->student_id => PromotionRequest::SKIP],
            [$otherClassStudent->student_id => PromotionRequest::GRADUATE],
        ];

        foreach ($cases as $decisions) {
            $this->actingAs($this->admin)
                ->post(route('admin.kenaikan.preview'), ['from_class_id' => $from->class_id, 'decisions' => $decisions])
                ->assertSessionHasErrors('decisions');
        }
    }

    // ---------------------------------------------------------------- 3. Wali kelas

    public function test_homeroom_teacher_is_bound_to_the_active_year_and_unique_per_class(): void
    {
        $year = AcademicYear::factory()->active()->create(['year_name' => '2026/2027']);
        $class = SchoolClass::factory()->create();
        $guru = User::factory()->guru()->create();

        $this->actingAs($this->admin)
            ->getJson(route('admin.wali-kelas.create', ['class_id' => $class->class_id]))
            ->assertOk()
            ->assertJsonPath('values.class_id', $class->class_id);

        $this->actingAs($this->admin)
            ->post(route('admin.wali-kelas.store'), ['class_id' => $class->class_id, 'user_id' => $guru->id, 'academic_year_id' => 999])
            ->assertRedirect(route('admin.wali-kelas.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('homeroom_teachers', ['class_id' => $class->class_id, 'user_id' => $guru->id, 'academic_year_id' => $year->academic_year_id]);

        $this->actingAs($this->admin)
            ->post(route('admin.wali-kelas.store'), ['class_id' => $class->class_id, 'user_id' => User::factory()->guru()->create()->id])
            ->assertSessionHasErrors(['class_id' => 'Kelas ini sudah punya wali kelas di tahun ajaran 2026/2027.']);

        $this->actingAs($this->admin)
            ->post(route('admin.wali-kelas.store'), ['class_id' => SchoolClass::factory()->create()->class_id, 'user_id' => $this->admin->id])
            ->assertSessionHasErrors('user_id');
    }

    public function test_homeroom_of_an_old_year_is_read_only_history(): void
    {
        $oldYear = AcademicYear::factory()->create(['year_name' => '2025/2026']);
        AcademicYear::factory()->active()->create(['year_name' => '2026/2027']);
        $old = HomeroomTeacher::factory()->create(['academic_year_id' => $oldYear->academic_year_id]);

        $row = $this->actingAs($this->admin)
            ->getJson(route('admin.wali-kelas.data', ['draw' => 1, 'academic_year_id' => $oldYear->academic_year_id, 'search' => ['value' => $old->schoolClass->class_name]]))
            ->assertOk()
            ->assertJsonPath('data.0.teacher_name', e($old->teacher->name))
            ->json('data.0');
        $this->assertStringNotContainsString(route('admin.wali-kelas.edit', $old), $row['actions']);
        $this->assertStringContainsString('Histori', $row['actions']);

        $this->actingAs($this->admin)->getJson(route('admin.wali-kelas.edit', $old))->assertUnprocessable()->assertJsonPath('message', 'Data tahun ajaran 2025/2026 adalah histori dan tidak bisa diubah.');
        $this->actingAs($this->admin)->delete(route('admin.wali-kelas.destroy', $old))->assertSessionHas('error');

        $this->assertModelExists($old);
    }

    // ---------------------------------------------------------------- 4. Mata pelajaran

    public function test_one_subject_can_be_assigned_to_many_classes_for_the_active_year(): void
    {
        $year = AcademicYear::factory()->active()->create();
        $guru = User::factory()->guru()->create();
        $classes = SchoolClass::factory()->count(3)->create();
        $classes->each(fn (SchoolClass $class) => HomeroomTeacher::factory()->create(['class_id' => $class->class_id, 'academic_year_id' => $year->academic_year_id]));

        $this->actingAs($this->admin)
            ->getJson(route('admin.mapel.create'))
            ->assertOk()
            ->assertJson(fn ($json) => $json->where('slots.class_checks', fn (string $html): bool => substr_count($html, 'name="class_ids[]"') === 3 && ! str_contains($html, 'disabled'))->etc());

        $this->actingAs($this->admin)
            ->post(route('admin.mapel.store'), [
                'subject_name' => '  Matematika ',
                'user_id' => $guru->id,
                'class_ids' => $classes->pluck('class_id')->all(),
            ])
            ->assertRedirect(route('admin.mapel.index'))
            ->assertSessionHas('success');

        $this->assertSame(3, Subject::where('subject_name', 'Matematika')->where('academic_year_id', $year->academic_year_id)->where('user_id', $guru->id)->count());

        // A guru may teach several subjects in the same class.
        $this->actingAs($this->admin)
            ->post(route('admin.mapel.store'), ['subject_name' => 'IPA', 'user_id' => $guru->id, 'class_ids' => [$classes[0]->class_id]])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->admin)
            ->post(route('admin.mapel.store'), ['subject_name' => 'Matematika', 'user_id' => $guru->id, 'class_ids' => [$classes[1]->class_id]])
            ->assertSessionHasErrors('class_ids');
    }

    public function test_subject_data_endpoint_filters_by_academic_year(): void
    {
        $oldYear = AcademicYear::factory()->create();
        $activeYear = AcademicYear::factory()->active()->create();
        Subject::factory()->create(['academic_year_id' => $oldYear->academic_year_id, 'subject_name' => 'Mapel Lama']);
        Subject::factory()->create(['academic_year_id' => $activeYear->academic_year_id, 'subject_name' => 'Mapel Baru']);

        $this->actingAs($this->admin)
            ->getJson(route('admin.mapel.data', ['draw' => 1]))
            ->assertJsonPath('recordsTotal', 1)
            ->assertJsonPath('data.0.subject_name', 'Mapel Baru');

        $this->actingAs($this->admin)
            ->getJson(route('admin.mapel.data', ['draw' => 2, 'academic_year_id' => $oldYear->academic_year_id]))
            ->assertJsonPath('data.0.subject_name', 'Mapel Lama')
            ->assertJsonPath('data.0.actions', '<span class="badge">Histori</span>');
    }

    public function test_subject_with_attendance_cannot_be_deleted(): void
    {
        AcademicYear::factory()->active()->create();
        $attendance = Attendance::factory()->create(['subject_id' => Subject::factory()->state(['academic_year_id' => AcademicYear::current()->academic_year_id])]);

        $this->actingAs($this->admin)
            ->delete(route('admin.mapel.destroy', $attendance->subject_id))
            ->assertSessionHas('error');

        $this->assertModelExists($attendance->subject);
    }

    // ---------------------------------------------------------------- Access

    public function test_guru_cannot_run_yearly_operations(): void
    {
        AcademicYear::factory()->active()->create();
        $guru = User::factory()->guru()->create();

        foreach (['admin.tahun-ajaran.index', 'admin.tahun-ajaran.create', 'admin.kenaikan.index', 'admin.wali-kelas.index', 'admin.wali-kelas.create', 'admin.mapel.index', 'admin.laporan.index'] as $route) {
            $this->actingAs($guru)->get(route($route))
                ->assertRedirect(route('guru.dashboard'))
                ->assertSessionHas('alert.text', AccessDenied::MESSAGE);
        }

        $this->actingAs($guru)->post(route('admin.tahun-ajaran.store'), ['year_name' => '2030/2031'])->assertRedirect(route('guru.dashboard'));
        $this->assertDatabaseMissing('academic_years', ['year_name' => '2030/2031']);
    }

    // ---------------------------------------------------------------- Full flow

    public function test_full_yearly_flow_keeps_the_previous_year_as_history(): void
    {
        // Previous year with its own homeroom, subject and attendance.
        $oldYear = AcademicYear::factory()->active()->create(['year_name' => '2025/2026']);
        $guruOld = User::factory()->guru()->create(['name' => 'Guru Lama']);
        $guruNew = User::factory()->guru()->create(['name' => 'Guru Baru']);
        $class1 = SchoolClass::factory()->create(['class_name' => 'Kelas 1A']);
        $class2 = SchoolClass::factory()->create(['class_name' => 'Kelas 2A']);
        $class6 = SchoolClass::factory()->create(['class_name' => 'Kelas 6A']);
        $student = Student::factory()->for($class1)->create(['name' => 'Budi Naik']);
        $senior = Student::factory()->for($class6)->create(['name' => 'Sari Lulus']);

        $oldHomeroom = HomeroomTeacher::factory()->create(['class_id' => $class1->class_id, 'user_id' => $guruOld->id, 'academic_year_id' => $oldYear->academic_year_id]);
        $oldSubject = Subject::factory()->create(['subject_name' => 'Matematika', 'class_id' => $class1->class_id, 'user_id' => $guruOld->id, 'academic_year_id' => $oldYear->academic_year_id]);
        Attendance::factory()->create(['subject_id' => $oldSubject->subject_id, 'student_id' => $student->student_id, 'date' => '2026-03-10', 'status' => 'hadir']);

        // 1. New academic year.
        $this->actingAs($this->admin)->post(route('admin.tahun-ajaran.store'), ['year_name' => '2026/2027', 'acknowledge_incomplete' => '1'])->assertSessionHasNoErrors();
        $newYear = AcademicYear::current();

        // 2. Promotion: 1A -> 2A, 6A graduates.
        $this->actingAs($this->admin)->post(route('admin.kenaikan.store'), ['from_class_id' => $class1->class_id, 'decisions' => [$student->student_id => (string) $class2->class_id]])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post(route('admin.kenaikan.store'), ['from_class_id' => $class6->class_id, 'decisions' => [$senior->student_id => PromotionRequest::GRADUATE]])->assertSessionHasNoErrors();

        // 3 + 4. New homeroom and subject for the new year, same class as last year.
        $this->actingAs($this->admin)->post(route('admin.wali-kelas.store'), ['class_id' => $class1->class_id, 'user_id' => $guruNew->id])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post(route('admin.wali-kelas.store'), ['class_id' => $class2->class_id, 'user_id' => $guruNew->id])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post(route('admin.mapel.store'), ['subject_name' => 'Matematika', 'user_id' => $guruNew->id, 'class_ids' => [$class1->class_id, $class2->class_id]])->assertSessionHasNoErrors();

        // New year is fully set up.
        $this->assertSame($class2->class_id, $student->fresh()->class_id);
        $this->assertSame(StudentStatus::Graduated, $senior->fresh()->status);
        $this->assertSame(2, $newYear->promotions()->count());
        $this->assertSame(2, $newYear->homeroomTeachers()->count());
        $this->assertSame(2, $newYear->subjects()->count());

        // Old year untouched.
        $this->assertFalse($oldYear->fresh()->is_active);
        $this->assertModelExists($oldHomeroom);
        $this->assertSame($guruOld->id, $oldHomeroom->fresh()->user_id);
        $this->assertSame($class1->class_id, $oldSubject->fresh()->class_id);
        $this->assertSame(1, $oldSubject->attendances()->count());
        $this->assertTrue(StudentPromotion::where('student_id', $student->student_id)->first()->fromClass->is($class1));
        $this->assertSame(PromotionResult::Graduated, $senior->promotions()->first()->result);

        // ...and still visible as history in every screen.
        $this->actingAs($this->admin)->getJson(route('admin.wali-kelas.data', ['draw' => 1, 'academic_year_id' => $oldYear->academic_year_id]))->assertJsonFragment(['teacher_name' => 'Guru Lama']);
        $this->actingAs($this->admin)->getJson(route('admin.wali-kelas.data', ['draw' => 1]))->assertJsonFragment(['teacher_name' => 'Guru Baru'])->assertJsonMissing(['teacher_name' => 'Guru Lama']);
        $this->actingAs($this->admin)->getJson(route('admin.mapel.data', ['draw' => 1, 'academic_year_id' => $oldYear->academic_year_id]))->assertJsonPath('recordsTotal', 1)->assertJsonPath('data.0.teacher_name', 'Guru Lama');

        // Recap of the old year still reports Budi under Kelas 1A, even though he is now in 2A.
        $this->actingAs($this->admin)
            ->get(route('admin.laporan.index', ['academic_year_id' => $oldYear->academic_year_id, 'semester' => 2]))
            ->assertOk()
            ->assertSeeInOrder(['Budi Naik', 'Kelas 1A']);

        $this->actingAs($this->admin)->get(route('admin.tahun-ajaran.index'))->assertOk()->assertSee('id="year-modal"', false);
        $this->actingAs($this->admin)
            ->getJson(route('admin.tahun-ajaran.data', ['draw' => 1, 'order' => [['column' => 0, 'dir' => 'desc']], 'columns' => [['data' => 'year_name']]]))
            ->assertJsonPath('data.0.year_name', '2026/2027')
            ->assertJsonPath('data.0.status', '<span class="badge success dot">Aktif</span>')
            ->assertJsonPath('data.1.year_name', '2025/2026')
            ->assertJsonPath('data.1.status', '<span class="badge">Histori</span>');
    }

    // ---------------------------------------------------------------- Order of the yearly flow

    public function test_attendance_opens_only_after_every_yearly_step_is_done(): void
    {
        Date::setTestNow('2026-07-01 08:00:00');
        AcademicYear::factory()->active()->create(['year_name' => '2025/2026']);
        $guru = User::factory()->guru()->create();
        $class1 = SchoolClass::factory()->create(['class_name' => 'Kelas 1A']);
        $class2 = SchoolClass::factory()->create(['class_name' => 'Kelas 2A']);
        $student = Student::factory()->for($class1)->create(['name' => 'Budi Naik']);

        // 1. New academic year: the guru has nothing to take attendance for yet.
        Date::setTestNow('2026-07-13 08:00:00');
        $this->actingAs($this->admin)->post(route('admin.tahun-ajaran.store'), ['year_name' => '2026/2027', 'acknowledge_incomplete' => '1'])->assertSessionHasNoErrors();
        $newYear = AcademicYear::current();

        $this->actingAs($guru)->getJson(route('guru.mapel.data', ['draw' => 1]))->assertJsonPath('recordsTotal', 0);
        $this->actingAs($guru)->get(route('guru.absensi.index'))->assertSee('Belum ada mapel di tahun ajaran aktif');

        // Steps 3 and 4 cannot be skipped to before step 2.
        foreach (['admin.wali-kelas.create', 'admin.mapel.create'] as $route) {
            $this->actingAs($this->admin)->get(route($route))
                ->assertRedirect(route('admin.kenaikan.index'))
                ->assertSessionHas('alert.title', 'Kenaikan kelas belum selesai');
        }
        $this->actingAs($this->admin)->post(route('admin.wali-kelas.store'), ['class_id' => $class2->class_id, 'user_id' => $guru->id])->assertRedirect(route('admin.kenaikan.index'));
        $this->actingAs($this->admin)->post(route('admin.mapel.store'), ['subject_name' => 'IPA', 'user_id' => $guru->id, 'class_ids' => [$class2->class_id]])->assertRedirect(route('admin.kenaikan.index'));
        $this->assertSame(0, $newYear->homeroomTeachers()->count());
        $this->assertSame(0, $newYear->subjects()->count());

        // Even a subject that already exists (e.g. inserted directly) stays locked for attendance.
        $lockedSubject = Subject::factory()->create(['subject_name' => 'Matematika', 'user_id' => $guru->id, 'class_id' => $class2->class_id, 'academic_year_id' => $newYear->academic_year_id]);
        $payload = ['date' => '2026-07-13', 'attendance' => [$student->student_id => ['status' => 'hadir']]];

        $row = $this->actingAs($guru)->getJson(route('guru.mapel.data', ['draw' => 2]))->json('data.0');
        $this->assertStringContainsString('Belum dibuka', $row['today']);
        $this->assertSame('', $row['actions']);
        $this->actingAs($guru)->get(route('guru.absensi.index'))->assertDontSee('Matematika · Kelas 2A')->assertSee('data-testid="locked-subjects"', false);
        $this->actingAs($guru)->get(route('guru.absensi.index', ['subject_id' => $lockedSubject->subject_id]))
            ->assertRedirect(route('guru.dashboard'))
            ->assertSessionHas('alert.text', 'Absensi belum dibuka: proses kenaikan kelas tahun ajaran 2026/2027 belum selesai.');
        $this->actingAs($guru)->post(route('guru.absensi.store', $lockedSubject), $payload)->assertRedirect(route('guru.dashboard'));

        // 2. Promotion done: homeroom may be assigned, but subjects still need the class's homeroom teacher.
        $this->actingAs($this->admin)->post(route('admin.kenaikan.store'), ['from_class_id' => $class1->class_id, 'decisions' => [$student->student_id => (string) $class2->class_id]])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->getJson(route('admin.mapel.create'))->assertOk();
        $this->actingAs($this->admin)->post(route('admin.mapel.store'), ['subject_name' => 'IPA', 'user_id' => $guru->id, 'class_ids' => [$class2->class_id]])
            ->assertSessionHasErrors(['class_ids' => 'Tetapkan wali kelas tahun ajaran 2026/2027 terlebih dahulu untuk: Kelas 2A.']);
        $this->actingAs($guru)->post(route('guru.absensi.store', $lockedSubject), $payload)
            ->assertSessionHas('alert.text', 'Absensi belum dibuka: Kelas 2A belum punya wali kelas di tahun ajaran 2026/2027.');

        // 3 + 4. Homeroom teacher, then the subject.
        $this->actingAs($this->admin)->post(route('admin.wali-kelas.store'), ['class_id' => $class2->class_id, 'user_id' => $guru->id])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post(route('admin.mapel.store'), ['subject_name' => 'IPA', 'user_id' => $guru->id, 'class_ids' => [$class2->class_id]])->assertSessionHasNoErrors();
        $this->assertSame(0, Attendance::count());

        // 5. Now the promoted student can be recorded, in his new class.
        $newSubject = Subject::firstWhere('subject_name', 'IPA');
        $this->actingAs($guru)->get(route('guru.absensi.index', ['subject_id' => $newSubject->subject_id]))->assertOk()->assertSee('Budi Naik');
        $this->actingAs($guru)->post(route('guru.absensi.store', $newSubject), $payload)->assertSessionHas('alert.title', 'Absensi tersimpan');
        $this->assertSame(1, $newSubject->attendances()->count());

        // Removing the homeroom teacher would lock the class again, so it is refused while subjects exist.
        $this->actingAs($this->admin)->delete(route('admin.wali-kelas.destroy', $newYear->homeroomTeachers()->first()))->assertSessionHas('error');
        $this->assertSame(1, $newYear->homeroomTeachers()->count());
    }

    public function test_students_enrolled_after_the_year_was_created_do_not_block_the_promotion_step(): void
    {
        Date::setTestNow('2026-07-01 08:00:00');
        AcademicYear::factory()->create(['year_name' => '2025/2026']);
        $class = SchoolClass::factory()->create();
        $existing = Student::factory()->for($class)->create();

        Date::setTestNow('2026-07-13 08:00:00');
        $year = AcademicYear::factory()->active()->create(['year_name' => '2026/2027']);
        $this->assertFalse($year->promotionCompleted());

        $this->actingAs($this->admin)->post(route('admin.kenaikan.store'), ['from_class_id' => $class->class_id, 'decisions' => [$existing->student_id => PromotionRequest::STAY]])->assertSessionHasNoErrors();

        Date::setTestNow('2026-07-20 08:00:00');
        Student::factory()->for($class)->create(['name' => 'Siswa Baru']);

        $this->assertTrue($year->promotionCompleted());
        $this->assertTrue(collect($year->setupProgress()['steps'])->firstWhere('key', 'promotion')['done']);
    }

    public function test_an_empty_academic_year_can_be_deleted_but_history_and_the_active_year_cannot(): void
    {
        $empty = AcademicYear::factory()->create(['year_name' => '2024/2025']);
        $withHistory = AcademicYear::factory()->create(['year_name' => '2025/2026']);
        $active = AcademicYear::factory()->active()->create(['year_name' => '2026/2027']);
        Subject::factory()->create(['academic_year_id' => $withHistory->academic_year_id]);

        $this->actingAs($this->admin)
            ->deleteJson(route('admin.tahun-ajaran.destroy', $empty))
            ->assertOk()
            ->assertJsonPath('message', 'Tahun ajaran 2024/2025 berhasil dihapus.');
        $this->assertModelMissing($empty);

        $this->actingAs($this->admin)
            ->deleteJson(route('admin.tahun-ajaran.destroy', $withHistory))
            ->assertUnprocessable()
            ->assertJsonPath('message', fn (string $message): bool => str_contains($message, '1 mata pelajaran'));
        $this->assertModelExists($withHistory);

        $this->actingAs($this->admin)
            ->deleteJson(route('admin.tahun-ajaran.destroy', $active))
            ->assertUnprocessable()
            ->assertJsonPath('message', fn (string $message): bool => str_contains($message, 'sedang aktif'));
        $this->assertModelExists($active);

        $row = $this->actingAs($this->admin)
            ->getJson(route('admin.tahun-ajaran.data', ['draw' => 1, 'search' => ['value' => '2025/2026']]))
            ->json('data.0');
        $this->assertStringContainsString('Histori', $row['actions']);
        $this->assertStringNotContainsString(route('admin.tahun-ajaran.destroy', $withHistory), $row['actions']);
    }

    /**
     * @return array{0: AcademicYear, 1: SchoolClass, 2: SchoolClass}
     */
    private function promotionSetup(): array
    {
        AcademicYear::factory()->create(['year_name' => '2025/2026']);

        return [
            AcademicYear::factory()->active()->create(['year_name' => '2026/2027']),
            SchoolClass::factory()->create(['class_name' => 'Kelas 1A']),
            SchoolClass::factory()->create(['class_name' => 'Kelas 2A']),
        ];
    }
}
