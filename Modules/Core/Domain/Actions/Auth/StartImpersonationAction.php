<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Auth\StartImpersonationData;
use Modules\Core\Domain\Events\Auth\ImpersonationStarted;
use Modules\Core\Domain\Exceptions\ReasonRequiredException;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Core\Models\ImpersonationSession;

/**
 * ACT-StartImpersonation (Book A CORE-05 §3/BR-CORE-05-017). A reason
 * and a support ticket reference are always required; a customer
 * consent reference is additionally required outside local/testing
 * environments. Authorisation (`core.user.impersonate`) is the
 * caller's responsibility to check before invoking this Action, per
 * the Action base class's "self-authorise" rule — the caller here is a
 * vendor-only console screen gated on that permission.
 */
final class StartImpersonationAction extends Action
{
    public function __construct(
        private readonly SettingResolver $settings,
    ) {}

    public function execute(StartImpersonationData $data): ImpersonationSession
    {
        if (trim($data->reason) === '' || trim($data->ticketReference) === '') {
            throw new ReasonRequiredException('Impersonation requires both a reason and a support ticket reference.');
        }

        if (app()->environment('production') && ($data->consentReference === null || trim($data->consentReference) === '')) {
            throw new ReasonRequiredException('Impersonation in production requires a recorded customer consent reference.');
        }

        User::findOrFail($data->impersonatorId);
        User::findOrFail($data->impersonatedId);

        $maxMinutes = (int) $this->settings->get('auth.impersonation_max_minutes', new ScopeChain(schoolId: $data->schoolId));

        return $this->transaction(function () use ($data, $maxMinutes): ImpersonationSession {
            $startedAt = Carbon::now();

            $session = ImpersonationSession::create([
                'impersonator_id' => $data->impersonatorId,
                'impersonated_id' => $data->impersonatedId,
                'school_id' => $data->schoolId,
                'reason' => $data->reason,
                'ticket_reference' => $data->ticketReference,
                'consent_reference' => $data->consentReference,
                'started_at' => $startedAt,
                'expires_at' => $startedAt->copy()->addMinutes($maxMinutes),
            ]);

            event(new ImpersonationStarted($session));

            return $session;
        });
    }
}
