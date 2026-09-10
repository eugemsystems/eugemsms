<?php

declare(strict_types=1);

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Finance\Database\Factories\GatewayWebhookFactory;

/**
 * Book B FIN-05 §3/BR-FIN-05-004/005. Raw, append-only — when a
 * gateway disputes what it sent, this row settles it. No `school_id`
 * scope guard is required for isolation registration since a webhook
 * may legitimately arrive before the school/gateway can be resolved
 * (`school_id` nullable) — see `IngestGatewayWebhookAction`.
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
 * @property int|null $intent_id
 * @property string $processing_status
 * @property string|null $processing_error
 * @property Carbon $received_at
 * @property Carbon|null $processed_at
 */
class GatewayWebhook extends Model
{
    /** @use HasFactory<GatewayWebhookFactory> */
    use HasFactory;

    public $timestamps = false;

    private const array MUTABLE_AFTER_CREATE = ['intent_id', 'processing_status', 'processing_error', 'processed_at'];

    protected $fillable = [
        'school_id', 'gateway_id', 'driver', 'event_type', 'raw_headers', 'raw_payload',
        'payload_hash', 'signature_valid', 'intent_id', 'processing_status',
        'processing_error', 'received_at', 'processed_at',
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
        return GatewayWebhookFactory::new();
    }

    protected static function booted(): void
    {
        static::updating(function (Model $model): void {
            $dirty = array_keys($model->getDirty());
            $illegal = array_diff($dirty, self::MUTABLE_AFTER_CREATE);

            if ($illegal !== []) {
                throw new InvalidStateTransitionException(
                    'gateway_webhooks is append-only — only intent_id, processing_status, processing_error, and processed_at may change (BR-FIN-05-005).',
                    ['dirty' => $illegal],
                );
            }
        });

        static::deleting(function (): void {
            throw new InvalidStateTransitionException('gateway_webhooks rows are never deleted.', []);
        });
    }

    /**
     * @return BelongsTo<PaymentIntent, $this>
     */
    public function intent(): BelongsTo
    {
        return $this->belongsTo(PaymentIntent::class);
    }
}
