<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Core\Database\Factories\NotificationFactory;
use Modules\Core\Domain\Concerns\HasUlid;

/**
 * Book A CORE-09 §2. Not `BelongsToSchool` — `DispatchNotificationAction`
 * always supplies `school_id` explicitly.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property string $notification_key
 * @property string $recipient_type
 * @property int|null $recipient_id
 * @property string $recipient_address
 * @property string $channel
 * @property string|null $subject
 * @property string $body
 * @property array<string, mixed>|null $context
 * @property string|null $related_type
 * @property int|null $related_id
 * @property string $status
 * @property string|null $provider
 * @property string|null $provider_message_id
 * @property int $attempt_count
 * @property int|null $cost_minor
 * @property string|null $cost_currency
 * @property string|null $error_code
 * @property string|null $error_message
 * @property Carbon|null $scheduled_for
 * @property Carbon|null $sent_at
 * @property Carbon|null $delivered_at
 * @property Carbon|null $read_at
 * @property Carbon|null $failed_at
 * @property string|null $dedupe_key
 * @property Carbon $created_at
 */
class Notification extends Model
{
    /** @use HasFactory<NotificationFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'notification_key', 'recipient_type', 'recipient_id',
        'recipient_address', 'channel', 'subject', 'body', 'context', 'related_type',
        'related_id', 'status', 'provider', 'provider_message_id', 'attempt_count',
        'cost_minor', 'cost_currency', 'error_code', 'error_message', 'scheduled_for',
        'sent_at', 'delivered_at', 'read_at', 'failed_at', 'dedupe_key', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'scheduled_for' => 'datetime',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
            'failed_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return NotificationFactory::new();
    }

    public function isUnread(): bool
    {
        return $this->channel === 'in_app' && $this->read_at === null;
    }
}
