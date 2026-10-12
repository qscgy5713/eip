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
}
