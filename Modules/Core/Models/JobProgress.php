<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Database\Factories\JobProgressFactory;
use Modules\Core\Domain\Concerns\HasUlid;

/**
 * Book A CORE-12 §2/BR-CORE-12-005.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $user_id
 * @property string $job_type
 * @property string $title
 * @property string $status
 * @property int|null $total_steps
 * @property int $completed_steps
 * @property string|null $current_message
 * @property array<string, mixed>|null $result
 * @property string|null $error
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 */
class JobProgress extends Model
{
    /** @use HasFactory<JobProgressFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'user_id', 'job_type', 'title', 'status', 'total_steps',
        'completed_steps', 'current_message', 'result', 'error', 'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'result' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return JobProgressFactory::new();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function percentComplete(): ?float
    {
        if ($this->total_steps === null || $this->total_steps === 0) {
            return null;
        }

        return round(($this->completed_steps / $this->total_steps) * 100, 1);
    }
}
