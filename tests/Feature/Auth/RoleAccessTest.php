<?php

namespace Tests\Feature\Auth;

use App\Http\Middleware\AccessDenied;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function adminRoutes(): array
    {
        return self::named(['admin.dashboard', 'admin.guru.index', 'admin.kelas.index', 'admin.mapel.index', 'admin.siswa.index', 'admin.jadwal.index', 'admin.laporan.index', 'admin.akun.edit']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function guruRoutes(): array
    {
        return self::named(['guru.dashboard', 'guru.mapel.index', 'guru.absensi.index', 'guru.riwayat.index', 'guru.nilai.index', 'guru.rekap.index', 'guru.jadwal.index', 'guru.profil.edit']);
    }

    #[DataProvider('adminRoutes')]
    public function test_admin_can_open_admin_pages(string $routeName): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route($routeName))
            ->assertOk();
    }

    #[DataProvider('adminRoutes')]
    public function test_guru_is_turned_away_from_admin_pages(string $routeName): void
    {
        $this->actingAs(User::factory()->guru()->create())
            ->get(route($routeName))
            ->assertRedirect(route('guru.dashboard'))
            ->assertSessionHas('alert.text', AccessDenied::MESSAGE);
    }

    #[DataProvider('guruRoutes')]
    public function test_guru_can_open_guru_pages(string $routeName): void
    {
        $this->actingAs(User::factory()->guru()->create())
            ->get(route($routeName))
            ->assertOk();
    }

    #[DataProvider('guruRoutes')]
    public function test_admin_is_turned_away_from_guru_pages(string $routeName): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route($routeName))
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('alert.text', AccessDenied::MESSAGE);
    }

    #[DataProvider('adminRoutes')]
    public function test_guests_are_sent_to_login(string $routeName): void
    {
        $this->get(route($routeName))->assertRedirect(route('login'));
    }

    public function test_access_denied_dialog_is_shown_after_redirect(): void
    {
        $this->actingAs(User::factory()->guru()->create())
            ->followingRedirects()
            ->get(route('admin.siswa.index'))
            ->assertOk()
            ->assertSee('id="flash-messages"', false)
            ->assertSee(AccessDenied::MESSAGE);
    }

    public function test_dashboards_greet_the_user_by_name(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Siti Aminah']);
        $guru = User::factory()->guru()->create(['name' => 'Budi Santoso']);

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertSeeInOrder(['Selamat datang,', 'Siti Aminah'], false);

        $this->actingAs($guru)->get(route('guru.dashboard'))
            ->assertSeeInOrder(['Selamat datang,', 'Budi Santoso'], false);
    }

    /**
     * @param  list<string>  $routes
     * @return array<string, array{string}>
     */
    private static function named(array $routes): array
    {
        return collect($routes)->mapWithKeys(fn (string $route): array => [$route => [$route]])->all();
    }
}
