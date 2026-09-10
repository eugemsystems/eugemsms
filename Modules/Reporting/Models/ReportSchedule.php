<?php

declare(strict_types=1);

namespace Modules\Reporting\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Reporting\Database\Factories\ReportScheduleFactory;

/**
 * Book H3 FIN-12 §2.
 *
 * @property int $id
 * @property int $school_id
 * @property int $report_definition_id
 * @property string $name
 * @property string $frequency
 * @property array<string, mixed>|null $parameters
 * @property array<int, mixed> $recipients
 * @property string $format
 * @property Carbon|null $next_run_at
 * @property bool $is_active
 */
class ReportSchedule extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<ReportScheduleFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'report_definition_id', 'name', 'frequency', 'parameters', 'recipients',
        'format', 'next_run_at', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'parameters' => 'array',
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
        return ReportScheduleFactory::new();
    }

    /**
     * @return BelongsTo<ReportDefinition, $this>
     */
    public function reportDefinition(): BelongsTo
    {
        return $this->belongsTo(ReportDefinition::class);
    }
}
