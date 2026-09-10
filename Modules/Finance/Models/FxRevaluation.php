<?php

declare(strict_types=1);

namespace Modules\Finance\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Models\Term;
use Modules\Finance\Database\Factories\FxRevaluationFactory;

/**
 * Book B FIN-06 §2/BR-FIN-06-012. Reversible while its period stays
 * open, and only by a full journal reversal — never by deletion.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $term_id
 * @property string $revaluation_date
 * @property int|null $closing_rate_id
 * @property array<int, array<string, mixed>> $accounts_revalued
 * @property int $gain_minor
 * @property int $loss_minor
 * @property string $base_currency
 * @property int|null $journal_id
 * @property string $status
 * @property int $performed_by
 */
class FxRevaluation extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<FxRevaluationFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'term_id', 'revaluation_date', 'closing_rate_id', 'accounts_revalued',
        'gain_minor', 'loss_minor', 'base_currency', 'journal_id', 'status', 'performed_by',
        'performed_at',
    ];

    protected function casts(): array
    {
        return [
            'revaluation_date' => 'date',
            'accounts_revalued' => 'array',
            'performed_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return FxRevaluationFactory::new();
    }

    /**
     * @return BelongsTo<Term, $this>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    /**
     * @return BelongsTo<Journal, $this>
     */
    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function netMinor(): int
    {
        return $this->gain_minor - $this->loss_minor;
    }
}
