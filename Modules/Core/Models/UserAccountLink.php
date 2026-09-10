<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Database\Factories\UserAccountLinkFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;

/**
 * Book A CORE-05 §2. Parent↔learner, staff↔teacher identity link.
 * `linked_type`/`linked_id` point at records owned by modules that don't
 * exist yet (`students`, `guardians`, `staff`), so this is a lightweight
 * pair of columns rather than a real `MorphTo`.
 *
 * @property int $id
 * @property int $school_id
 * @property int $user_id
 * @property string $linked_type
 * @property int $linked_id
 * @property bool $is_active
 */
class UserAccountLink extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<UserAccountLinkFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['school_id', 'user_id', 'linked_type', 'linked_id', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return UserAccountLinkFactory::new();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
