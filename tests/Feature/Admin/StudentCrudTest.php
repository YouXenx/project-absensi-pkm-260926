<?php

namespace Tests\Feature\Admin;

use App\Gender;
use App\Http\Middleware\AccessDenied;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\StudentStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->admin = User::factory()->admin()->create();
    }

    public function test_relationships_between_classes_and_students(): void
    {
        $schoolClass = SchoolClass::factory()->has(Student::factory()->count(3))->create();
        $student = $schoolClass->students->first();

        $this->assertCount(3, $schoolClass->students);
        $this->assertTrue($student->schoolClass->is($schoolClass));
        $this->assertInstanceOf(Gender::class, $student->gender);
    }

    public function test_index_renders_the_server_side_table_with_class_filter(): void
    {
        SchoolClass::factory()->create(['class_name' => 'Kelas 4B']);

        $this->actingAs($this->admin)
            ->get(route('admin.siswa.index'))
            ->assertOk()
            ->assertSee('data-url="'.route('admin.siswa.data').'"', false)
            ->assertSee('data-datatable-filter="class_id"', false)
            ->assertSee('Kelas 4B');
    }

    public function test_data_endpoint_searches_sorts_and_filters_by_class(): void
    {
        $classA = SchoolClass::factory()->create(['class_name' => 'Kelas 1A']);
        $classB = SchoolClass::factory()->create(['class_name' => 'Kelas 1B']);

        Student::factory()->for($classA)->create(['name' => 'Andi Wijaya', 'nis' => '1001', 'gender' => Gender::Male]);
        Student::factory()->for($classA)->create(['name' => 'Budi Wijaya', 'nis' => '1002', 'gender' => Gender::Male]);
        Student::factory()->for($classB)->create(['name' => 'Citra Wijaya', 'nis' => '1003', 'gender' => Gender::Female]);
        Student::factory()->for($classB)->create(['name' => 'Dewi Lestari', 'nis' => '1004', 'gender' => Gender::Female]);

        $columns = [['data' => 'nis'], ['data' => 'name'], ['data' => 'class_name'], ['data' => 'gender'], ['data' => 'actions']];

        $this->actingAs($this->admin)
            ->getJson(route('admin.siswa.data', [
                'draw' => 2, 'start' => 0, 'length' => 10,
                'search' => ['value' => 'wijaya'],
                'columns' => $columns,
                'order' => [['column' => 1, 'dir' => 'desc']],
            ]))
            ->assertOk()
            ->assertJsonPath('recordsTotal', 4)
            ->assertJsonPath('recordsFiltered', 3)
            ->assertJsonPath('data.0.name', 'Citra Wijaya')
            ->assertJsonPath('data.0.class_name', 'Kelas 1B')
            ->assertJsonPath('data.0.gender', 'Perempuan')
            ->assertJsonPath('data.2.name', 'Andi Wijaya');

        $this->actingAs($this->admin)
            ->getJson(route('admin.siswa.data', ['draw' => 3, 'class_id' => $classB->class_id, 'columns' => $columns]))
            ->assertJsonPath('recordsTotal', 2)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Citra Wijaya');

        $this->actingAs($this->admin)
            ->getJson(route('admin.siswa.data', ['draw' => 4, 'search' => ['value' => 'Kelas 1A'], 'columns' => $columns]))
            ->assertJsonPath('recordsFiltered', 2);
    }

    public function test_search_treats_like_wildcards_literally(): void
    {
        Student::factory()->count(3)->create();

        $this->actingAs($this->admin)
            ->getJson(route('admin.siswa.data', ['draw' => 1, 'search' => ['value' => '%']]))
            ->assertJsonPath('recordsFiltered', 0);
    }

    public function test_admin_can_create_a_student(): void
    {
        $schoolClass = SchoolClass::factory()->create();

        $this->actingAs($this->admin)->get(route('admin.siswa.index'))->assertOk()->assertSee('id="student-modal"', false)->assertSee($schoolClass->class_name);

        $this->actingAs($this->admin)
            ->post(route('admin.siswa.store'), [
                'nis' => '2026000001',
                'name' => 'Rina Marlina',
                'class_id' => $schoolClass->class_id,
                'gender' => 'P',
            ])
            ->assertRedirect(route('admin.siswa.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('students', [
            'nis' => '2026000001',
            'name' => 'Rina Marlina',
            'class_id' => $schoolClass->class_id,
            'gender' => 'P',
        ]);
    }

    public function test_student_validation(): void
    {
        Student::factory()->create(['nis' => '2026000001']);

        $this->actingAs($this->admin)
            ->postJson(route('admin.siswa.store'), [
                'nis' => '2026000001',
                'name' => '',
                'class_id' => 999,
                'gender' => 'X',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nis', 'name', 'class_id', 'gender']);

        $this->actingAs($this->admin)
            ->post(route('admin.siswa.store'), ['nis' => '12ab'])
            ->assertSessionHasErrors(['nis' => 'NIS hanya boleh berisi angka.']);
    }

    public function test_admin_can_update_a_student(): void
    {
        $student = Student::factory()->create(['nis' => '2026000005']);
        $newClass = SchoolClass::factory()->create();

        $this->actingAs($this->admin)
            ->getJson(route('admin.siswa.edit', $student))
            ->assertOk()
            ->assertJsonPath('values.nis', '2026000005')
            ->assertJsonPath('values.current_class', $student->schoolClass->class_name)
            ->assertJsonMissingPath('values.class_id');

        $this->actingAs($this->admin)
            ->put(route('admin.siswa.update', $student), [
                'nis' => '2026000005',
                'name' => 'Nama Baru',
                'class_id' => $newClass->class_id,
                'gender' => 'L',
            ])
            ->assertRedirect(route('admin.siswa.index'))
            ->assertSessionHasNoErrors();

        $student->refresh();
        $this->assertSame('Nama Baru', $student->name);
        $this->assertFalse($student->schoolClass->is($newClass), 'The edit form must not move a student; use the transfer action.');
    }

    public function test_admin_can_transfer_a_student_to_another_class(): void
    {
        $student = Student::factory()->create();
        $oldClass = $student->schoolClass;
        $newClass = SchoolClass::factory()->create(['class_name' => 'Kelas Tujuan']);

        $this->actingAs($this->admin)
            ->getJson(route('admin.siswa.pindah', $student))
            ->assertOk()
            ->assertJsonPath('action', route('admin.siswa.pindah.update', $student))
            ->assertJsonPath('confirm', "Pindahkan {$student->name} dari {$oldClass->class_name} ke kelas yang dipilih?")
            ->assertJson(fn ($json) => $json->where('slots.target_classes', fn (string $html): bool => str_contains($html, 'Kelas Tujuan') && ! str_contains($html, e($oldClass->class_name).' ('))->etc());

        $this->actingAs($this->admin)
            ->patch(route('admin.siswa.pindah.update', $student), ['class_id' => $oldClass->class_id])
            ->assertSessionHasErrors(['class_id' => 'Kelas tujuan sama dengan kelas saat ini.']);

        $this->actingAs($this->admin)
            ->patch(route('admin.siswa.pindah.update', $student), ['class_id' => $newClass->class_id])
            ->assertRedirect(route('admin.siswa.index'))
            ->assertSessionHas('alert.text', "{$student->name} pindah dari {$oldClass->class_name} ke Kelas Tujuan.");

        $this->assertTrue($student->fresh()->schoolClass->is($newClass));
    }

    public function test_graduated_students_cannot_be_transferred(): void
    {
        $student = Student::factory()->graduated()->create();
        $classId = $student->class_id;

        $this->actingAs($this->admin)->getJson(route('admin.siswa.pindah', $student))->assertUnprocessable()->assertJsonPath('message', "{$student->name} berstatus Lulus dan tidak bisa dipindahkan ke kelas lain.");
        $this->actingAs($this->admin)
            ->patch(route('admin.siswa.pindah.update', $student), ['class_id' => SchoolClass::factory()->create()->class_id])
            ->assertSessionHas('error');

        $this->assertSame($classId, $student->fresh()->class_id);
    }

    public function test_guru_cannot_transfer_students(): void
    {
        $student = Student::factory()->create();
        $classId = $student->class_id;

        $this->actingAs(User::factory()->guru()->create())
            ->patch(route('admin.siswa.pindah.update', $student), ['class_id' => SchoolClass::factory()->create()->class_id])
            ->assertRedirect(route('guru.dashboard'));

        $this->assertSame($classId, $student->fresh()->class_id);
    }

    public function test_admin_can_delete_a_student(): void
    {
        $student = Student::factory()->create();

        $this->actingAs($this->admin)
            ->delete(route('admin.siswa.destroy', $student))
            ->assertRedirect(route('admin.siswa.index'))
            ->assertSessionHas('success');

        $this->assertModelMissing($student);
    }

    public function test_guru_cannot_manage_students(): void
    {
        $guru = User::factory()->guru()->create();
        $student = Student::factory()->create();

        $this->actingAs($guru)->getJson(route('admin.siswa.data'))->assertForbidden()->assertJson(['message' => AccessDenied::MESSAGE]);
        $this->actingAs($guru)->delete(route('admin.siswa.destroy', $student))->assertRedirect(route('guru.dashboard'));

        $this->assertModelExists($student);
    }

    public function test_admin_can_mark_a_student_as_moved_to_another_school(): void
    {
        $student = Student::factory()->create(['name' => 'Rina Marlina']);
        Attendance::factory()->create(['student_id' => $student->student_id]);

        $this->actingAs($this->admin)
            ->getJson(route('admin.siswa.status', $student))
            ->assertOk()
            ->assertJsonPath('action', route('admin.siswa.status.update', $student))
            ->assertJsonPath('values.current_status', 'Aktif')
            ->assertJsonPath('values.status', 'aktif');

        $this->actingAs($this->admin)
            ->patchJson(route('admin.siswa.status.update', $student), ['status' => 'pindah', 'note' => 'Pindah ke SDN 2'])
            ->assertOk()
            ->assertJsonPath('alert.title', 'Status siswa diperbarui')
            ->assertJsonPath('alert.text', fn (string $text): bool => str_contains($text, 'Aktif → Pindah sekolah')
                && str_contains($text, 'Pindah ke SDN 2')
                && str_contains($text, 'tidak lagi muncul di daftar absensi'));

        $student->refresh();
        $this->assertSame(StudentStatus::Transferred, $student->status);
        // The student and their attendance stay in the database as history.
        $this->assertDatabaseHas('students', ['student_id' => $student->student_id, 'class_id' => $student->class_id]);
        $this->assertSame(1, $student->attendances()->count());
    }

    public function test_students_who_left_drop_out_of_rosters_promotion_and_transfers(): void
    {
        $schoolClass = SchoolClass::factory()->create();
        $active = Student::factory()->for($schoolClass)->create();
        $moved = Student::factory()->for($schoolClass)->create(['status' => StudentStatus::Transferred]);
        $dropped = Student::factory()->for($schoolClass)->create(['status' => StudentStatus::Dropped]);

        $this->assertSame([$active->student_id], $schoolClass->activeStudents()->pluck('student_id')->all());

        AcademicYear::factory()->create(['year_name' => '2025/2026']);
        $year = AcademicYear::factory()->active()->create(['year_name' => '2026/2027']);
        $this->assertSame(1, $year->studentsAwaitingPromotion()->count());

        $this->actingAs($this->admin)
            ->patchJson(route('admin.siswa.pindah.update', $moved), ['class_id' => SchoolClass::factory()->create()->class_id])
            ->assertUnprocessable()
            ->assertJsonPath('message', "{$moved->name} berstatus Pindah sekolah dan tidak bisa dipindahkan ke kelas lain.");

        $this->actingAs($this->admin)
            ->getJson(route('admin.siswa.data', ['draw' => 1, 'status' => 'keluar']))
            ->assertJsonPath('recordsTotal', 1)
            ->assertJsonPath('data.0.nis', $dropped->nis);
    }

    public function test_status_change_is_validated(): void
    {
        $student = Student::factory()->create();

        $this->actingAs($this->admin)
            ->patchJson(route('admin.siswa.status.update', $student), ['status' => 'cuti'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->assertSame(StudentStatus::Active, $student->fresh()->status);
    }
}
