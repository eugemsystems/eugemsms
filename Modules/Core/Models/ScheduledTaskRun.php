<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Database\Factories\ScheduledTaskRunFactory;

/**
 * Book A CORE-12 §2.
 *
 * @property int $id
 * @property int $task_id
 * @property int|null $school_id
 * @property string $status
 * @property Carbon $started_at
 * @property Carbon|null $completed_at
 * @property int|null $duration_ms
 * @property string|null $output
 * @property string|null $error
 */
class ScheduledTaskRun extends Model
{
    /** @use HasFactory<ScheduledTaskRunFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'task_id', 'school_id', 'status', 'started_at', 'completed_at', 'duration_ms',
        'output', 'error',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return ScheduledTaskRunFactory::new();
    }

    /**
     * @return BelongsTo<ScheduledTask, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(ScheduledTask::class, 'task_id');
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, ['completed', 'failed', 'timed_out'], true);
    }
}
