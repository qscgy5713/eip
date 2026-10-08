<?php

namespace App\Services;

class CsvExportService
{
    /**
     * 防範 CSV 公式注入 (CSV Formula / DDE Injection - SEC-07)
     * 若單元格內容以 '=', '+', '-', '@', '\t', '\r' 開頭，
     * 前綴單引號 "'" 避免 Excel / Calc / 試算表軟體將其當作可執行公式運算。
     */
    public static function sanitizeCell(mixed $value): mixed
    {
        if ($value === null || !is_string($value)) {
            return $value;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return $value;
        }

        $dangerousChars = ['=', '+', '-', '@', "\t", "\r"];

        if (in_array($value[0], $dangerousChars, true)) {
            return "'" . $value;
        }

        return $value;
    }

    /**
     * 對整行資料進行消毒過濾
     */
    public static function sanitizeRow(array $row): array
    {
        return array_map([self::class, 'sanitizeCell'], $row);
    }
}
