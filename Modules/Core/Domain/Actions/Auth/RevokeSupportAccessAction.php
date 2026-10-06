<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Auth;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Models\ImpersonationSession;
use Modules\Core\Models\SupportAccessGrant;

/**
 * ACT-RevokeSupportAccess (Book J SAA-02 BR-SAA-02-002). Withdraws a support-access grant at once
 * and ends any session that was opened under it — consent that can be withdrawn but leaves the
 * session running is not consent. Revoking an already-revoked or expired grant changes nothing.
 */
final class RevokeSupportAccessAction extends Action
{
    public function execute(int $grantId, int $revokedByUserId): SupportAccessGrant
    {
        return $this->transaction(function () use ($grantId, $revokedByUserId): SupportAccessGrant {
            $grant = SupportAccessGrant::query()->lockForUpdate()->findOrFail($grantId);

            if ($grant->revoked_at === null) {
                $grant->update(['revoked_at' => now(), 'revoked_by' => $revokedByUserId]);
            }

            ImpersonationSession::query()
                ->where('access_grant_id', $grant->id)
                ->whereNull('ended_at')
                ->update(['ended_at' => now()]);

            return $grant;
        });
    }
}
