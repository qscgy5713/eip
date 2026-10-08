<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SystemSetting extends Model
{
    use HasFactory;

    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'key',
        'value',
        'description',
    ];

    /**
     * 快取前綴
     */
    protected const CACHE_PREFIX = 'system_setting:';

    /**
     * 獲取設定值（支援快取）
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::remember(self::CACHE_PREFIX . $key, 3600, function () use ($key, $default) {
            $setting = self::find($key);
            return $setting ? $setting->value : $default;
        });
    }

    /**
     * 儲存設定值並清除快取
     */
    public static function set(string $key, mixed $value, ?string $description = null): self
    {
        $setting = self::updateOrCreate(
            ['key' => $key],
            [
                'value' => is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string) $value,
                'description' => $description,
            ]
        );

        Cache::forget(self::CACHE_PREFIX . $key);

        return $setting;
    }

    /**
     * 刪除設定
     */
    public static function forget(string $key): void
    {
        self::where('key', $key)->delete();
        Cache::forget(self::CACHE_PREFIX . $key);
    }
}
