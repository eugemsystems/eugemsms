<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Book A CORE-05 §2/§4. Single-use rotation chain — BR-CORE-05-009.
 *
 * @property int $id
 * @property int $user_id
 * @property int $access_token_id
 * @property string $token_hash
 * @property string $device_id
 * @property Carbon $expires_at
 * @property Carbon|null $used_at
 * @property int|null $replaced_by_id
 * @property Carbon|null $created_at
 */
class RefreshToken extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'access_token_id', 'token_hash', 'device_id', 'expires_at', 'used_at', 'replaced_by_id',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $token): void {
            $token->created_at ??= Carbon::now();
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<PersonalAccessToken, $this>
     */
    public function accessToken(): BelongsTo
    {
        return $this->belongsTo(PersonalAccessToken::class, 'access_token_id');
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function replacedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaced_by_id');
    }

    public function isUsed(): bool
    {
        return $this->used_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
