<?php

declare(strict_types=1);

namespace Modules\Stores\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Models\Term;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\CostCentre;
use Modules\Stores\Database\Factories\BudgetLineFactory;

/**
 * Book H1 FIN-11 §2/§3 ⭐/BR-FIN-11-001/007/008.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $budget_id
 * @property int $account_id
 * @property int $cost_centre_id
 * @property int|null $term_id
 * @property int $annual_amount_minor
 * @property int|null $term_1_minor
 * @property int|null $term_2_minor
 * @property int|null $term_3_minor
 * @property string $currency
 * @property int $committed_minor
 * @property int $actual_minor
 * @property int $available_minor
 * @property string|null $basis_note
 * @property bool $is_locked
 */
class BudgetLine extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<BudgetLineFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'budget_id', 'account_id', 'cost_centre_id', 'term_id', 'annual_amount_minor',
        'term_1_minor', 'term_2_minor', 'term_3_minor', 'currency', 'committed_minor', 'actual_minor',
        'available_minor', 'prior_year_actual_minor', 'basis_note', 'is_locked',
    ];

    protected function casts(): array
    {
        return [
            'is_locked' => 'boolean',
        ];
    }

    /**
     * BR-FIN-11-007 — recomputed on every commitment and journal
     * posting, never drifting silently between recalculations.
     */
    public function recomputeAvailable(): void
    {
        $this->available_minor = $this->annual_amount_minor - $this->committed_minor - $this->actual_minor;
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return BudgetLineFactory::new();
    }

    /**
     * @return BelongsTo<Budget, $this>
     */
    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * @return BelongsTo<CostCentre, $this>
     */
    public function costCentre(): BelongsTo
    {
        return $this->belongsTo(CostCentre::class);
    }

    /**
     * @return BelongsTo<Term, $this>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    /**
     * @return HasMany<BudgetCommitment, $this>
     */
    public function commitments(): HasMany
    {
        return $this->hasMany(BudgetCommitment::class);
    }
}
