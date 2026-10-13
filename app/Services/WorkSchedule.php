<?php

namespace App\Services;

use App\Models\SystemSetting;

/**
 * 公司上下班時間設定(存在 system_settings,由「考勤設定」頁維護)。
 * 預設值與過去寫死的行為相同:9:00 上班、寬限 30 分鐘(9:30 後算遲到)、18:00 下班、滿 8 小時。
 */
class WorkSchedule
{
    public const DEFAULTS = ['start' => '09:00', 'end' => '18:00', 'grace' => 30, 'required_hours' => 8.0];

    /** @return array{start: string, end: string, grace: int, required_hours: float} */
    public function get(): array
    {
        $start = $this->time(SystemSetting::get('work_start')) ?? self::DEFAULTS['start'];
        $end = $this->time(SystemSetting::get('work_end')) ?? self::DEFAULTS['end'];
        $grace = SystemSetting::get('work_late_grace_minutes');
        $hours = SystemSetting::get('work_required_hours');

        return [
            'start' => $start,
            'end' => $end,
            'grace' => is_numeric($grace) ? max(0, min(240, (int) $grace)) : self::DEFAULTS['grace'],
            'required_hours' => is_numeric($hours) ? max(1.0, min(24.0, (float) $hours)) : self::DEFAULTS['required_hours'],
        ];
    }

    /** 超過這個時間(H:i)打上班卡就算遲到 */
    public function lateAfter(): string
    {
        $c = $this->get();

        return \Carbon\Carbon::createFromFormat('H:i', $c['start'])->addMinutes($c['grace'])->format('H:i');
    }

    public function isLate(\DateTimeInterface $clockIn): bool
    {
        return $clockIn->format('H:i') > $this->lateAfter();
    }

    public function update(array $data): array
    {
        SystemSetting::set('work_start', $data['work_start'], '上班時間');
        SystemSetting::set('work_end', $data['work_end'], '下班時間');
        SystemSetting::set('work_late_grace_minutes', (string) $data['work_late_grace_minutes'], '遲到寬限分鐘數');
        SystemSetting::set('work_required_hours', (string) $data['work_required_hours'], '每日應工作時數(不足視為早退)');

        return $this->get();
    }

    private function time(mixed $v): ?string
    {
        return is_string($v) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $v) ? $v : null;
    }
}
