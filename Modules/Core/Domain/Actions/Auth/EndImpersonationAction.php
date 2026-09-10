<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Auth;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Auth\EndImpersonationData;
use Modules\Core\Domain\Events\Auth\ImpersonationEnded;
use Modules\Core\Models\ImpersonationSession;

final class EndImpersonationAction extends Action
{
    public function execute(EndImpersonationData $data): ImpersonationSession
    {
        $session = ImpersonationSession::findOrFail($data->impersonationSessionId);

        return $this->transaction(function () use ($session): ImpersonationSession {
            $session->forceFill(['ended_at' => Carbon::now()])->save();

            event(new ImpersonationEnded($session));

            return $session;
        });
    }
}
