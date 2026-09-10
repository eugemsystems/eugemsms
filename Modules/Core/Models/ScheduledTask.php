<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Database\Factories\ScheduledTaskFactory;

/**
 * Book A CORE-12 §2. Mirrors `ScheduledTaskRegistry`.
 *
 * @property int $id
 * @property string $key
 * @property string $module_code
 * @property string $name
 * @property string|null $description
 * @property string $command
 * @property string $schedule_expression
 * @property bool $is_enabled
 * @property bool $is_per_school
 * @property int $timeout_seconds
 * @property bool $alert_on_failure
 * @property int|null $alert_if_not_run_within_minutes
 */
class ScheduledTask extends Model
{
    /** @use HasFactory<ScheduledTaskFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'key', 'module_code', 'name', 'description', 'command', 'schedule_expression',
        'is_enabled', 'is_per_school', 'timeout_seconds', 'alert_on_failure',
        'alert_if_not_run_within_minutes',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'is_per_school' => 'boolean',
            'alert_on_failure' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return ScheduledTaskFactory::new();
    }

    /**
     * @return HasMany<ScheduledTaskRun, $this>
     */
    public function runs(): HasMany
    {
        return $this->hasMany(ScheduledTaskRun::class, 'task_id');
    }
}
