<?php

declare(strict_types=1);

namespace Modules\Comms\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Comms\Database\Factories\MessagingGatewayWebhookFactory;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;

/**
 * Book I COM-01 §2/BR-COM-01-010 — APPEND-ONLY, exactly `FIN-05`'s
 * `GatewayWebhook` pattern. `school_id`/`gateway_id` nullable — a
 * webhook may arrive before either can be resolved.
 *
 * @property int $id
 * @property int|null $school_id
 * @property int|null $gateway_id
 * @property string $driver
 * @property string|null $event_type
 * @property array<string, mixed> $raw_headers
 * @property string $raw_payload
 * @property string $payload_hash
 * @property bool $signature_valid
 * @property int|null $notification_id
 * @property string $processing_status
 * @property string|null $processing_error
 * @property Carbon $received_at
 * @property Carbon|null $processed_at
 */
class MessagingGatewayWebhook extends Model
{
    /** @use HasFactory<MessagingGatewayWebhookFactory> */
    use HasFactory;

    public $timestamps = false;

    private const array MUTABLE_AFTER_CREATE = ['notification_id', 'event_type', 'processing_status', 'processing_error', 'processed_at'];

    protected $fillable = [
        'school_id', 'gateway_id', 'driver', 'event_type', 'raw_headers', 'raw_payload', 'payload_hash',
        'signature_valid', 'notification_id', 'processing_status', 'processing_error', 'received_at', 'processed_at',
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
        return MessagingGatewayWebhookFactory::new();
    }

    protected static function booted(): void
    {
        static::updating(function (Model $model): void {
            $dirty = array_keys($model->getDirty());
            $illegal = array_diff($dirty, self::MUTABLE_AFTER_CREATE);

            if ($illegal !== []) {
                throw new InvalidStateTransitionException(
                    'A messaging_gateway_webhooks row may only change notification_id, processing_status, processing_error, or processed_at after creation.',
                    ['dirty' => $illegal],
                );
            }
        });

        static::deleting(function (): void {
            throw new InvalidStateTransitionException('messaging_gateway_webhooks is append-only — a webhook receipt is never deleted.');
        });
    }
}
