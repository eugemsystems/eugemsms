<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support\Auth;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;

/**
 * BR-CORE-05-005: 2FA is mandatory for whichever roles
 * `auth.require_2fa_roles` names (Super Admin/Head/Bursar/Cashier by
 * default) — shared between `AuthenticateWebAction` (decides whether a
 * fresh login needs a challenge or forced enrolment) and
 * `EnsureTwoFactorIsEnrolled` middleware (keeps a not-yet-enrolled user
 * confined to the enrolment screen on every later request). Queried
 * directly against `model_has_roles` rather than `$user->roles()` —
 * the "team" (school) scope spatie's relation applies isn't reliably
 * resolvable at login time (`SchoolContext` is only set after
 * authentication, by `SetSchoolContext`), and the rule doesn't limit
 * the requirement to one school.
 */
final class TwoFactorRequirement
{
    public function __construct(
        private readonly SettingResolver $settings,
    ) {}

    public function isRequiredFor(User $user): bool
    {
        $requiredRoles = (array) $this->settings->get(
            'auth.require_2fa_roles',
            new ScopeChain(tenantId: $user->tenant_id),
        );

        if ($requiredRoles === []) {
            return false;
        }

        return DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_id', $user->id)
            ->where('model_has_roles.model_type', $user->getMorphClass())
            ->whereIn('roles.name', $requiredRoles)
            ->exists();
    }
}
