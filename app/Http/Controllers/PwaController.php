<?php

namespace App\Http\Controllers;

use App\Services\Branding;
use Illuminate\Http\JsonResponse;

class PwaController extends Controller
{
    /** Web App Manifest:名稱跟著「品牌設定」走,讓公司可以換成自己的名稱。 */
    public function manifest(): JsonResponse
    {
        $name = Branding::name();

        return response()->json([
            'name' => $name,
            'short_name' => mb_substr($name, 0, 12),
            'description' => '企業資訊入口網:簽核、考勤、公告與協作',
            'id' => '/',
            'start_url' => '/dashboard',
            'scope' => '/',
            'display' => 'standalone',
            'orientation' => 'any',
            'lang' => 'zh-Hant',
            'background_color' => '#f9fafb',
            'theme_color' => '#2547d9',
            'icons' => [
                ['src' => '/pwa/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/pwa/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/pwa/icon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
        ], 200, ['Content-Type' => 'application/manifest+json', 'Cache-Control' => 'no-cache']);
    }
}
