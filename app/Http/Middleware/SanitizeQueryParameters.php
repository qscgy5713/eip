<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 清理 GET 查詢參數:格式不合的篩選條件(陣列型搜尋字、非數字的 *_id、非法的 month/year/week/日期)
 * 直接忽略並回到預設值,避免這些值流入資料庫或 Carbon 造成 500。
 */
class SanitizeQueryParameters
{
    /** 一律視為單一字串/數字的篩選鍵,若給成陣列則丟棄 */
    private const SCALAR_KEYS = [
        'search', 'q', 'keyword', 'status', 'category', 'scope', 'sort', 'direction', 'type', 'tab', 'filter',
        'month', 'year', 'week', 'date', 'page', 'per_page',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->method(), ['GET', 'HEAD'], true)) {
            return $next($request);
        }

        $query = $request->query;

        foreach ($query->all() as $key => $value) {
            $key = (string) $key;

            if (is_array($value)) {
                if (in_array($key, self::SCALAR_KEYS, true) || $key === 'id' || str_ends_with($key, '_id') || str_ends_with($key, '_date')) {
                    $query->remove($key);
                }

                continue;
            }

            $value = (string) $value;
            $valid = match (true) {
                $key === 'month' => (bool) preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value),
                $key === 'year' => (bool) preg_match('/^\d{4}$/', $value),
                $key === 'week' => (bool) preg_match('/^\d{1,2}$/', $value) && (int) $value >= 1 && (int) $value <= 53,
                in_array($key, ['page', 'per_page', 'id'], true) || str_ends_with($key, '_id') => (bool) preg_match('/^\d{1,18}$/', $value),
                $key === 'date' || str_ends_with($key, '_date') => $value === '' || (bool) preg_match('/^\d{4}-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/', $value),
                default => true,
            };

            if (! $valid) {
                $query->remove($key);
            } elseif ($key === 'per_page' && (int) $value > 100) {
                $query->set('per_page', '100');
            }
        }

        return $next($request);
    }
}
