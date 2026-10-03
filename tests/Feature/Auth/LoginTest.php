<?php

namespace Tests\Feature\Auth;

use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_login_page_renders_the_adminator_form(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('class="auth-shell"', false)
            ->assertSee('action="'.route('login.store').'"', false)
            ->assertSee('name="_token"', false)
            ->assertSee(asset('adminator/css/style.css'), false)
            ->assertDontSee('adminator/js/', false)
            ->assertDontSee('fonts.googleapis.com', false);
    }

    public function test_home_redirects_guests_to_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_admin_is_redirected_to_admin_dashboard(): void
    {
        $admin = User::factory()->admin()->create();

        $this->post(route('login.store'), ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect('/admin/dashboard')
            ->assertSessionHas('success');

        $this->assertAuthenticatedAs($admin);
    }

    public function test_guru_is_redirected_to_guru_dashboard(): void
    {
        $guru = User::factory()->guru()->create();

        $this->post(route('login.store'), ['email' => $guru->email, 'password' => 'password'])
            ->assertRedirect('/guru/dashboard');

        $this->assertAuthenticatedAs($guru);
    }

    public function test_wrong_password_is_rejected_with_a_sweetalert_error(): void
    {
        $guru = User::factory()->guru()->create();

        $this->from(route('login'))
            ->post(route('login.store'), ['email' => $guru->email, 'password' => 'salah'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => 'Email atau password salah.']);

        $this->assertGuest();

        $this->from(route('login'))
            ->followingRedirects()
            ->post(route('login.store'), ['email' => $guru->email, 'password' => 'salah'])
            ->assertOk()
            ->assertSee('id="flash-messages"', false)
            ->assertSee('Email atau password salah.');
    }

    public function test_unknown_email_is_rejected(): void
    {
        $this->from(route('login'))
            ->post(route('login.store'), ['email' => 'tidak.ada@absensi.test', 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_login_is_rate_limited(): void
    {
        $guru = User::factory()->guru()->create();

        foreach (range(1, LoginRequest::MAX_ATTEMPTS) as $attempt) {
            $this->post(route('login.store'), ['email' => $guru->email, 'password' => 'salah']);
        }

        $this->post(route('login.store'), ['email' => $guru->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_authenticated_user_visiting_login_goes_to_own_dashboard(): void
    {
        $this->actingAs(User::factory()->guru()->create())
            ->get(route('login'))
            ->assertRedirect(route('guru.dashboard'));

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('login'))
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_user_can_logout(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
