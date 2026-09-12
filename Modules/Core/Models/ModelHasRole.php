<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Read-only Eloquent mapping onto spatie/laravel-permission's own
 * `model_has_roles` pivot table (composite key: role_id, model_id,
 * model_type, school_id — the `school_id` column is this app's "team"
 * extension, Book A CORE-05 §2). spatie ships no Eloquent model for this
 * table itself — `HasRoles::assignRole()`/`removeRole()` write to it via
 * the relation's own query builder — but Book A Part 1.1's Action-pattern
 * CI gate (`ActionPatternEnforcementTest`) forbids `DB::table(` outright
 * from anything under `Livewire/`, so `Core\Permissions\Explorer` reads
 * a role's holder count in a school through this model instead.
 *
 * Never write through this model: every insert/delete against this table
 * belongs to spatie's own `assignRole()`/`removeRole()` calls
 * (`AssignRoleAction`/`RevokeRoleAction`) — BR-GLOBAL-005 still applies.
 *
 * @property int $role_id
 * @property string $model_type
 * @property int $model_id
 * @property int $school_id
 */
class ModelHasRole extends Model
{
    protected $table = 'model_has_roles';

    public $timestamps = false;

    protected $primaryKey = 'role_id';

    public $incrementing = false;
}
