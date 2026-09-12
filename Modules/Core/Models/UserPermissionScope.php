<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Domain\Support\Auth\PermissionScope;

/**
 * Book A CORE-05 §2 extension — one row per (user, permission, school)
 * direct grant, carrying the scope it narrows to. The direct grant
 * itself lives in spatie's own `model_has_permissions` (written by
 * `$user->givePermissionTo()`); this table only adds the scope
 * dimension spatie has no column for — same split as `Role`/
 * `RolePermissionScope`.
 *
 * @property int $id
 * @property int $user_id
 * @property int $permission_id
 * @property int $school_id
 * @property PermissionScope $scope
 */
class UserPermissionScope extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'permission_id', 'school_id', 'scope'];

    protected function casts(): array
    {
        return [
            'scope' => PermissionScope::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Permission, $this>
     */
    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class);
    }

    /**
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
