<?php

declare(strict_types=1);

namespace Modules\Stores\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Stores\Database\Factories\BudgetVirementFactory;

/**
 * Book H1 FIN-11 §2/BR-FIN-11-010/011.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $budget_id
 * @property int $from_line_id
 * @property int $to_line_id
 * @property int $amount_minor
 * @property string $currency
 * @property string $reason
 * @property string $status
 * @property int $requested_by
 * @property int|null $approved_by
 * @property Carbon $effective_from
 */
class BudgetVirement extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<BudgetVirementFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'budget_id', 'from_line_id', 'to_line_id', 'amount_minor', 'currency', 'reason',
        'status', 'approval_request_id', 'requested_by', 'approved_by', 'effective_from',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return BudgetVirementFactory::new();
    }

    /**
     * @return BelongsTo<Budget, $this>
     */
    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    /**
     * @return BelongsTo<BudgetLine, $this>
     */
    public function fromLine(): BelongsTo
    {
        return $this->belongsTo(BudgetLine::class, 'from_line_id');
    }

    /**
     * @return BelongsTo<BudgetLine, $this>
     */
    public function toLine(): BelongsTo
    {
        return $this->belongsTo(BudgetLine::class, 'to_line_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
