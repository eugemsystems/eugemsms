<?php

declare(strict_types=1);

namespace Modules\Comms\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Comms\Database\Factories\CalendarFeedTokenFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;

/**
 * Book I COM-06 §4/BR-COM-06-008 (AC-COM-06-005). See the owning
 * migration's docblock.
 *
 * @property int $id
 * @property int $school_id
 * @property string $token
 * @property int $user_id
 * @property string $audience_scope
 * @property int|null $audience_scope_id
 * @property Carbon|null $revoked_at
 */
class CalendarFeedToken extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<CalendarFeedTokenFactory> */
    use HasFactory;

    protected $fillable = [
        'school_id', 'token', 'user_id', 'audience_scope', 'audience_scope_id', 'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return CalendarFeedTokenFactory::new();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }
}
