<?php

namespace Tests\Feature;

use App\Models\User;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_guru_updates_only_their_own_profile(): void
    {
        $guruA = User::factory()->guru()->create();
        $guruB = User::factory()->guru()->create(['name' => 'Guru B']);

        $this->actingAs($guruA)->get(route('guru.profil.edit'))->assertOk()->assertSee($guruA->email)->assertDontSee($guruB->email);

        // Extra ids/roles in the payload are ignored: the target is always the signed-in user.
        $this->actingAs($guruA)
            ->put(route('guru.profil.update'), [
                'id' => $guruB->id,
                'role' => 'admin',
                'is_active' => '0',
                'name' => 'Guru A Baru',
                'email' => $guruA->email,
            ])
            ->assertRedirect(route('guru.profil.edit'))
            ->assertSessionHas('success');

        $guruA->refresh();
        $this->assertSame('Guru A Baru', $guruA->name);
        $this->assertSame(UserRole::Guru, $guruA->role);
        $this->assertTrue($guruA->is_active);
        $this->assertSame('Guru B', $guruB->fresh()->name);
    }

    public function test_changing_password_requires_the_current_password(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.akun.edit'))->assertOk()->assertSee('Pengaturan Akun');

        $this->actingAs($admin)
            ->put(route('admin.akun.update'), [
                'name' => $admin->name,
                'email' => $admin->email,
                'current_password' => 'salah',
                'password' => 'passwordbaru',
                'password_confirmation' => 'passwordbaru',
            ])
            ->assertSessionHasErrors(['current_password' => 'Password saat ini salah.']);

        $this->actingAs($admin)
            ->put(route('admin.akun.update'), [
                'name' => $admin->name,
                'email' => $admin->email,
                'current_password' => 'password',
                'password' => 'passwordbaru',
                'password_confirmation' => 'passwordbaru',
            ])
            ->assertRedirect(route('admin.akun.edit'))
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('passwordbaru', $admin->fresh()->password));
    }

    public function test_each_role_uses_its_own_profile_url(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get(route('guru.profil.edit'))->assertRedirect(route('admin.dashboard'));
        $this->actingAs(User::factory()->guru()->create())->get(route('admin.akun.edit'))->assertRedirect(route('guru.dashboard'));
    }
}
