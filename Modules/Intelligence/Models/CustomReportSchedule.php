<?php

declare(strict_types=1);

namespace Modules\Intelligence\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Intelligence\Database\Factories\CustomReportScheduleFactory;

/**
 * Book J INT-01 §2/BR-INT-01-010. See the owning migration's docblock
 * for the rename from the spec's literal `report_schedules`.
 *
 * @property int $id
 * @property int $school_id
 * @property int $report_id
 * @property string $frequency
 * @property array<int, array<string, mixed>> $recipients
 * @property string $format
 * @property Carbon|null $next_run_at
 * @property bool $is_active
 */
class CustomReportSchedule extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<CustomReportScheduleFactory> */
    use HasFactory;

    protected $fillable = [
        'school_id', 'report_id', 'frequency', 'recipients', 'format', 'next_run_at', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'recipients' => 'array',
            'next_run_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return CustomReportScheduleFactory::new();
    }

    /**
     * @return BelongsTo<CustomReport, $this>
     */
    public function report(): BelongsTo
    {
        return $this->belongsTo(CustomReport::class, 'report_id');
    }
}
