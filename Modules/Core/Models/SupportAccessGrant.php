<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Database\Factories\SupportAccessGrantFactory;

/**
 * A customer administrator's time-boxed, revocable consent for vendor staff to open a read-only
 * support session as one of the tenant's users, for one named support ticket.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $granted_by
 * @property string $ticket_reference
 * @property string $reason
 * @property Carbon $expires_at
 * @property Carbon|null $revoked_at
 * @property int|null $revoked_by
 * @property Carbon $created_at
 */
class SupportAccessGrant extends Model
{
    /** @use HasFactory<SupportAccessGrantFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = ['tenant_id', 'granted_by', 'ticket_reference', 'reason', 'expires_at', 'revoked_at', 'revoked_by'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return SupportAccessGrantFactory::new();
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null && $this->expires_at->isFuture();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }
}
