<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Database\Factories\RoleFactory;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Domain\Exceptions\InsufficientScopeException;
use Spatie\Permission\Contracts\Permission as PermissionContract;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Book A CORE-05 §2. `school_id` (spatie's "team" column, renamed) is
 * nullable — NULL marks a system template (BR-CORE-05-013); a school's
 * own roles, including clones of a template, carry that school's id.
 *
 * @property int $id
 * @property string $ulid
 * @property int|null $school_id
 * @property string $name
 * @property string $display_name
 * @property string|null $description
 * @property string $guard_name
 * @property bool $is_system
 * @property bool $is_vendor_only
 * @property string $category
 */
class Role extends SpatieRole
{
    /** @use HasFactory<RoleFactory> */
    use HasFactory;

    use HasUlid;

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_vendor_only' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return RoleFactory::new();
    }

    /**
     * @return HasMany<RolePermissionScope, $this>
     */
    public function permissionScopes(): HasMany
    {
        return $this->hasMany(RolePermissionScope::class);
    }

    /**
     * Book G BRD-08 §8 ⭐⭐ — "No permission in this list may be
     * assigned to a vendor role." A vendor-only role must never hold a
     * `safeguarding.*` permission — the vendor's own super-admin has no
     * implicit access to safeguarding data, and this is the one place
     * that guarantee can be enforced regardless of which screen or
     * seeder is doing the assigning.
     *
     * @param  array<int, mixed>|string|PermissionContract  ...$permissions
     */
    public function givePermissionTo(...$permissions): static
    {
        if ($this->is_vendor_only) {
            foreach (collect($permissions)->flatten() as $permission) {
                $name = match (true) {
                    is_string($permission) => $permission,
                    $permission instanceof PermissionContract => $permission->name,
                    default => null,
                };

                if ($name !== null && str_starts_with($name, 'safeguarding.')) {
                    throw new InsufficientScopeException(
                        "Vendor-only role [{$this->name}] cannot hold safeguarding permission [{$name}] (Book G BRD-08 §8).",
                        ['role_id' => $this->id, 'permission' => $name],
                    );
                }
            }
        }

        return parent::givePermissionTo(...$permissions);
    }
}
