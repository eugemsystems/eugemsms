<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

/**
 * Book A CORE-05 §2. Sanctum's token model, extended with the device and
 * school-binding columns the spec's schema adds — bound via
 * `Sanctum::usePersonalAccessTokenModel()` in `CoreServiceProvider`.
 *
 * @property int $id
 * @property string $tokenable_type
 * @property int $tokenable_id
 * @property string $name
 * @property string $token
 * @property array<int, string>|null $abilities
 * @property int|null $school_id
 * @property string|null $device_id
 * @property string|null $device_platform
 * @property string|null $device_model
 * @property string|null $app_version
 * @property Carbon|null $last_used_at
 * @property string|null $last_used_ip
 * @property Carbon|null $expires_at
 * @property Carbon|null $revoked_at
 * @property int|null $revoked_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class PersonalAccessToken extends SanctumPersonalAccessToken
{
    protected $fillable = [
        'name', 'token', 'abilities', 'school_id', 'device_id', 'device_platform',
        'device_model', 'app_version', 'last_used_at', 'last_used_ip', 'expires_at',
        'revoked_at', 'revoked_by',
    ];

    protected function casts(): array
    {
        return [
            'abilities' => 'json',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }
}
