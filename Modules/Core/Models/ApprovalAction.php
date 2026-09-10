<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Book A CORE-07 §2/BR-CORE-07-010. Append-only.
 *
 * @property int $id
 * @property int $request_id
 * @property int $step_number
 * @property string $action
 * @property int $actor_id
 * @property int|null $on_behalf_of_id
 * @property string|null $comment
 * @property string|null $ip_address
 * @property Carbon $acted_at
 */
class ApprovalAction extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'request_id', 'step_number', 'action', 'actor_id', 'on_behalf_of_id',
        'comment', 'ip_address', 'acted_at',
    ];

    protected function casts(): array
    {
        return [
            'acted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ApprovalRequest, $this>
     */
    public function request(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class, 'request_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function onBehalfOf(): BelongsTo
    {
        return $this->belongsTo(User::class, 'on_behalf_of_id');
    }

    public function isDelegated(): bool
    {
        return $this->on_behalf_of_id !== null;
    }
}
