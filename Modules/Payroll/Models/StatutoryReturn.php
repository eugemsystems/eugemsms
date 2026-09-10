<?php

declare(strict_types=1);

namespace Modules\Payroll\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Payroll\Database\Factories\StatutoryReturnFactory;

/**
 * Book H3 PPL-05 §2/BR-PPL-05-020/022 ⭐.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property string $return_type
 * @property string $period_type
 * @property string $period_reference
 * @property Carbon $due_date
 * @property int $amount_due_minor
 * @property string $currency
 * @property array<string, mixed> $supporting_data
 * @property string $status
 * @property int|null $prepared_by
 * @property Carbon|null $prepared_at
 * @property string|null $submission_reference
 * @property Carbon|null $submitted_at
 */
class StatutoryReturn extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<StatutoryReturnFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'return_type', 'period_type', 'period_reference', 'due_date', 'amount_due_minor',
        'currency', 'supporting_data', 'export_file_id', 'status', 'prepared_by', 'prepared_at',
        'reviewed_by', 'submitted_at', 'submission_reference', 'paid_at', 'payment_reference',
        'journal_id',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'supporting_data' => 'array',
            'prepared_at' => 'datetime',
            'submitted_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return StatutoryReturnFactory::new();
    }
}
