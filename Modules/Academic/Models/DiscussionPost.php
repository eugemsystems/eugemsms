<?php

declare(strict_types=1);

namespace Modules\Academic\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Academic\Database\Factories\DiscussionPostFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;

/**
 * Book K ACA-08 §2/BR-ACA-08-010. A hidden post remains in the
 * database for audit but is invisible to learners — `is_hidden` is the
 * visibility switch, `hidden_reason`/`hidden_by`/`hidden_at` are the
 * audit trail (see the owning migration's docblock for the reason
 * column's spec deviation).
 *
 * @property int $id
 * @property int $school_id
 * @property int $thread_id
 * @property string $posted_by_type
 * @property int $posted_by_id
 * @property string $content
 * @property bool $is_hidden
 * @property int|null $hidden_by
 * @property string|null $hidden_reason
 * @property Carbon|null $hidden_at
 * @property Carbon $posted_at
 */
class DiscussionPost extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<DiscussionPostFactory> */
    use HasFactory;

    protected $fillable = [
        'school_id', 'thread_id', 'posted_by_type', 'posted_by_id', 'content', 'is_hidden',
        'hidden_by', 'hidden_reason', 'hidden_at', 'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'is_hidden' => 'boolean',
            'hidden_at' => 'datetime',
            'posted_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return DiscussionPostFactory::new();
    }

    /**
     * @return BelongsTo<DiscussionThread, $this>
     */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(DiscussionThread::class, 'thread_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function hiddenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hidden_by');
    }

    public function isVisibleToLearners(): bool
    {
        return ! $this->is_hidden;
    }
}
