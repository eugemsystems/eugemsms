<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Core\Database\Factories\PermissionFactory;
use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * Book A CORE-05 §2/§9. `name` is the dotted permission string
 * (`academic.result.enter`); `module_code`/`resource`/`action` are its
 * decomposed parts, used by the role editor's permission-matrix grouping
 * and by CI to check every module registers the permissions it declares.
 *
 * @property int $id
 * @property string $name
 * @property string $module_code
 * @property string $resource
 * @property string $action
 * @property string|null $description
 * @property bool $is_dangerous
 * @property string $guard_name
 */
class Permission extends SpatiePermission
{
    /** @use HasFactory<PermissionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_dangerous' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return PermissionFactory::new();
    }
}
