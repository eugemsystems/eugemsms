<?php

declare(strict_types=1);

namespace Modules\Compliance\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Compliance\Database\Factories\DisposalQueueItemFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;

/**
 * Book H3 CMP-03 §2/BR-CMP-03-006 ⭐. `protected $table` because
 * Eloquent would otherwise guess `disposal_queue_items` from the
 * class name — the spec's own table is `disposal_queue`.
 *
 * @property int $id
 * @property int $school_id
 * @property int $schedule_id
 * @property string $record_type
 * @property int $record_id
 * @property Carbon $eligible_on
 * @property string $review_status
 * @property Carbon|null $deferred_until
 * @property string|null $deferral_reason
 * @property int|null $reviewed_by
 * @property Carbon|null $disposed_at
 * @property string|null $disposal_method
 */
class DisposalQueueItem extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<DisposalQueueItemFactory> */
    use HasFactory;

    protected $table = 'disposal_queue';

    protected $fillable = [
        'school_id', 'schedule_id', 'record_type', 'record_id', 'eligible_on', 'review_status',
        'deferred_until', 'deferral_reason', 'reviewed_by', 'disposed_at', 'disposal_method',
    ];

    protected function casts(): array
    {
        return [
            'eligible_on' => 'date',
            'deferred_until' => 'date',
            'disposed_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return DisposalQueueItemFactory::new();
    }

    /**
     * @return BelongsTo<RetentionSchedule, $this>
     */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(RetentionSchedule::class, 'schedule_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
