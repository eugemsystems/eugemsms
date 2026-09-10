<?php

declare(strict_types=1);

namespace Modules\Payroll\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Payroll\Database\Factories\PayslipFactory;
use Modules\People\Models\Staff;

/**
 * Book H3 PPL-05 §2 ⭐ — a payslip is a computed artefact of exactly
 * one payroll run; there is no "recompute in place" — a new run
 * produces new payslips (BR-PPL-05-016's philosophy applied here
 * too). Immutable once created except `document_id`/`distributed_at`.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $payroll_run_id
 * @property int $staff_id
 * @property string $payslip_number
 * @property int $gross_minor
 * @property int $taxable_gross_minor
 * @property int $pensionable_gross_minor
 * @property int $paye_minor
 * @property int $aids_levy_minor
 * @property int $nssa_employee_minor
 * @property int $nec_employee_minor
 * @property int $loan_deduction_minor
 * @property int $third_party_minor
 * @property int $fee_offset_minor
 * @property int $other_deductions_minor
 * @property int $total_deductions_minor
 * @property int $net_pay_minor
 * @property int $nssa_employer_minor
 * @property int $apwcs_minor
 * @property int $zimdef_minor
 * @property int $nec_employer_minor
 * @property int $employer_cost_minor
 * @property string $currency
 * @property int $ytd_gross_minor
 * @property int $ytd_paye_minor
 * @property int $ytd_nssa_minor
 * @property array<string, mixed>|null $calculation_trace
 * @property int|null $document_id
 */
class Payslip extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<PayslipFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    /**
     * @var array<int, string>
     */
    private const array MUTABLE_AFTER_CREATE = ['document_id', 'distributed_at'];

    protected $fillable = [
        'school_id', 'payroll_run_id', 'staff_id', 'payslip_number', 'basic_minor',
        'allowances_minor', 'overtime_minor', 'bonus_minor', 'gross_minor', 'taxable_gross_minor',
        'pensionable_gross_minor', 'paye_minor', 'aids_levy_minor', 'nssa_employee_minor',
        'nec_employee_minor', 'loan_deduction_minor', 'third_party_minor', 'fee_offset_minor',
        'other_deductions_minor', 'total_deductions_minor', 'net_pay_minor', 'nssa_employer_minor',
        'apwcs_minor', 'zimdef_minor', 'nec_employer_minor', 'employer_cost_minor', 'currency',
        'usd_net_minor', 'zwg_net_minor', 'exchange_rate_id', 'ytd_gross_minor', 'ytd_paye_minor',
        'ytd_nssa_minor', 'days_worked', 'unpaid_leave_days', 'calculation_trace', 'document_id',
        'distributed_at',
    ];

    protected function casts(): array
    {
        return [
            'calculation_trace' => 'array',
            'days_worked' => 'decimal:2',
            'unpaid_leave_days' => 'decimal:2',
            'distributed_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return PayslipFactory::new();
    }

    protected static function booted(): void
    {
        static::updating(function (Model $model): void {
            $dirty = array_keys($model->getDirty());
            $notAllowed = array_diff($dirty, self::MUTABLE_AFTER_CREATE);

            if ($notAllowed !== []) {
                throw new InvalidStateTransitionException(
                    'A payslip is immutable once created, except for document_id and distributed_at.',
                    ['dirty' => $notAllowed],
                );
            }
        });
    }

    /**
     * @return BelongsTo<PayrollRun, $this>
     */
    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    /**
     * @return HasMany<PayslipLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(PayslipLine::class);
    }
}
