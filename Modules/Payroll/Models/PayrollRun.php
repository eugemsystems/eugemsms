<?php

declare(strict_types=1);

namespace Modules\Payroll\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Payroll\Database\Factories\PayrollRunFactory;

/**
 * Book H3 PPL-05 §2/§4 ⭐/BR-PPL-05-016. The identity/period columns
 * are immutable from creation; once `status` reaches `posted`, only
 * a narrow "paid" transition (`status`, `bank_file_id`) may still
 * change — everything else about a posted run is permanent.
 * Correction is a reversal plus a supplementary run, both retained,
 * never an edit to this row.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $academic_year_id
 * @property int $term_id
 * @property string $period_month
 * @property string $run_number
 * @property string $run_type
 * @property Carbon $pay_date
 * @property Carbon $period_start
 * @property Carbon $period_end
 * @property int $staff_count
 * @property int $gross_minor
 * @property int $deductions_minor
 * @property int $net_minor
 * @property int $employer_cost_minor
 * @property array<string, mixed>|null $currency_totals
 * @property int $paye_minor
 * @property int $aids_levy_minor
 * @property int $nssa_employee_minor
 * @property int $nssa_employer_minor
 * @property int $apwcs_minor
 * @property int $zimdef_minor
 * @property int $nec_employee_minor
 * @property int $nec_employer_minor
 * @property string $status
 * @property array<int, mixed>|null $variance_report
 * @property array<int, mixed>|null $exception_report
 * @property int|null $approval_request_id
 * @property int $computed_by
 * @property int|null $approved_by
 * @property int|null $journal_id
 * @property int|null $bank_file_id
 */
class PayrollRun extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<PayrollRunFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    /**
     * @var array<int, string>
     */
    private const array IMMUTABLE_ALWAYS = [
        'school_id', 'academic_year_id', 'term_id', 'period_month', 'run_number', 'run_type',
        'pay_date', 'period_start', 'period_end', 'computed_by',
    ];

    /**
     * @var array<int, string>
     */
    private const array MUTABLE_AFTER_POSTED = ['status', 'bank_file_id'];

    protected $fillable = [
        'school_id', 'academic_year_id', 'term_id', 'period_month', 'run_number', 'run_type',
        'pay_date', 'period_start', 'period_end', 'staff_count', 'gross_minor', 'deductions_minor',
        'net_minor', 'employer_cost_minor', 'currency_totals', 'paye_minor', 'aids_levy_minor',
        'nssa_employee_minor', 'nssa_employer_minor', 'apwcs_minor', 'zimdef_minor',
        'nec_employee_minor', 'nec_employer_minor', 'status', 'variance_report', 'exception_report',
        'approval_request_id', 'computed_by', 'approved_by', 'journal_id', 'bank_file_id',
    ];

    protected function casts(): array
    {
        return [
            'pay_date' => 'date',
            'period_start' => 'date',
            'period_end' => 'date',
            'currency_totals' => 'array',
            'variance_report' => 'array',
            'exception_report' => 'array',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return PayrollRunFactory::new();
    }

    protected static function booted(): void
    {
        static::updating(function (Model $model): void {
            $dirty = array_keys($model->getDirty());

            if (array_intersect($dirty, self::IMMUTABLE_ALWAYS) !== []) {
                throw new InvalidStateTransitionException(
                    'A payroll run\'s identity and period columns are immutable once created.',
                    ['dirty' => array_intersect($dirty, self::IMMUTABLE_ALWAYS)],
                );
            }

            if ($model->getOriginal('status') === 'posted') {
                $notAllowed = array_diff($dirty, self::MUTABLE_AFTER_POSTED);

                if ($notAllowed !== []) {
                    throw new InvalidStateTransitionException(
                        'A posted payroll run cannot be edited (BR-PPL-05-016). Correct it with a reversal plus a supplementary run.',
                        ['dirty' => $notAllowed],
                    );
                }
            }
        });
    }

    /**
     * @return HasMany<Payslip, $this>
     */
    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class, 'payroll_run_id');
    }
}
