<?php

declare(strict_types=1);

namespace Modules\Stores\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Domain\Support\PeriodGuard;
use Modules\Finance\Models\Journal;
use Modules\Stores\Database\Factories\DepreciationRunFactory;

/**
 * Book H1 FIN-10 §2/BR-FIN-10-004/005/007 — `UNIQUE(school_id, period_month)`
 * is the real guarantee behind "a posted period never runs twice";
 * `PeriodGuard` (called manually, matching every other financial model
 * in this module) is what refuses posting into a locked period.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $academic_year_id
 * @property int $term_id
 * @property string $period_month
 * @property Carbon $run_date
 * @property int $asset_count
 * @property int $total_depreciation_minor
 * @property string $currency
 * @property string $status
 * @property int|null $journal_id
 * @property int $computed_by
 * @property int|null $approved_by
 */
class DepreciationRun extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<DepreciationRunFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected static function booted(): void
    {
        static::updating(function (self $model): void {
            if ($model->isDirty('status') && $model->status === 'posted') {
                PeriodGuard::assertWritable($model);
            }
        });
    }

    protected $fillable = [
        'school_id', 'academic_year_id', 'term_id', 'period_month', 'run_date', 'asset_count',
        'total_depreciation_minor', 'currency', 'status', 'journal_id', 'computed_by', 'approved_by',
        'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'run_date' => 'date',
            'posted_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return DepreciationRunFactory::new();
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
    public function computedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'computed_by');
    }

    /**
     * @return HasMany<DepreciationEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(DepreciationEntry::class, 'run_id');
    }
}
