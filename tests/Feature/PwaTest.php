<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PwaTest extends TestCase
{
    use RefreshDatabase;

    public function test_manifest_is_public_and_valid(): void
    {
        $response = $this->get('/manifest.webmanifest');

        $response->assertOk()->assertHeader('Content-Type', 'application/manifest+json');
        $m = $response->json();
        $this->assertSame('standalone', $m['display']);
        $this->assertSame('/dashboard', $m['start_url']);
        $this->assertNotEmpty($m['name']);
        $this->assertLessThanOrEqual(12, mb_strlen($m['short_name']));
        $sizes = collect($m['icons'])->pluck('sizes')->all();
        $this->assertContains('192x192', $sizes);
        $this->assertContains('512x512', $sizes);
        $this->assertContains('maskable', collect($m['icons'])->pluck('purpose')->all());
    }

    public function test_every_manifest_icon_exists_on_disk(): void
    {
        foreach ($this->get('/manifest.webmanifest')->json('icons') as $icon) {
            $this->assertFileExists(public_path(ltrim($icon['src'], '/')), "圖示不存在:{$icon['src']}");
        }
    }

    public function test_manifest_name_follows_branding(): void
    {
        \App\Models\SystemSetting::set('brand_name', '測試公司入口');

        $this->assertSame('測試公司入口', $this->get('/manifest.webmanifest')->json('name'));
    }

    public function test_login_page_links_manifest(): void
    {
        $html = $this->get('/login')->getContent();

        $this->assertStringContainsString('<link rel="manifest" href="/manifest.webmanifest">', $html);
        $this->assertStringContainsString('apple-touch-icon', $html);
    }

    public function test_service_worker_never_caches_pages_or_data(): void
    {
        $sw = file_get_contents(public_path('sw.js'));

        // 只處理 navigate 失敗時的離線頁;不得有把一般回應寫進快取的行為
        $this->assertStringContainsString("req.mode !== 'navigate'", $sw);
        $this->assertStringNotContainsString('cache.put', $sw);
        $this->assertStringNotContainsString('.put(', $sw);
        $this->assertFileExists(public_path('pwa/offline.html'));
    }
}
