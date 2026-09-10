<?php

declare(strict_types=1);

namespace Modules\Operations\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Operations\Database\Factories\MaintenanceScheduleFactory;

/**
 * Book H2 OPS-02 §2/BR-OPS-02-009/010.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $maintenance_asset_id
 * @property string $name
 * @property string $trigger_type
 * @property int|null $interval_days
 * @property float|null $interval_units
 * @property int $lead_time_days
 * @property array<int, mixed> $task_checklist
 * @property float|null $estimated_hours
 * @property string $assigned_team
 * @property Carbon|null $next_due_on
 * @property float|null $next_due_units
 * @property int|null $last_generated_wo_id
 * @property bool $is_active
 */
class MaintenanceSchedule extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<MaintenanceScheduleFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'maintenance_asset_id', 'name', 'trigger_type', 'interval_days', 'interval_units',
        'lead_time_days', 'task_checklist', 'estimated_hours', 'estimated_parts', 'assigned_team',
        'next_due_on', 'next_due_units', 'last_generated_wo_id', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'interval_units' => 'decimal:2',
            'task_checklist' => 'array',
            'estimated_hours' => 'decimal:2',
            'estimated_parts' => 'array',
            'next_due_on' => 'date',
            'next_due_units' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return MaintenanceScheduleFactory::new();
    }

    /**
     * @return BelongsTo<MaintenanceAsset, $this>
     */
    public function maintenanceAsset(): BelongsTo
    {
        return $this->belongsTo(MaintenanceAsset::class);
    }
}
