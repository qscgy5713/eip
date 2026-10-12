<?php

namespace App\Models\Concerns;

use DateTimeInterface;

/**
 * 模型轉 JSON 時日期保留應用時區位移(例如 2026-02-12T00:00:00+08:00)。
 * 預設的 `...Z`(UTC) 會讓前端 substring(0, 10) 取日期時,台北午夜的日期差一天。
 */
trait SerializesLocalDates
{
    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d\TH:i:sP');
    }
}
