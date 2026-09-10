<?php

declare(strict_types=1);

namespace Modules\Intelligence\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Intelligence\Database\Factories\WebhookSubscriptionFactory;

/**
 * Book J INT-04 §2/BR-INT-04-004/005.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $client_id
 * @property array<int, string> $event_names
 * @property string $target_url
 * @property string $signing_secret
 * @property bool $is_active
 * @property int $consecutive_failures
 */
class WebhookSubscription extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<WebhookSubscriptionFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'client_id', 'event_names', 'target_url', 'signing_secret', 'is_active', 'consecutive_failures',
    ];

    protected $hidden = [
        'signing_secret',
    ];

    protected function casts(): array
    {
        return [
            'event_names' => 'array',
            'signing_secret' => 'encrypted',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return WebhookSubscriptionFactory::new();
    }

    public function subscribesTo(string $eventName): bool
    {
        return in_array($eventName, $this->event_names, true);
    }

    /**
     * @return BelongsTo<ApiClient, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(ApiClient::class, 'client_id');
    }
}
