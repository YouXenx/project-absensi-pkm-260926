<?php

namespace Tests\Feature;

use App\AttendanceStatus;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Tests\TestCase;

class AttendanceCorrectionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Attendance $pastRecord;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Date::setTestNow('2026-09-14 10:00:00');

        $this->admin = User::factory()->admin()->create();
        $year = AcademicYear::factory()->active()->create(['year_name' => '2026/2027']);
        $subject = Subject::factory()->create(['academic_year_id' => $year->academic_year_id]);
        $this->pastRecord = Attendance::factory()->create(['subject_id' => $subject->subject_id, 'date' => '2026-08-20', 'status' => 'alpha']);
    }

    protected function tearDown(): void
    {
        Date::setTestNow();

        parent::tearDown();
    }

    public function test_admin_lists_records_of_any_date_and_teacher(): void
    {
        Attendance::factory()->create(['date' => '2026-09-14', 'status' => 'hadir']);

        $this->actingAs($this->admin)
            ->get(route('admin.absensi.index', ['date_from' => '2026-08-01', 'date_to' => '2026-09-14']))
            ->assertOk()
            ->assertSee('Koreksi Absensi');

        $response = $this->actingAs($this->admin)
            ->getJson(route('admin.absensi.data', ['draw' => 1, 'date_from' => '2026-08-01', 'date_to' => '2026-09-14', 'status' => 'alpha']))
            ->assertJsonPath('recordsTotal', 1)
            ->assertJsonPath('data.0.date', '20/08/2026');

        $this->assertStringContainsString(route('admin.absensi.edit', $this->pastRecord), $response->json('data.0.actions'));
        $this->assertStringContainsString(route('admin.absensi.destroy', $this->pastRecord), $response->json('data.0.actions'));
    }

    public function test_admin_can_correct_a_past_record(): void
    {
        $this->actingAs($this->admin)
            ->getJson(route('admin.absensi.edit', $this->pastRecord))
            ->assertOk()
            ->assertJsonPath('action', route('admin.absensi.update', $this->pastRecord))
            ->assertJsonPath('values.status', 'alpha')
            ->assertJsonPath('confirm', "Simpan koreksi absensi {$this->pastRecord->student->name} tanggal 20/08/2026?");

        $this->actingAs($this->admin)
            ->put(route('admin.absensi.update', $this->pastRecord), ['status' => 'sakit', 'description' => 'Surat dokter menyusul'])
            ->assertRedirect(route('admin.absensi.index', ['subject_id' => $this->pastRecord->subject_id, 'date_from' => '2026-08-20', 'date_to' => '2026-08-20']))
            ->assertSessionHas('success');

        $this->pastRecord->refresh();
        $this->assertSame(AttendanceStatus::Sick, $this->pastRecord->status);
        $this->assertSame('Surat dokter menyusul', $this->pastRecord->description);
        $this->assertSame('2026-08-20', $this->pastRecord->date->toDateString());
    }

    public function test_admin_can_delete_a_wrong_record(): void
    {
        $this->actingAs($this->admin)
            ->delete(route('admin.absensi.destroy', $this->pastRecord))
            ->assertSessionHas('success');

        $this->assertModelMissing($this->pastRecord);
    }

    public function test_the_subjects_own_guru_cannot_correct_past_dates_or_delete(): void
    {
        $guru = $this->pastRecord->subject->teacher;

        $this->actingAs($guru)->put(route('guru.riwayat.update', $this->pastRecord), ['status' => 'hadir'])->assertRedirect(route('guru.dashboard'));
        $this->actingAs($guru)->put(route('admin.absensi.update', $this->pastRecord), ['status' => 'hadir'])->assertRedirect(route('guru.dashboard'));
        $this->actingAs($guru)->delete(route('admin.absensi.destroy', $this->pastRecord))->assertRedirect(route('guru.dashboard'));

        $this->assertSame(AttendanceStatus::Absent, $this->pastRecord->fresh()->status);
    }
}
