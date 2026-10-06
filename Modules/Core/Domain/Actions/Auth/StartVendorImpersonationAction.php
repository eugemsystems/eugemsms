<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Auth;

use App\Models\User;
use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Auth\StartImpersonationData;
use Modules\Core\Domain\DataObjects\Auth\StartVendorImpersonationData;
use Modules\Core\Domain\Exceptions\ImpersonationNotPermittedException;
use Modules\Core\Domain\Support\Auth\UserType;
use Modules\Core\Models\ImpersonationSession;
use Modules\Core\Models\SupportAccessGrant;

/**
 * ACT-StartVendorImpersonation (Book J SAA-02 BR-SAA-02-002). The vendor console's entry point
 * into CORE-05's impersonation. A session opens only when all of these hold:
 *
 *  - the operator is vendor staff, and the target is an ordinary tenant user — never another
 *    vendor account, and never a user outside the grant's tenant;
 *  - the tenant's own administrator has an active, unrevoked grant whose ticket is the one the
 *    operator names (so consent given for one problem does not stretch to another);
 *  - a reason is given, and the target can sign in.
 *
 * The session is read-only (the base Action refuses any write while it is open), ends when the
 * shorter of the platform maximum and the grant runs out, and is stamped with the grant that
 * authorised it so the customer can see exactly who looked, when, and under what consent. The
 * caller performs the browser session swap; this Action only records and authorises.
 */
final class StartVendorImpersonationAction extends Action
{
    public function __construct(
        private readonly StartImpersonationAction $startImpersonation,
    ) {}

    public function execute(StartVendorImpersonationData $data): ImpersonationSession
    {
        $operator = User::query()->findOrFail($data->operatorId);

        if ($operator->user_type !== UserType::Vendor) {
            throw new ImpersonationNotPermittedException('Only vendor staff can open a support session.');
        }

        $target = User::query()->findOrFail($data->targetUserId);
        $errors = [];

        if ($target->user_type === UserType::Vendor || $target->tenant_id === null) {
            $errors['targetUserId'] = 'A vendor account cannot be impersonated.';
        } elseif (! $target->status->canAuthenticate()) {
            $errors['targetUserId'] = 'That user cannot sign in, so there is nothing to look at as them.';
        }

        if (trim($data->reason) === '') {
            $errors['reason'] = 'A reason is required.';
        }

        $grant = $target->tenant_id === null ? null : SupportAccessGrant::query()
            ->where('tenant_id', $target->tenant_id)
            ->where('ticket_reference', trim($data->ticketReference))
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->orderByDesc('expires_at')
            ->first();

        if ($grant === null) {
            $errors['ticketReference'] = 'The customer has not granted access for that ticket, or the grant has expired or been withdrawn.';
        }

        if ($errors !== [] || $grant === null) {
            throw ValidationException::withMessages($errors);
        }

        return $this->transaction(function () use ($data, $operator, $target, $grant): ImpersonationSession {
            $session = $this->startImpersonation->execute(new StartImpersonationData(
                impersonatorId: $operator->id,
                impersonatedId: $target->id,
                reason: $data->reason,
                ticketReference: $grant->ticket_reference,
                consentReference: 'grant:'.$grant->id,
                schoolId: $target->primarySchool()?->id,
            ));

            $session->forceFill([
                'access_grant_id' => $grant->id,
                'is_read_only' => true,
                'expires_at' => $session->expires_at->lessThan($grant->expires_at) ? $session->expires_at : $grant->expires_at,
            ])->save();

            return $session;
        });
    }
}
