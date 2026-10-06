<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Auth;

use App\Models\User;
use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Auth\GrantSupportAccessData;
use Modules\Core\Models\SupportAccessGrant;

/**
 * ACT-GrantSupportAccess (Book J SAA-02 BR-SAA-02-002). A customer administrator consents to a
 * vendor support session for one named ticket, for a limited number of hours. The grant is the
 * only thing that lets vendor staff open a session as one of the tenant's users, and it can be
 * revoked at any time. It never authorises a write: vendor sessions are read-only.
 */
final class GrantSupportAccessAction extends Action
{
    public const int MAX_HOURS = 72;

    public function execute(GrantSupportAccessData $data): SupportAccessGrant
    {
        $granter = User::query()->findOrFail($data->grantedByUserId);
        $errors = [];

        if ($granter->tenant_id !== $data->tenantId) {
            $errors['tenant'] = 'You can only grant access to your own organisation.';
        }

        if (trim($data->ticketReference) === '') {
            $errors['ticketReference'] = 'Name the support ticket this access is for.';
        }

        if (trim($data->reason) === '') {
            $errors['reason'] = 'Say what the support engineer needs to look at.';
        }

        if ($data->durationHours < 1 || $data->durationHours > self::MAX_HOURS) {
            $errors['durationHours'] = 'Access can be granted for 1 to '.self::MAX_HOURS.' hours.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $this->transaction(fn (): SupportAccessGrant => SupportAccessGrant::create([
            'tenant_id' => $data->tenantId,
            'granted_by' => $granter->id,
            'ticket_reference' => trim($data->ticketReference),
            'reason' => trim($data->reason),
            'expires_at' => now()->addHours($data->durationHours),
        ]));
    }
}
