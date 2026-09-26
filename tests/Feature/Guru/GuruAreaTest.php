<?php

namespace Tests\Feature\Guru;

use App\Http\Middleware\AccessDenied;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuruAreaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_dashboard_shows_only_the_signed_in_gurus_own_data(): void
    {
        $guruA = User::factory()->guru()->create(['name' => 'Guru Alpha', 'email' => 'alpha@absensi.test']);
        User::factory()->guru()->create(['name' => 'Guru Bravo', 'email' => 'bravo@absensi.test']);
        Student::factory()->for(SchoolClass::factory()->create(['class_name' => 'Kelas 5C']))->create(['name' => 'Siswa Rahasia']);

        $this->actingAs($guruA)
            ->get(route('guru.dashboard'))
            ->assertOk()
            ->assertViewHas('teacher', fn (User $teacher): bool => $teacher->is($guruA))
            ->assertSee('Guru Alpha')
            ->assertSee('alpha@absensi.test')
            ->assertDontSee('Guru Bravo')
            ->assertDontSee('bravo@absensi.test')
            ->assertDontSee('Siswa Rahasia')
            ->assertDontSee('Total guru');
    }

    public function test_guru_is_turned_away_from_every_admin_url_with_a_sweetalert(): void
    {
        $guru = User::factory()->guru()->create();
        $student = Student::factory()->create();
        $otherGuru = User::factory()->guru()->create();

        $urls = [
            route('admin.dashboard'),
            route('admin.guru.index'), route('admin.guru.edit', $otherGuru),
            route('admin.kelas.index'), route('admin.kelas.edit', $student->class_id),
            route('admin.siswa.index'), route('admin.siswa.edit', $student), route('admin.siswa.pindah', $student),
            route('admin.akun.edit'),
        ];

        foreach ($urls as $url) {
            $this->actingAs($guru)
                ->get($url)
                ->assertRedirect(route('guru.dashboard'))
                ->assertSessionHas('alert.text', AccessDenied::MESSAGE);
        }

        $this->actingAs($guru)
            ->followingRedirects()
            ->get(route('admin.guru.index'))
            ->assertOk()
            ->assertSee('id="flash-messages"', false)
            ->assertSee(AccessDenied::MESSAGE);
    }

    public function test_guru_sidebar_has_no_admin_menu(): void
    {
        $this->actingAs(User::factory()->guru()->create())
            ->get(route('guru.dashboard'))
            ->assertDontSee('/admin/', false)
            ->assertDontSee('<span>Data Guru</span>', false)
            ->assertDontSee('<span>Pengaturan Akun</span>', false)
            ->assertSee('href="'.route('guru.profil.edit').'"', false);
    }
}
