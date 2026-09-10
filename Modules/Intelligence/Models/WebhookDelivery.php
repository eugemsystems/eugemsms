<?php

declare(strict_types=1);

namespace Modules\Intelligence\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Intelligence\Database\Factories\WebhookDeliveryFactory;

/**
 * Book J INT-04 §2 — append-only, exactly `Modules\Comms\Models\MessagingGatewayWebhook`'s
 * own pattern: a delivery record is never deleted, and only the fields
 * a retry attempt legitimately advances may change after creation.
 *
 * @property int $id
 * @property int $school_id
 * @property int $subscription_id
 * @property string $event_name
 * @property array<string, mixed> $payload
 * @property int $attempt_count
 * @property string $status
 * @property int|null $response_status
 * @property Carbon|null $last_attempted_at
 */
class WebhookDelivery extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<WebhookDeliveryFactory> */
    use HasFactory;

    private const array MUTABLE_AFTER_CREATE = ['attempt_count', 'status', 'response_status', 'last_attempted_at'];

    protected $fillable = [
        'school_id', 'subscription_id', 'event_name', 'payload', 'attempt_count',
        'status', 'response_status', 'last_attempted_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'last_attempted_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return WebhookDeliveryFactory::new();
    }

    protected static function booted(): void
    {
        static::updating(function (Model $model): void {
            $illegal = array_diff(array_keys($model->getDirty()), self::MUTABLE_AFTER_CREATE);

            if ($illegal !== []) {
                throw new InvalidStateTransitionException(
                    'A webhook_deliveries row may only change attempt_count, status, response_status, or last_attempted_at after creation.',
                    ['dirty' => $illegal],
                );
            }
        });

        static::deleting(function (): void {
            throw new InvalidStateTransitionException('webhook_deliveries is append-only — a delivery attempt record is never deleted.');
        });
    }

    /**
     * @return BelongsTo<WebhookSubscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(WebhookSubscription::class, 'subscription_id');
    }
}
