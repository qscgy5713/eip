<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Storage;

/**
 * 站台品牌設定:網站名稱 / Logo / Favicon(存於 system_settings,檔案放 public disk)
 */
class Branding
{
    public const DEFAULT_NAME = 'EIP 企業入口網';

    public const KINDS = ['logo', 'favicon'];

    public static function name(): string
    {
        return (string) (SystemSetting::get('brand_name') ?: self::DEFAULT_NAME);
    }

    public static function path(string $kind): ?string
    {
        $path = SystemSetting::get("brand_{$kind}_path");

        return $path && Storage::disk('public')->exists($path) ? $path : null;
    }

    public static function url(string $kind): ?string
    {
        $path = self::path($kind);
        if (! $path) {
            return null;
        }

        // 帶上檔案更新時間作為版本參數,換圖後瀏覽器不會吃到舊快取
        return route('branding.asset', $kind).'?v='.Storage::disk('public')->lastModified($path);
    }

    /** @return array{name: string, logo_url: ?string, favicon_url: ?string} */
    public static function get(): array
    {
        return [
            'name' => self::name(),
            'logo_url' => self::url('logo'),
            'favicon_url' => self::url('favicon'),
        ];
    }
}
