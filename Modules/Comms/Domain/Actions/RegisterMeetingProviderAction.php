<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Actions;

use Modules\Comms\Models\MeetingProvider;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-RegisterMeetingProvider (Book I COM-07 §2/§5 ⚠⚠).
 */
final class RegisterMeetingProviderAction extends Action
{
    public function execute(int $schoolId, string $provider, string $credentials, ?string $accountEmail = null): MeetingProvider
    {
        return $this->transaction(fn (): MeetingProvider => MeetingProvider::updateOrCreate(
            ['school_id' => $schoolId, 'provider' => $provider],
            ['credentials' => $credentials, 'account_email' => $accountEmail, 'is_active' => true],
        ));
    }
}
