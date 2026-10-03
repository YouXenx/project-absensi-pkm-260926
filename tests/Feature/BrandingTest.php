<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The school's name and logo replace the Laravel and Adminator defaults on every page a user sees.
 */
class BrandingTest extends TestCase
{
    use RefreshDatabase;

    private const SCHOOL = 'Bina Indonesia Gemilang Boarding School';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_login_page_carries_the_school_name_and_no_laravel_branding(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('<title>Login - '.self::SCHOOL.'</title>', false)
            ->assertSee('<div class="auth-school-name">'.self::SCHOOL.'</div>', false)
            ->assertDontSee('Laravel');
    }

    public function test_dashboards_use_the_menu_name_in_the_title_and_the_short_name_in_the_sidebar(): void
    {
        foreach (['admin' => 'Dashboard Admin', 'guru' => 'Dashboard Guru'] as $role => $menu) {
            $this->actingAs(User::factory()->{$role}()->create())
                ->get(route("{$role}.dashboard"))
                ->assertOk()
                ->assertSee("<title>{$menu} - ".self::SCHOOL.'</title>', false)
                ->assertSee('title="'.self::SCHOOL.'">BIG Boarding School</div>', false)
                ->assertDontSee('Laravel');
        }
    }

    public function test_the_logo_and_favicon_are_used_once_their_files_exist(): void
    {
        // Any image that ships with the app stands in for the school's files.
        config([
            'adminator.brand.logo' => 'adminator/images/logo.png',
            'adminator.brand.favicon' => 'adminator/images/logo.png',
            'adminator.brand.touch_icon' => 'adminator/images/logo.png',
        ]);

        $this->get(route('login'))
            ->assertSee('<img src="'.asset('adminator/images/logo.png').'" alt="Logo '.self::SCHOOL.'"', false)
            ->assertSee('<link rel="icon" type="image/png" sizes="32x32" href="'.asset('adminator/images/logo.png').'">', false)
            ->assertSee('<link rel="apple-touch-icon" href="'.asset('adminator/images/logo.png').'">', false);
    }

    public function test_pages_still_render_while_the_logo_file_is_missing(): void
    {
        config(['adminator.brand.logo' => 'images/missing.jpg', 'adminator.brand.favicon' => 'images/missing.png']);

        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('images/missing', false)
            ->assertDontSee('rel="icon"', false);
    }

    public function test_login_panel_cycles_through_the_school_photos_every_three_seconds(): void
    {
        $response = $this->get(route('login'))
            ->assertOk()
            ->assertSee('data-slides data-slides-interval="3000"', false)
            ->assertSee('class="auth-slide is-active" style="background-image:url(\''.asset('images/login/slide-1.jpg').'\')"', false)
            // Later photos are fetched by the script one step ahead, not with the page.
            ->assertSee('class="auth-slide" data-slide-src="'.asset('images/login/slide-2.jpg').'"', false);

        $this->assertSame(4, preg_match_all('/class="auth-slide( is-active)?"/', $response->getContent()));
    }

    public function test_login_form_panel_has_the_particles_layer(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('<div id="login-particles" class="auth-particles" data-particles aria-hidden="true"></div>', false);
    }

    public function test_login_panel_falls_back_to_the_plain_panel_without_photos(): void
    {
        config(['adminator.login.slides' => ['images/login/missing.jpg']]);

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('<aside class="auth-aside">', false)
            ->assertDontSee('data-slides', false);
    }
}
