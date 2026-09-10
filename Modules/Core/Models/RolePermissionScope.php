<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Domain\Support\Auth\PermissionScope;

/**
 * Book A CORE-05 §2. One row per (role, permission) grant, carrying the
 * scope that grant narrows to — own/assigned/section/school.
 *
 * @property int $id
 * @property int $role_id
 * @property int $permission_id
 * @property PermissionScope $scope
 */
class RolePermissionScope extends Model
{
    public $timestamps = false;

    protected $fillable = ['role_id', 'permission_id', 'scope'];

    protected function casts(): array
    {
        return [
            'scope' => PermissionScope::class,
        ];
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * @return BelongsTo<Permission, $this>
     */
    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class);
    }
}
