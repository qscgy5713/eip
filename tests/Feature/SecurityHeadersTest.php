<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_headers_are_sent(): void
    {
        $response = $this->get('/login');

        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('geolocation=(self)', $response->headers->get('Permissions-Policy'));
        $this->assertNull($response->headers->get('Strict-Transport-Security'), 'HTTP 連線不應送 HSTS');
    }

    public function test_hsts_is_sent_only_over_https(): void
    {
        $response = $this->get('https://localhost/login');

        $this->assertStringContainsString('max-age=31536000', (string) $response->headers->get('Strict-Transport-Security'));
    }

    public function test_error_pages_also_carry_headers(): void
    {
        $this->get('/no-such-page')->assertNotFound()->assertHeader('Referrer-Policy');
    }

    public function test_csp_uses_nonce_without_unsafe_inline_scripts(): void
    {
        $response = $this->get('/login');
        $csp = $response->headers->get('Content-Security-Policy');

        $this->assertMatchesRegularExpression("/script-src 'self' 'nonce-([A-Za-z0-9+\/=]+)'/", $csp);
        $this->assertStringNotContainsString("script-src 'self' 'unsafe-inline'", $csp);
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
        $this->assertStringContainsString("object-src 'self'", $csp);

        // 頁面上每個可執行的內嵌 <script> 都必須帶同一個 nonce
        preg_match("/'nonce-([^']+)'/", $csp, $m);
        $html = $response->getContent();
        preg_match_all('/<script(?![^>]*type="application\/json")(?![^>]*\ssrc=)[^>]*>/i', $html, $tags);
        $this->assertNotEmpty($tags[0]);
        foreach ($tags[0] as $tag) {
            $this->assertStringContainsString('nonce="'.$m[1].'"', $tag, "內嵌腳本缺少 nonce:$tag");
        }
    }

    public function test_nonce_differs_per_request(): void
    {
        $a = $this->get('/login')->headers->get('Content-Security-Policy');
        $b = $this->get('/login')->headers->get('Content-Security-Policy');

        $this->assertNotSame($a, $b);
    }

    public function test_existing_csp_such_as_document_sandbox_is_not_overwritten(): void
    {
        $response = new \Illuminate\Http\Response('x', 200, ['Content-Security-Policy' => 'sandbox']);
        $result = (new \App\Http\Middleware\SecurityHeaders)->handle(\Illuminate\Http\Request::create('/'), fn () => $response);

        $this->assertSame('sandbox', $result->headers->get('Content-Security-Policy'));
    }
}
