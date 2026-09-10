<?php

declare(strict_types=1);

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Finance\Database\Factories\ReconciliationRunFactory;

/**
 * Book B FIN-05 §5 ⭐/BR-FIN-05-013. Never auto-resolves — `exceptions`
 * is the list a human works through; clearing one is recorded
 * elsewhere (against the exception's own record), never by editing
 * this array away.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property Carbon $run_date
 * @property string $scope
 * @property int|null $gateway_id
 * @property int|null $bank_account_id
 * @property int|null $gateway_total_minor
 * @property int|null $receipts_total_minor
 * @property int|null $bank_total_minor
 * @property int|null $gl_total_minor
 * @property string $currency
 * @property int $variance_minor
 * @property int $exception_count
 * @property array<int, array<string, mixed>>|null $exceptions
 * @property string $status
 * @property Carbon $ran_at
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 */
class ReconciliationRun extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<ReconciliationRunFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'run_date', 'scope', 'gateway_id', 'bank_account_id',
        'gateway_total_minor', 'receipts_total_minor', 'bank_total_minor', 'gl_total_minor',
        'currency', 'variance_minor', 'exception_count', 'exceptions', 'status', 'ran_at',
        'reviewed_by', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'run_date' => 'date',
            'variance_minor' => 'integer',
            'exception_count' => 'integer',
            'exceptions' => 'array',
            'ran_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return ReconciliationRunFactory::new();
    }

    public function hasNoExceptions(): bool
    {
        return $this->exception_count === 0;
    }
}
