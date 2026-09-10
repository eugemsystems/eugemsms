<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Book A CORE-05 §2/BR-CORE-05-007. Append-only — no update/delete path
 * is ever exposed by an Action.
 *
 * @property int $id
 * @property string $identifier
 * @property int|null $user_id
 * @property string $guard
 * @property bool $was_successful
 * @property string|null $failure_reason
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property Carbon $attempted_at
 */
class LoginAttempt extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'identifier', 'user_id', 'guard', 'was_successful', 'failure_reason', 'ip_address', 'user_agent', 'attempted_at',
    ];

    protected function casts(): array
    {
        return [
            'was_successful' => 'boolean',
            'attempted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
