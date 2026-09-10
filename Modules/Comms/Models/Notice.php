<?php

declare(strict_types=1);

namespace Modules\Comms\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Comms\Database\Factories\NoticeFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;

/**
 * Book I COM-06 §2/BR-COM-06-003/004/005.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property string $title
 * @property string $body
 * @property string $priority
 * @property string $audience_scope
 * @property int|null $audience_scope_id
 * @property bool $is_pinned
 * @property Carbon $publish_at
 * @property Carbon|null $expires_at
 * @property array<int, int>|null $attachment_file_ids
 * @property int $posted_by
 * @property string $status
 */
class Notice extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<NoticeFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'title', 'body', 'priority', 'audience_scope', 'audience_scope_id',
        'is_pinned', 'publish_at', 'expires_at', 'attachment_file_ids', 'posted_by', 'status',
    ];

    protected function casts(): array
    {
        return [
            'is_pinned' => 'boolean',
            'publish_at' => 'datetime',
            'expires_at' => 'datetime',
            'attachment_file_ids' => 'array',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return NoticeFactory::new();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    /**
     * @return HasMany<NoticeRead, $this>
     */
    public function reads(): HasMany
    {
        return $this->hasMany(NoticeRead::class);
    }

    public function tracksReadReceipts(): bool
    {
        return in_array($this->priority, ['important', 'urgent'], true);
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }
}
