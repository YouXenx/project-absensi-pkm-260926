<?php

namespace Tests\Feature\Admin;

use App\Http\Middleware\AccessDenied;
use App\Http\Middleware\CheckAdmin;
use App\Http\Middleware\EnsureUserIsActive;
use App\Models\HomeroomTeacher;
use App\Models\Subject;
use App\Models\User;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TeacherManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->admin = User::factory()->admin()->create();
    }

    public function test_data_endpoint_lists_only_guru_accounts_with_status_filter(): void
    {
        User::factory()->guru()->create(['name' => 'Guru Aktif']);
        User::factory()->guru()->inactive()->create(['name' => 'Guru Nonaktif']);

        $this->actingAs($this->admin)
            ->getJson(route('admin.guru.data', ['draw' => 1]))
            ->assertOk()
            ->assertJsonPath('recordsTotal', 2)
            ->assertJsonMissing(['name' => e($this->admin->name)]);

        $this->actingAs($this->admin)
            ->getJson(route('admin.guru.data', ['draw' => 2, 'status' => 'inactive']))
            ->assertJsonPath('recordsTotal', 1)
            ->assertJsonPath('data.0.name', 'Guru Nonaktif');
    }

    public function test_admin_can_create_a_guru_account(): void
    {
        $this->actingAs($this->admin)->get(route('admin.guru.index'))->assertOk()->assertSee('id="teacher-modal"', false);

        $this->actingAs($this->admin)
            ->post(route('admin.guru.store'), [
                'name' => 'Sri Wahyuni',
                'email' => 'sri@absensi.test',
                'password' => 'rahasia123',
                'password_confirmation' => 'rahasia123',
                'is_active' => '1',
                'role' => 'admin',
            ])
            ->assertRedirect(route('admin.guru.index'))
            ->assertSessionHas('success');

        $teacher = User::firstWhere('email', 'sri@absensi.test');
        $this->assertSame(UserRole::Guru, $teacher->role, 'Role cannot be escalated from the form.');
        $this->assertTrue($teacher->is_active);
        $this->assertTrue(Hash::check('rahasia123', $teacher->password));
    }

    public function test_password_fields_have_a_show_button_and_accept_any_mix_of_characters(): void
    {
        $this->actingAs($this->admin)->get(route('admin.guru.index'))
            ->assertSee('id="teacher-modal-password" name="password" autocomplete="new-password">', false)
            ->assertSee('id="teacher-modal-password_confirmation"', false)
            ->assertSee('data-password-toggle', false);

        // Upper case, lower case, digits, symbols and a space: stored exactly as typed.
        $password = 'Guru BIG#2026!';

        $this->actingAs($this->admin)
            ->post(route('admin.guru.store'), ['name' => 'Budi', 'email' => 'budi@absensi.test', 'password' => $password, 'password_confirmation' => $password])
            ->assertSessionHasNoErrors();

        $teacher = User::firstWhere('email', 'budi@absensi.test');
        $this->assertTrue(Hash::check($password, $teacher->password));
        $this->assertFalse(Hash::check(strtolower($password), $teacher->password), 'Passwords stay case-sensitive.');

        auth()->logout();
        $this->post(route('login.store'), ['email' => 'budi@absensi.test', 'password' => $password]);
        $this->assertAuthenticatedAs($teacher);
    }

    public function test_guru_account_validation(): void
    {
        User::factory()->guru()->create(['email' => 'dipakai@absensi.test']);

        $this->actingAs($this->admin)
            ->post(route('admin.guru.store'), [
                'name' => '',
                'email' => 'dipakai@absensi.test',
                'password' => 'pendek',
                'password_confirmation' => 'beda',
            ])
            ->assertSessionHasErrors(['name', 'email', 'password']);
    }

    public function test_admin_can_edit_a_guru_and_keep_the_password(): void
    {
        $teacher = User::factory()->guru()->create();
        $originalHash = $teacher->password;

        $this->actingAs($this->admin)
            ->getJson(route('admin.guru.edit', $teacher))
            ->assertOk()
            ->assertJsonPath('values.email', $teacher->email)
            ->assertJsonPath('values.is_active', true)
            ->assertJsonMissingPath('values.password');

        $this->actingAs($this->admin)
            ->put(route('admin.guru.update', $teacher), [
                'name' => 'Nama Diperbarui',
                'email' => $teacher->email,
                'password' => '',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.guru.index'))
            ->assertSessionHasNoErrors();

        $teacher->refresh();
        $this->assertSame('Nama Diperbarui', $teacher->name);
        $this->assertSame($originalHash, $teacher->password);
    }

    public function test_admin_can_deactivate_and_reactivate_a_guru(): void
    {
        $teacher = User::factory()->guru()->create();

        $this->actingAs($this->admin)
            ->patch(route('admin.guru.status', $teacher), ['is_active' => '0'])
            ->assertRedirect(route('admin.guru.index'))
            ->assertSessionHas('success');
        $this->assertFalse($teacher->fresh()->is_active);

        $this->actingAs($this->admin)->patch(route('admin.guru.status', $teacher), ['is_active' => '1']);
        $this->assertTrue($teacher->fresh()->is_active);
    }

    public function test_admin_accounts_cannot_be_reached_through_guru_urls(): void
    {
        $otherAdmin = User::factory()->admin()->create();

        $this->actingAs($this->admin)->getJson(route('admin.guru.edit', $otherAdmin->id))->assertNotFound();
        $this->actingAs($this->admin)->patch(route('admin.guru.status', $otherAdmin->id), ['is_active' => '0'])->assertNotFound();

        $this->assertTrue($otherAdmin->fresh()->is_active);
    }

    public function test_deactivated_guru_cannot_log_in(): void
    {
        $teacher = User::factory()->guru()->inactive()->create();

        $this->post(route('login.store'), ['email' => $teacher->email, 'password' => 'password'])
            ->assertSessionHasErrors(['email' => EnsureUserIsActive::MESSAGE]);

        $this->assertGuest();
    }

    public function test_deactivated_guru_is_signed_out_on_the_next_request(): void
    {
        $teacher = User::factory()->guru()->create();

        $this->actingAs($teacher)->get(route('guru.dashboard'))->assertOk();

        $teacher->update(['is_active' => false]);

        $this->actingAs($teacher)
            ->get(route('guru.dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('alert.text', EnsureUserIsActive::MESSAGE);

        $this->assertGuest();
    }

    public function test_guru_cannot_manage_other_guru_accounts(): void
    {
        $guru = User::factory()->guru()->create();
        $otherGuru = User::factory()->guru()->create(['name' => 'Guru Lain']);

        $this->actingAs($guru)->get(route('admin.guru.index'))
            ->assertRedirect(route('guru.dashboard'))
            ->assertSessionHas('alert.text', AccessDenied::MESSAGE);
        $this->actingAs($guru)->get(route('admin.guru.edit', $otherGuru))->assertRedirect(route('guru.dashboard'));
        $this->actingAs($guru)->put(route('admin.guru.update', $otherGuru), ['name' => 'Diretas', 'email' => 'x@x.test'])->assertRedirect(route('guru.dashboard'));
        $this->actingAs($guru)->patch(route('admin.guru.status', $otherGuru), ['is_active' => '0'])->assertRedirect(route('guru.dashboard'));
        $this->actingAs($guru)->getJson(route('admin.guru.data'))->assertForbidden();

        $otherGuru->refresh();
        $this->assertSame('Guru Lain', $otherGuru->name);
        $this->assertTrue($otherGuru->is_active);
    }

    public function test_controller_gate_still_blocks_guru_if_route_middleware_is_missing(): void
    {
        $guru = User::factory()->guru()->create();
        $otherGuru = User::factory()->guru()->create();

        $this->withoutMiddleware(CheckAdmin::class);

        $this->actingAs($guru)
            ->get(route('admin.guru.index'))
            ->assertRedirect(route('guru.dashboard'))
            ->assertSessionHas('alert.text', AccessDenied::MESSAGE);

        $this->actingAs($guru)->patch(route('admin.guru.status', $otherGuru), ['is_active' => '0'])->assertRedirect(route('guru.dashboard'));
        $this->actingAs($guru)->get(route('admin.siswa.index'))->assertRedirect(route('guru.dashboard'));
        $this->actingAs($guru)->get(route('admin.dashboard'))->assertRedirect(route('guru.dashboard'));

        $this->assertTrue($otherGuru->fresh()->is_active);
    }

    public function test_admin_can_reset_a_guru_password(): void
    {
        $teacher = User::factory()->guru()->create();

        $response = $this->actingAs($this->admin)
            ->patch(route('admin.guru.reset-password', $teacher))
            ->assertRedirect(route('admin.guru.index'))
            ->assertSessionHas('alert.title', 'Password direset');

        preg_match('/data-testid="temporary-password">([^<]+)</', session('alert')['html'], $matches);
        $temporaryPassword = html_entity_decode($matches[1]);

        $this->assertSame(10, strlen($temporaryPassword));
        $this->assertTrue(Hash::check($temporaryPassword, $teacher->fresh()->password));
        $this->assertFalse(Hash::check('password', $teacher->fresh()->password));

        auth()->logout();
        $this->post(route('login.store'), ['email' => $teacher->email, 'password' => $temporaryPassword])->assertRedirect(route('guru.dashboard'));
    }

    public function test_reset_password_is_admin_only_and_guru_accounts_only(): void
    {
        $guru = User::factory()->guru()->create();
        $otherGuru = User::factory()->guru()->create();
        $originalHash = $otherGuru->password;

        $this->actingAs($guru)->patch(route('admin.guru.reset-password', $otherGuru))->assertRedirect(route('guru.dashboard'));
        $this->assertSame($originalHash, $otherGuru->fresh()->password);

        $this->actingAs($this->admin)->patch(route('admin.guru.reset-password', User::factory()->admin()->create()->id))->assertNotFound();
    }

    public function test_admin_can_delete_a_guru_account_that_was_never_assigned(): void
    {
        $teacher = User::factory()->guru()->create(['name' => 'Belum Mengajar']);

        $this->actingAs($this->admin)
            ->deleteJson(route('admin.guru.destroy', $teacher))
            ->assertOk()
            ->assertJsonPath('message', 'Akun guru Belum Mengajar berhasil dihapus.');

        $this->assertModelMissing($teacher);
    }

    public function test_a_guru_with_assignments_is_kept_and_the_message_points_to_deactivation(): void
    {
        $teacher = User::factory()->guru()->create(['name' => 'Sudah Mengajar']);
        Subject::factory()->create(['user_id' => $teacher->id]);
        HomeroomTeacher::factory()->create(['user_id' => $teacher->id]);

        $this->actingAs($this->admin)
            ->deleteJson(route('admin.guru.destroy', $teacher))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Akun Sudah Mengajar tidak bisa dihapus karena masih memiliki 1 penugasan mapel dan 1 penugasan wali kelas yang tersimpan sebagai histori. Nonaktifkan akunnya agar tidak bisa login.');

        $this->assertModelExists($teacher);
    }

    public function test_guru_cannot_delete_accounts(): void
    {
        $guru = User::factory()->guru()->create();
        $otherGuru = User::factory()->guru()->create();

        $this->actingAs($guru)->deleteJson(route('admin.guru.destroy', $otherGuru))->assertForbidden();
        $this->actingAs($this->admin)->deleteJson(route('admin.guru.destroy', User::factory()->admin()->create()->id))->assertNotFound();

        $this->assertModelExists($otherGuru);
    }
}
