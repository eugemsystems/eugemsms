<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Install;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Modules\Core\Domain\Actions\Install\ActivateLicenceAction;
use Modules\Core\Domain\DataObjects\Install\LicenceActivationStatus;
use Modules\Core\Domain\DataObjects\Install\LicenceKeyData;
use Modules\Core\Domain\Support\Install\InstallStepKey;
use Modules\Core\Livewire\Install\Concerns\InstallStepComponent;

/**
 * `Install\Licence` (Book A CORE-01 §5). Key entry, activation, or
 * "continue in grace mode". BR-CORE-01-007: activation failure never
 * blocks installation — the system installs in a 14-day grace state.
 */
#[Title('Licence')]
#[Layout('core::layouts.install', ['step' => InstallStepKey::Licence])]
final class Licence extends InstallStepComponent
{
    public string $licenceKey = '';

    protected function stepKey(): InstallStepKey
    {
        return InstallStepKey::Licence;
    }

    public function continue(): void
    {
        $result = app(ActivateLicenceAction::class)->execute(new LicenceKeyData(
            key: $this->licenceKey !== '' ? $this->licenceKey : null,
        ));

        if ($result->status === LicenceActivationStatus::Invalid) {
            $this->addError('licenceKey', $result->message);

            return;
        }

        $this->completeStepAndContinue([
            'status' => $result->status->value,
            'licence_key' => $this->licenceKey !== '' ? $this->licenceKey : null,
            'activated_at' => $result->activatedAt?->toIso8601String(),
            'expires_at' => $result->expiresAt?->toIso8601String(),
            'message' => $result->message,
        ]);
    }

    public function render(): View
    {
        return view('core::install.licence');
    }
}
