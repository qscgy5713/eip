<?php

namespace App\Services;

use App\Models\Holiday;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/** 工作日計算:週一至週五,扣掉國定假日,補班日(週末要上班)算工作日。 */
class WorkdayService
{
    /** @return array<string, string> [Y-m-d => type] */
    public function exceptionsBetween(CarbonInterface $start, CarbonInterface $end): array
    {
        return Holiday::whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get(['date', 'type'])
            ->mapWithKeys(fn ($h) => [$h->date->toDateString() => $h->type])
            ->all();
    }

    public function isWorkday(CarbonInterface $day, ?array $exceptions = null): bool
    {
        $exceptions ??= $this->exceptionsBetween($day, $day);
        $type = $exceptions[$day->toDateString()] ?? null;

        if ($type === Holiday::TYPE_HOLIDAY) {
            return false;
        }
        if ($type === Holiday::TYPE_WORKDAY) {
            return true;
        }

        return $day->isWeekday();
    }

    /** 區間內(含頭尾)的工作日數;起日大於迄日回傳 0。 */
    public function countWorkdays(CarbonInterface $start, CarbonInterface $end): int
    {
        $start = Carbon::parse($start)->startOfDay();
        $end = Carbon::parse($end)->startOfDay();
        if ($start->gt($end)) {
            return 0;
        }
        // 防呆:最長計算一年,避免惡意或誤填超長區間拖垮請求
        if ($start->diffInDays($end) > 366) {
            $end = $start->copy()->addDays(366);
        }

        $exceptions = $this->exceptionsBetween($start, $end);
        $count = 0;
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            if ($this->isWorkday($d, $exceptions)) {
                $count++;
            }
        }

        return $count;
    }
}
