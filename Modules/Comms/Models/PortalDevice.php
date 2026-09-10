<?php

declare(strict_types=1);

namespace Modules\Comms\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Comms\Database\Factories\PortalDeviceFactory;
use Modules\Core\Domain\Concerns\HasUlid;

/**
 * Book I COM-03 §2/BR-COM-03-005/006/007.
 *
 * @property int $id
 * @property string $ulid
 * @property int $user_id
 * @property string $device_id
 * @property string $platform
 * @property string|null $push_token
 * @property Carbon|null $push_token_updated_at
 * @property string|null $app_version
 * @property string|null $os_version
 * @property Carbon|null $last_active_at
 * @property bool $requires_biometric_lock
 * @property string|null $app_pin_hash
 * @property bool $is_active
 * @property Carbon|null $revoked_at
 */
class PortalDevice extends Model
{
    /** @use HasFactory<PortalDeviceFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'user_id', 'device_id', 'platform', 'push_token', 'push_token_updated_at', 'app_version',
        'os_version', 'last_active_at', 'requires_biometric_lock', 'app_pin_hash', 'is_active', 'revoked_at',
    ];

    protected $hidden = ['app_pin_hash'];

    protected function casts(): array
    {
        return [
            'push_token_updated_at' => 'datetime',
            'last_active_at' => 'datetime',
            'requires_biometric_lock' => 'boolean',
            'app_pin_hash' => 'hashed',
            'is_active' => 'boolean',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return PortalDeviceFactory::new();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hasAppLock(): bool
    {
        return $this->requires_biometric_lock || $this->app_pin_hash !== null;
    }
}
