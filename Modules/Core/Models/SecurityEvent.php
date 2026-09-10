<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Database\Factories\SecurityEventFactory;

/**
 * Book A CORE-08 §2. `school_id` nullable — a security event can be
 * platform-wide (e.g. a cross-tenant scope-bypass attempt).
 *
 * @property int $id
 * @property int|null $school_id
 * @property string $event_type
 * @property string $severity
 * @property int|null $user_id
 * @property string $description
 * @property array<string, mixed>|null $context
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property bool $is_reviewed
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property string|null $review_notes
 * @property Carbon $occurred_at
 */
class SecurityEvent extends Model
{
    /** @use HasFactory<SecurityEventFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'event_type', 'severity', 'user_id', 'description', 'context',
        'ip_address', 'user_agent', 'is_reviewed', 'reviewed_by', 'reviewed_at',
        'review_notes', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'is_reviewed' => 'boolean',
            'reviewed_at' => 'datetime',
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return SecurityEventFactory::new();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isCritical(): bool
    {
        return $this->severity === 'critical';
    }
}
