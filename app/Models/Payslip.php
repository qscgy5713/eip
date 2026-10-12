<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payslip extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'year',
        'month',
        'period',
        'base_salary',
        'allowance',
        'performance_bonus',
        'bonus',
        'overtime_pay',
        'other_earnings',
        'other_additions',
        'labor_insurance',
        'health_insurance',
        'pension_contribution',
        'pension_self',
        'tax_withholding',
        'other_deductions',
        'total_earnings',
        'gross_pay',
        'total_deductions',
        'net_pay',
        'status',
        'notes',
        'note',
        'published_at',
    ];

    protected static function booted()
    {
        static::saving(function (Payslip $payslip) {
            // 自動計算總額
            $totals = static::computeTotals($payslip->attributesToArray());
            if (empty($payslip->total_earnings)) {
                $payslip->total_earnings = $totals['total_earnings'];
            }
            if (empty($payslip->total_deductions)) {
                $payslip->total_deductions = $totals['total_deductions'];
            }
            if (empty($payslip->net_pay)) {
                $payslip->net_pay = $totals['net_pay'];
            }
        });
    }

    public function setPeriodAttribute($value): void
    {
        if ($value && str_contains($value, '-')) {
            $parts = explode('-', $value);
            $this->attributes['year'] = (int) $parts[0];
            $this->attributes['month'] = (int) $parts[1];
        }
    }

    public function setGrossPayAttribute($value): void
    {
        $this->attributes['total_earnings'] = $value;
    }

    public function setBonusAttribute($value): void
    {
        $this->attributes['performance_bonus'] = $value;
    }

    public function setOtherAdditionsAttribute($value): void
    {
        $this->attributes['other_earnings'] = $value;
    }

    public function setPensionSelfAttribute($value): void
    {
        $this->attributes['pension_contribution'] = $value;
    }

    public function setNoteAttribute($value): void
    {
        $this->attributes['notes'] = $value;
    }

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'base_salary' => 'decimal:2',
            'allowance' => 'decimal:2',
            'performance_bonus' => 'decimal:2',
            'overtime_pay' => 'decimal:2',
            'other_earnings' => 'decimal:2',
            'labor_insurance' => 'decimal:2',
            'health_insurance' => 'decimal:2',
            'pension_contribution' => 'decimal:2',
            'tax_withholding' => 'decimal:2',
            'other_deductions' => 'decimal:2',
            'total_earnings' => 'decimal:2',
            'total_deductions' => 'decimal:2',
            'net_pay' => 'decimal:2',
            'published_at' => 'datetime',
        ];
    }

    protected $appends = ['status_label', 'period_label', 'period', 'gross_pay'];

    public function getPeriodAttribute(): string
    {
        return sprintf('%04d-%02d', $this->year ?? 0, $this->month ?? 0);
    }

    public function getGrossPayAttribute(): float
    {
        return (float) ($this->total_earnings ?? 0);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 計算應發、應扣與實發金額
     */
    public static function computeTotals(array $data): array
    {
        $baseSalary = (float) ($data['base_salary'] ?? 0);
        $allowance = (float) ($data['allowance'] ?? 0);
        $performanceBonus = (float) ($data['performance_bonus'] ?? 0);
        $overtimePay = (float) ($data['overtime_pay'] ?? 0);
        $otherEarnings = (float) ($data['other_earnings'] ?? 0);

        $totalEarnings = $baseSalary + $allowance + $performanceBonus + $overtimePay + $otherEarnings;

        $laborInsurance = (float) ($data['labor_insurance'] ?? 0);
        $healthInsurance = (float) ($data['health_insurance'] ?? 0);
        $pensionContribution = (float) ($data['pension_contribution'] ?? 0);
        $taxWithholding = (float) ($data['tax_withholding'] ?? 0);
        $otherDeductions = (float) ($data['other_deductions'] ?? 0);

        $totalDeductions = $laborInsurance + $healthInsurance + $pensionContribution + $taxWithholding + $otherDeductions;
        $netPay = $totalEarnings - $totalDeductions;

        return [
            'total_earnings' => round($totalEarnings, 2),
            'total_deductions' => round($totalDeductions, 2),
            'net_pay' => round($netPay, 2),
        ];
    }

    /**
     * 判定使用者是否有權調閱此薪資單 (IDOR 防越權)
     */
    public function canAccess(User $user): bool
    {
        if ($user->isAdmin() || $user->isHr()) {
            return true;
        }

        if ($this->user_id === $user->id && $this->status === 'published') {
            return true;
        }

        return false;
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'draft' => '草稿未發布',
            'published' => '已正式發布',
            default => (string) ($this->status ?? '草稿未發布'),
        };
    }

    public function getPeriodLabelAttribute(): string
    {
        return "{$this->year} 年 " . str_pad((string)$this->month, 2, '0', STR_PAD_LEFT) . " 月份";
    }
}
