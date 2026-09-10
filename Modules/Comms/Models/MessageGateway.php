<?php

declare(strict_types=1);

namespace Modules\Comms\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Comms\Database\Factories\MessageGatewayFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;

/**
 * Book I COM-01 §2/BR-COM-01-001. `credentials`/`webhook_secret` are
 * encrypted at rest — never plain in a DB dump, never logged, never
 * present in an export (enforced by simply never including them in
 * any Action's return DTO or a `toArray()`-derived response).
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property string $channel
 * @property string $driver
 * @property string $name
 * @property string $credentials
 * @property string|null $webhook_secret
 * @property bool $is_default
 * @property bool $is_sandbox
 * @property int $priority
 * @property Carbon|null $last_health_check_at
 * @property string|null $health_status
 * @property bool $is_active
 * @property int $created_by
 */
class MessageGateway extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<MessageGatewayFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'channel', 'driver', 'name', 'credentials', 'webhook_secret', 'is_default',
        'is_sandbox', 'priority', 'last_health_check_at', 'health_status', 'is_active', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted',
            'webhook_secret' => 'encrypted',
            'is_default' => 'boolean',
            'is_sandbox' => 'boolean',
            'last_health_check_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return MessageGatewayFactory::new();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isHealthy(): bool
    {
        return $this->health_status === null || $this->health_status !== 'down';
    }
}
