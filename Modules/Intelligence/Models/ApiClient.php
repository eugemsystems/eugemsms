<?php

declare(strict_types=1);

namespace Modules\Intelligence\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Intelligence\Database\Factories\ApiClientFactory;

/**
 * Book J INT-04 §2/BR-INT-04-001/002/007. `api_key_hash` is the ONLY
 * form the key exists in once created — `IssueApiClientAction` returns
 * the plaintext once, at creation, and never stores it.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property string $name
 * @property string $client_type
 * @property string|null $contact_email
 * @property string $api_key_hash
 * @property array<int, string> $scoped_abilities
 * @property int $rate_limit_per_minute
 * @property array<int, string>|null $ip_allowlist
 * @property bool $is_active
 * @property Carbon|null $last_used_at
 * @property int|null $created_by
 * @property Carbon|null $revoked_at
 * @property int|null $revoked_by
 */
class ApiClient extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<ApiClientFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'name', 'client_type', 'contact_email', 'api_key_hash', 'scoped_abilities',
        'rate_limit_per_minute', 'ip_allowlist', 'is_active', 'last_used_at', 'created_by',
        'revoked_at', 'revoked_by',
    ];

    protected function casts(): array
    {
        return [
            'scoped_abilities' => 'array',
            'ip_allowlist' => 'array',
            'is_active' => 'boolean',
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return ApiClientFactory::new();
    }

    public function hasAbility(string $ability): bool
    {
        return in_array($ability, $this->scoped_abilities, true);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
