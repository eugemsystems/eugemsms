<?php

declare(strict_types=1);

namespace Modules\Finance\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Domain\Support\PeriodGuard;
use Modules\Finance\Database\Factories\TillSessionFactory;

/**
 * Book B FIN-04 §2/§3 ⭐. `declared_closing` is entered blind — the
 * cashier never sees `expected_closing` before submitting it, and
 * that ordering lives entirely in `DeclareTillCountAction`/
 * `RevealAndCloseTillSessionAction`, not in this model.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $academic_year_id
 * @property int $term_id
 * @property int $till_id
 * @property int $cashier_id
 * @property string $session_number
 * @property Carbon $opened_at
 * @property Carbon|null $closed_at
 * @property array<string, int> $opening_float
 * @property array<string, int>|null $declared_closing
 * @property array<string, int>|null $expected_closing
 * @property array<string, int>|null $variance
 * @property string|null $variance_reason
 * @property string $status
 * @property int $receipt_count
 * @property array<string, mixed>|null $totals_by_tender
 * @property array<string, int>|null $totals_by_currency
 * @property int|null $supervised_by
 * @property int|null $journal_id
 */
class TillSession extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<TillSessionFactory> */
    use HasFactory;

    use HasUlid;

    private const array IMMUTABLE_AFTER_CREATE = [
        'school_id', 'academic_year_id', 'term_id', 'till_id', 'cashier_id',
        'session_number', 'opened_at', 'opening_float',
    ];

    protected $fillable = [
        'school_id', 'academic_year_id', 'term_id', 'till_id', 'cashier_id', 'session_number',
        'opened_at', 'closed_at', 'opening_float', 'declared_closing', 'expected_closing',
        'variance', 'variance_reason', 'status', 'receipt_count', 'totals_by_tender',
        'totals_by_currency', 'supervised_by', 'journal_id', 'banking_sheet_doc_id',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'opening_float' => 'array',
            'declared_closing' => 'array',
            'expected_closing' => 'array',
            'variance' => 'array',
            'receipt_count' => 'integer',
            'totals_by_tender' => 'array',
            'totals_by_currency' => 'array',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return TillSessionFactory::new();
    }

    protected static function booted(): void
    {
        static::creating(function (Model $model): void {
            PeriodGuard::assertWritable($model);
        });

        static::updating(function (Model $model): void {
            $dirty = array_keys($model->getDirty());
            $illegal = array_intersect($dirty, self::IMMUTABLE_AFTER_CREATE);

            if ($illegal !== []) {
                throw new InvalidStateTransitionException(
                    'A till session may not change its school, session/scope, till, cashier, session_number, opened_at, or opening_float after creation.',
                    ['dirty' => $illegal],
                );
            }
        });
    }

    /**
     * @return BelongsTo<Till, $this>
     */
    public function till(): BelongsTo
    {
        return $this->belongsTo(Till::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }
}
