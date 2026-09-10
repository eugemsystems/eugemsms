<?php

declare(strict_types=1);

namespace Modules\Stores\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Stores\Database\Factories\BudgetCommitmentFactory;

/**
 * Book H1 FIN-11 §2/§3 ⭐ — the control itself.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $budget_line_id
 * @property string $source_type
 * @property int $source_id
 * @property int $committed_minor
 * @property int $released_minor
 * @property int $outstanding_minor
 * @property string $currency
 * @property Carbon $committed_at
 * @property Carbon|null $released_at
 * @property string $status
 */
class BudgetCommitment extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<BudgetCommitmentFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'budget_line_id', 'source_type', 'source_id', 'committed_minor', 'released_minor',
        'outstanding_minor', 'currency', 'committed_at', 'released_at', 'status',
    ];

    protected function casts(): array
    {
        return [
            'committed_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return BudgetCommitmentFactory::new();
    }

    /**
     * @return BelongsTo<BudgetLine, $this>
     */
    public function budgetLine(): BelongsTo
    {
        return $this->belongsTo(BudgetLine::class);
    }
}
