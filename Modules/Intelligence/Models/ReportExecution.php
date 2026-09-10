<?php

declare(strict_types=1);

namespace Modules\Intelligence\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Intelligence\Database\Factories\ReportExecutionFactory;

/**
 * Book J INT-01 §2 ⭐/BR-INT-01-009. Append-only.
 *
 * @property int $id
 * @property int $school_id
 * @property int|null $report_id
 * @property int $executed_by
 * @property int|null $row_count
 * @property int $duration_ms
 * @property array<int, int>|null $schools_included
 * @property Carbon $executed_at
 */
class ReportExecution extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<ReportExecutionFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'report_id', 'executed_by', 'row_count', 'duration_ms', 'schools_included', 'executed_at',
    ];

    protected function casts(): array
    {
        return [
            'schools_included' => 'array',
            'executed_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return ReportExecutionFactory::new();
    }

    protected static function booted(): void
    {
        static::updating(function (Model $model): void {
            throw new InvalidStateTransitionException(
                'A report_executions row is append-only and may never be updated.',
                ['dirty' => array_keys($model->getDirty())],
            );
        });
    }

    /**
     * @return BelongsTo<CustomReport, $this>
     */
    public function report(): BelongsTo
    {
        return $this->belongsTo(CustomReport::class, 'report_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function executor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executed_by');
    }

    public function isConsolidated(): bool
    {
        return $this->schools_included !== null;
    }
}
