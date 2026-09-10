<?php

declare(strict_types=1);

namespace Modules\Comms\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Comms\Database\Factories\MeetingWebhookEventFactory;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;

/**
 * Book I COM-07 §2/BR-COM-07-006 — the exact append-only shape
 * already established for `Modules\Comms\Models\MessagingGatewayWebhook`
 * (COM-01) and `Modules\Finance\Models\GatewayWebhook` (FIN-05).
 *
 * @property int $id
 * @property int|null $school_id
 * @property string $provider
 * @property string|null $event_type
 * @property array<string, mixed> $raw_headers
 * @property string $raw_payload
 * @property string $payload_hash
 * @property bool $signature_valid
 * @property int|null $meeting_id
 * @property string $processing_status
 * @property string|null $processing_error
 * @property Carbon $received_at
 * @property Carbon|null $processed_at
 */
class MeetingWebhookEvent extends Model
{
    /** @use HasFactory<MeetingWebhookEventFactory> */
    use HasFactory;

    public $timestamps = false;

    private const array MUTABLE_AFTER_CREATE = ['meeting_id', 'event_type', 'processing_status', 'processing_error', 'processed_at'];

    protected $fillable = [
        'school_id', 'provider', 'event_type', 'raw_headers', 'raw_payload', 'payload_hash',
        'signature_valid', 'meeting_id', 'processing_status', 'processing_error', 'received_at', 'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'raw_headers' => 'array',
            'signature_valid' => 'boolean',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return MeetingWebhookEventFactory::new();
    }

    protected static function booted(): void
    {
        static::updating(function (Model $model): void {
            $dirty = array_keys($model->getDirty());
            $illegal = array_diff($dirty, self::MUTABLE_AFTER_CREATE);

            if ($illegal !== []) {
                throw new InvalidStateTransitionException(
                    'A meeting_webhook_events row may only change meeting_id, event_type, processing_status, processing_error, or processed_at after creation.',
                    ['dirty' => $illegal],
                );
            }
        });

        static::deleting(function (): void {
            throw new InvalidStateTransitionException('meeting_webhook_events is append-only — a webhook receipt is never deleted.', []);
        });
    }
}
