<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * FAQ modal on the login page: a login tutorial from config/faq.php, shown nowhere else and without a backend.
 */
class LoginFaqTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_login_page_has_the_faq_modal_with_the_login_tutorial(): void
    {
        $response = $this->get(route('login'))
            ->assertOk()
            ->assertSee('data-faq-open', false)
            ->assertSee('<dialog id="login-faq" class="faq-modal" data-faq aria-labelledby="login-faq-title">', false)
            ->assertSee('<h2 class="faq-title" id="login-faq-title">FAQ</h2>', false)
            ->assertSeeInOrder(['Cara login', 'Dari mana saya mendapat akun?', 'Apa fungsi &quot;Ingat saya&quot;?', 'Setelah login, ke mana?', 'Cara keluar (logout)'], false)
            ->assertSee('Tekan tombol Masuk.')
            // It is a tutorial, not a troubleshooting list.
            ->assertDontSee('Lupa password</summary>', false);

        // One <details> per topic; only the first one starts open.
        $html = $response->getContent();
        $this->assertSame(count(config('faq.questions')), substr_count($html, '<details class="faq-item" name="login-faq"'));
        $this->assertSame(1, preg_match_all('/<details class="faq-item" name="login-faq"\s+open\s*>/', $html));
    }

    public function test_admin_contact_comes_from_config_and_the_number_is_converted_for_wa_me(): void
    {
        config([
            'faq.contact.whatsapp' => '0812-3456-7890',
            'faq.contact.whatsapp_message' => 'Halo Admin, saya butuh bantuan.',
            'faq.contact.email' => 'admin@sekolah.sch.id',
        ]);

        $response = $this->get(route('login'));
        $contact = $response->viewData('faq')['contact'];

        $this->assertSame(['label' => '0812-3456-7890', 'url' => 'https://wa.me/6281234567890?text=Halo%20Admin%2C%20saya%20butuh%20bantuan.'], $contact['whatsapp']);
        $this->assertSame(['label' => 'admin@sekolah.sch.id', 'url' => 'mailto:admin@sekolah.sch.id'], $contact['email']);
        $response->assertSee('href="https://wa.me/6281234567890?text=Halo%20Admin%2C%20saya%20butuh%20bantuan."', false)
            ->assertSee('href="mailto:admin@sekolah.sch.id"', false);

        config(['faq.contact.whatsapp' => '+62 812 3456 7890']);

        $this->assertStringStartsWith('https://wa.me/6281234567890?', $this->get(route('login'))->viewData('faq')['contact']['whatsapp']['url']);
    }

    public function test_contact_footer_is_left_out_while_the_contact_is_not_filled_in(): void
    {
        config(['faq.contact.whatsapp' => null, 'faq.contact.email' => '']);

        $response = $this->get(route('login'))->assertOk()->assertDontSee('wa.me', false)->assertDontSee('faq-foot', false);

        $this->assertSame(['whatsapp' => null, 'email' => null], $response->viewData('faq')['contact']);
    }

    public function test_the_faq_is_only_on_the_login_page(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.dashboard'))->assertOk()->assertDontSee('data-faq', false);
        $this->actingAs(User::factory()->guru()->create())->get(route('guru.dashboard'))->assertOk()->assertDontSee('data-faq', false);
    }

    public function test_the_faq_has_no_backend_of_its_own(): void
    {
        $routes = collect(Route::getRoutes())->filter(fn ($route): bool => str_contains($route->uri(), 'faq') || str_contains($route->uri(), 'chat'));

        $this->assertCount(0, $routes, 'The FAQ must stay static: no route may serve it.');
    }
}
