<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * FAQ help widget on the login page: fixed questions and answers, shown nowhere else and without a backend.
 */
class LoginChatbotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_login_page_carries_the_widget_with_its_questions_and_greeting(): void
    {
        $response = $this->get(route('login'))
            ->assertOk()
            ->assertSee('data-chatbot-toggle', false)
            ->assertSee('<script type="application/json" data-chatbot-config>', false);

        $chatbot = $response->viewData('chatbot');

        $this->assertSame('Halo! Ada kendala login?', $chatbot['greeting'][0]);
        $this->assertSame(
            ['Lupa password', 'Akun tidak bisa login / dinonaktifkan', 'Siswa saya tidak bisa diabsen', 'Cara lain menghubungi admin'],
            array_column($chatbot['questions'], 'label'),
        );

        foreach ($chatbot['questions'] as $question) {
            $this->assertNotEmpty($question['answer'], "{$question['id']} has no answer.");
        }
    }

    public function test_admin_contact_comes_from_config_and_the_number_is_converted_for_wa_me(): void
    {
        config([
            'chatbot.contact.whatsapp' => '0812-3456-7890',
            'chatbot.contact.whatsapp_message' => 'Halo Admin, saya butuh bantuan.',
            'chatbot.contact.email' => 'admin@sekolah.sch.id',
        ]);

        $contact = $this->get(route('login'))->viewData('chatbot')['contact'];

        $this->assertSame(['label' => '0812-3456-7890', 'url' => 'https://wa.me/6281234567890?text=Halo%20Admin%2C%20saya%20butuh%20bantuan.'], $contact['whatsapp']);
        $this->assertSame(['label' => 'admin@sekolah.sch.id', 'url' => 'mailto:admin@sekolah.sch.id'], $contact['email']);

        config(['chatbot.contact.whatsapp' => '+62 812 3456 7890']);

        $this->assertStringStartsWith('https://wa.me/6281234567890?', $this->get(route('login'))->viewData('chatbot')['contact']['whatsapp']['url']);
    }

    public function test_contact_links_are_left_out_while_the_contact_is_not_filled_in(): void
    {
        config(['chatbot.contact.whatsapp' => null, 'chatbot.contact.email' => '']);

        $response = $this->get(route('login'))->assertOk()->assertDontSee('wa.me', false);

        $this->assertSame(['whatsapp' => null, 'email' => null], $response->viewData('chatbot')['contact']);
    }

    public function test_the_widget_is_only_on_the_login_page(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.dashboard'))->assertOk()->assertDontSee('data-chatbot', false);
        $this->actingAs(User::factory()->guru()->create())->get(route('guru.dashboard'))->assertOk()->assertDontSee('data-chatbot', false);
    }

    public function test_the_widget_has_no_backend_of_its_own(): void
    {
        $chatRoutes = collect(Route::getRoutes())->filter(fn ($route): bool => str_contains($route->uri(), 'chat'));

        $this->assertCount(0, $chatRoutes, 'The FAQ widget must stay static: no route may serve it.');
    }
}
