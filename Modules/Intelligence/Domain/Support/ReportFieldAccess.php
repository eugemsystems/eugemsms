<?php

declare(strict_types=1);

namespace Modules\Intelligence\Domain\Support;

use App\Models\User;
use Modules\Intelligence\Domain\DataObjects\ReportFieldDefinition;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

/**
 * The one place the report engine asks "may this user read this
 * field?" (BR-INT-01-002/003), used by the field picker, the executor's
 * select/filter/group checks and saved-report runs alike so they can
 * never disagree.
 *
 * spatie throws `PermissionDoesNotExist` when asked about a permission
 * string that has never been created as a row — even to ask whether
 * someone *lacks* it. A permission that does not exist cannot be held,
 * so it is treated as "not permitted" rather than letting an
 * unsynced permission catalogue turn the whole builder into a 500.
 */
final class ReportFieldAccess
{
    public function mayRead(ReportFieldDefinition $field, User $user): bool
    {
        return $this->has($user, $field->requiredPermission)
            && (! $field->isSensitive || $this->has($user, 'report.sensitive_field.access'));
    }

    public function has(User $user, string $permission): bool
    {
        try {
            return $user->hasPermissionTo($permission);
        } catch (PermissionDoesNotExist) {
            return false;
        }
    }
}
