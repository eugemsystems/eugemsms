<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Database\Factories\ApprovalDelegationFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;

/**
 * Book A CORE-07 §2/BR-CORE-07-009. `approvable_type` null covers
 * every type.
 *
 * @property int $id
 * @property int $school_id
 * @property int $delegator_id
 * @property int $delegate_id
 * @property string|null $approvable_type
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property string|null $reason
 * @property bool $is_active
 */
class ApprovalDelegation extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<ApprovalDelegationFactory> */
    use HasFactory;

    const UPDATED_AT = null;

    protected $fillable = [
        'school_id', 'delegator_id', 'delegate_id', 'approvable_type', 'starts_at',
        'ends_at', 'reason', 'is_active', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return ApprovalDelegationFactory::new();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function delegator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegator_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function delegate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegate_id');
    }

    public function isActiveAt(Carbon $moment): bool
    {
        return $this->is_active && $moment->betweenIncluded($this->starts_at, $this->ends_at);
    }

    public function coversType(string $approvableType): bool
    {
        return $this->approvable_type === null || $this->approvable_type === $approvableType;
    }
}
