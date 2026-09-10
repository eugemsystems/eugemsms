<?php

declare(strict_types=1);

namespace Modules\Reporting\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Models\Term;
use Modules\Reporting\Database\Factories\PeriodCloseChecklistFactory;

/**
 * Book H3 FIN-12 §2/§4 ⭐/BR-FIN-12-009/010/011.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $term_id
 * @property string $period_type
 * @property Carbon $run_at
 * @property int $run_by
 * @property string $overall_status
 * @property int $blocking_failures
 * @property int $warnings
 * @property array<int, array<string, mixed>> $results
 * @property int|null $report_document_id
 */
class PeriodCloseChecklist extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<PeriodCloseChecklistFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'term_id', 'period_type', 'run_at', 'run_by', 'overall_status',
        'blocking_failures', 'warnings', 'results', 'report_document_id',
    ];

    protected function casts(): array
    {
        return [
            'run_at' => 'datetime',
            'results' => 'array',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return PeriodCloseChecklistFactory::new();
    }

    /**
     * @return BelongsTo<Term, $this>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function runBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'run_by');
    }

    /**
     * @return HasMany<CloseCheckAcknowledgement, $this>
     */
    public function acknowledgements(): HasMany
    {
        return $this->hasMany(CloseCheckAcknowledgement::class, 'checklist_id');
    }
}
