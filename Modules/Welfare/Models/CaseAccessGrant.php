<?php

declare(strict_types=1);

namespace Modules\Welfare\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Welfare\Database\Factories\CaseAccessGrantFactory;

/**
 * Book G BRD-08 §2/§3 ⭐⭐/BR-BRD-08-002/003 — per-case, per-person.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $case_id
 * @property int $user_id
 * @property string $access_level
 * @property int $granted_by
 * @property Carbon $granted_at
 * @property string $reason
 * @property Carbon|null $expires_at
 * @property Carbon|null $revoked_at
 * @property int|null $revoked_by
 * @property string|null $revocation_reason
 */
class CaseAccessGrant extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<CaseAccessGrantFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'case_id', 'user_id', 'access_level', 'granted_by', 'granted_at', 'reason',
        'expires_at', 'revoked_at', 'revoked_by', 'revocation_reason',
    ];

    protected function casts(): array
    {
        return [
            'granted_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function isActive(): bool
    {
        if ($this->revoked_at !== null) {
            return false;
        }

        return $this->expires_at === null || $this->expires_at->isFuture();
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return CaseAccessGrantFactory::new();
    }

    /**
     * @return BelongsTo<SafeguardingCase, $this>
     */
    public function case(): BelongsTo
    {
        return $this->belongsTo(SafeguardingCase::class, 'case_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
