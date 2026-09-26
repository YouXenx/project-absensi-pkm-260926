<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SidebarTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private const ADMIN_MENU = ['Data Guru', 'Data Kelas', 'Data Siswa', 'Tahun Ajaran', 'Kenaikan Kelas', 'Wali Kelas', 'Mata Pelajaran', 'Jadwal Pelajaran', 'Koreksi Absensi', 'Rekap Absensi', 'Rekap per Mapel', 'Pengaturan Akun'];

    /** @var list<string> */
    private const GURU_MENU = ['Mapel yang Diampu', 'Absensi Siswa', 'Riwayat Absensi', 'Input Nilai', 'Rekap Absensi Mapel', 'Rekap per Mapel Saya', 'Jadwal Mengajar', 'Profil Saya'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_admin_sees_the_complete_admin_menu_and_no_guru_menu(): void
    {
        $response = $this->actingAs(User::factory()->admin()->create())->get(route('admin.dashboard'));

        $response->assertOk()
            ->assertSee('class="d-sidebar"', false)
            ->assertSee(route('admin.dashboard'), false);

        foreach (['admin.guru.index', 'admin.kelas.index', 'admin.siswa.index', 'admin.tahun-ajaran.index', 'admin.kenaikan.index', 'admin.wali-kelas.index', 'admin.mapel.index', 'admin.jadwal.index', 'admin.absensi.index', 'admin.laporan.index', 'admin.akun.edit'] as $route) {
            $response->assertSee('href="'.route($route).'"', false);
        }

        foreach (self::ADMIN_MENU as $label) {
            $response->assertSee("<span>{$label}</span>", false);
        }

        // "Absensi Siswa" is also the brand tagline, so match the menu markup itself.
        foreach (self::GURU_MENU as $label) {
            $response->assertDontSee("<span>{$label}</span>", false);
        }

        $response->assertDontSee('href="'.url('/guru/'), false);
    }

    public function test_guru_html_contains_no_admin_menu_at_all(): void
    {
        $response = $this->actingAs(User::factory()->guru()->create())->get(route('guru.dashboard'));

        $response->assertOk();

        foreach (['guru.dashboard', 'guru.mapel.index', 'guru.absensi.index', 'guru.riwayat.index', 'guru.nilai.index', 'guru.rekap.index', 'guru.jadwal.index', 'guru.profil.edit'] as $route) {
            $response->assertSee('href="'.route($route).'"', false);
        }

        foreach (self::GURU_MENU as $label) {
            $response->assertSee("<span>{$label}</span>", false);
        }

        // Raw HTML check (what view-source shows): no admin menu items and no /admin URLs anywhere.
        // Menu markup is matched because some words (e.g. "Rekap Absensi") also occur in guru labels.
        foreach (self::ADMIN_MENU as $label) {
            $response->assertDontSee("<span>{$label}</span>", false);
        }

        $response->assertDontSee('/admin/', false);
    }

    public function test_active_menu_item_follows_the_current_route(): void
    {
        $this->actingAs(User::factory()->guru()->create())
            ->get(route('guru.absensi.index'))
            ->assertSee('<a class="nav-link is-active" href="'.route('guru.absensi.index').'">', false);
    }

    public function test_both_roles_get_a_logout_button(): void
    {
        foreach ([User::factory()->admin()->create(), User::factory()->guru()->create()] as $user) {
            $this->actingAs($user)
                ->get(route($user->role->dashboardRoute()))
                ->assertSee('action="'.route('logout').'"', false)
                ->assertSee($user->name);
        }
    }
}
