<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['date', 'name', 'type'])]
class Holiday extends Model
{
    public const TYPE_HOLIDAY = 'holiday';

    public const TYPE_WORKDAY = 'workday';

    /** 日期固定的國定假日(農曆節日與補假、補班每年不同,須由人資依人事行政總處公告自行登錄) */
    public const FIXED_DATE_HOLIDAYS = [
        '01-01' => '開國紀念日(元旦)',
        '02-28' => '和平紀念日',
        '04-04' => '兒童節',
        '05-01' => '勞動節',
        '10-10' => '國慶日',
    ];

    protected function casts(): array
    {
        return ['date' => 'date:Y-m-d'];
    }
}
