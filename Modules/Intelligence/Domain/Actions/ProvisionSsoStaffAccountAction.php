<?php

declare(strict_types=1);

namespace Modules\Intelligence\Domain\Actions;

use App\Models\User;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Auth\CreateUserAction;
use Modules\Core\Domain\DataObjects\Auth\CreateUserData;
use Modules\Core\Domain\Support\Auth\UserType;
use Modules\Intelligence\Models\SsoProvisioningConfig;

/**
 * ACT-ProvisionSsoStaffAccount (Book J INT-04 §2/BR-INT-04-006). Every
 * SSO-provisioned account is created through the real
 * `Modules\Core\Domain\Actions\Auth\CreateUserAction` (`CORE-05`) and
 * then given its role through spatie's own `assignRole` — the exact
 * same identity and permission machinery a manually-created staff
 * account passes through. This action never bypasses either step to
 * grant an implicit, unassigned set of permissions.
 */
final class ProvisionSsoStaffAccountAction extends Action
{
    public function __construct(
        private readonly CreateUserAction $createUser,
    ) {}

    public function execute(int $schoolId, int $tenantId, string $firstName, string $lastName, string $email, string $roleName): User
    {
        $config = SsoProvisioningConfig::where('school_id', $schoolId)->first();

        if ($config === null || ! $config->auto_provision_staff) {
            throw new InvalidArgumentException('SSO auto-provisioning is not enabled for this school.');
        }

        $user = $this->createUser->execute(new CreateUserData(
            firstName: $firstName, lastName: $lastName, email: $email, userType: UserType::Staff, tenantId: $tenantId,
        ));

        $user->assignRole($roleName);

        $this->transaction(fn () => $config->update(['sync_status' => 'active', 'last_synced_at' => Carbon::now()]));

        return $user->fresh();
    }
}
