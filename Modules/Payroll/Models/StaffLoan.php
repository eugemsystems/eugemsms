<?php

declare(strict_types=1);

namespace Modules\Payroll\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Payroll\Database\Factories\StaffLoanFactory;

/**
 * Book H3 PPL-05 §2/BR-PPL-05-019 — never over-recovered.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $staff_id
 * @property string $loan_type
 * @property int $principal_minor
 * @property string $currency
 * @property string $interest_rate_percent
 * @property int $instalment_minor
 * @property int $instalment_count
 * @property Carbon $starts_on
 * @property int $outstanding_minor
 * @property int $paid_minor
 * @property array<int, int>|null $offset_student_ids
 * @property string $status
 */
class StaffLoan extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<StaffLoanFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'staff_id', 'loan_type', 'principal_minor', 'currency', 'interest_rate_percent',
        'instalment_minor', 'instalment_count', 'starts_on', 'outstanding_minor', 'paid_minor',
        'offset_student_ids', 'approval_request_id', 'approved_by', 'status',
    ];

    protected function casts(): array
    {
        return [
            'interest_rate_percent' => 'decimal:2',
            'starts_on' => 'date',
            'offset_student_ids' => 'array',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return StaffLoanFactory::new();
    }

    public function isFullyRecovered(): bool
    {
        return $this->outstanding_minor <= 0;
    }
}
