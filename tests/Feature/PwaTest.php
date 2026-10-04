<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Installable app (PWA): manifest, icons, offline page and the service worker's caching rules.
 */
class PwaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_manifest_describes_an_installable_app(): void
    {
        $manifest = $this->get(route('pwa.manifest'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/manifest+json')
            ->json();

        $this->assertSame('Bina Indonesia Gemilang Boarding School', $manifest['name']);
        $this->assertSame('BIG Boarding School', $manifest['short_name']);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame('/', $manifest['start_url']);
        $this->assertSame('/', $manifest['scope']);

        // Chrome needs a 192px and a 512px icon; Android launchers want a maskable one as well.
        $icons = collect($manifest['icons']);
        $this->assertSame(['192x192', '512x512'], $icons->where('purpose', 'any')->pluck('sizes')->all());
        $this->assertTrue($icons->contains('purpose', 'maskable'));
    }

    public function test_every_manifest_icon_exists_with_the_size_it_declares(): void
    {
        foreach (config('adminator.pwa.icons') as $icon) {
            $path = public_path($icon['src']);

            $this->assertFileExists($path);

            [$width, $height] = getimagesize($path);
            $this->assertSame($icon['sizes'], "{$width}x{$height}", $icon['src']);
        }
    }

    public function test_pages_link_the_manifest_and_set_the_theme_colour(): void
    {
        $this->get(route('login'))
            ->assertSee('<link rel="manifest" href="'.route('pwa.manifest').'">', false)
            ->assertSee('<meta name="theme-color" content="#2563eb">', false)
            ->assertSee('data-pwa-install hidden', false);

        $this->actingAs(User::factory()->guru()->create())
            ->get(route('guru.dashboard'))
            ->assertSee('<link rel="manifest" href="'.route('pwa.manifest').'">', false);
    }

    public function test_offline_page_is_public_and_self_contained(): void
    {
        $this->get(route('pwa.offline'))
            ->assertOk()
            ->assertSee('Sedang offline')
            // It is stored on the device, so it must not depend on other stylesheets or scripts.
            ->assertDontSee('<link rel="stylesheet"', false)
            ->assertDontSee('<script', false);
    }

    public function test_service_worker_never_stores_pages_or_data(): void
    {
        $worker = file_get_contents(public_path('sw.js'));

        // Only the offline page and one icon are stored up front; pages go to the network.
        $this->assertStringContainsString("const PRECACHE = [OFFLINE_URL, '/images/pwa/icon-192.png'];", $worker);
        $this->assertStringContainsString('fetch(request).catch(() => caches.match(OFFLINE_URL))', $worker);
        $this->assertStringContainsString("request.method !== 'GET'", $worker);

        // The only runtime cache is for hashed build assets.
        $this->assertSame(1, substr_count($worker, 'cache.put('));
        $this->assertStringContainsString("url.pathname.startsWith('/build/assets/')", $worker);
    }
}
