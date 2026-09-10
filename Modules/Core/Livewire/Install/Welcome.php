<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Install;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Modules\Core\Domain\Support\Install\InstallStepKey;
use Modules\Core\Livewire\Install\Concerns\InstallStepComponent;

/**
 * `Install\Welcome` (Book A CORE-01 §5). Terms acceptance recorded with
 * timestamp and IP.
 */
#[Title('Welcome')]
#[Layout('core::layouts.install', ['step' => InstallStepKey::Welcome])]
final class Welcome extends InstallStepComponent
{
    public bool $termsAccepted = false;

    protected function stepKey(): InstallStepKey
    {
        return InstallStepKey::Welcome;
    }

    public function continue(): void
    {
        $this->validate(['termsAccepted' => ['accepted']]);

        $this->completeStepAndContinue([
            'terms_accepted_at' => now()->toIso8601String(),
            'terms_accepted_ip' => request()->ip(),
        ]);
    }

    public function render(): View
    {
        return view('core::install.welcome');
    }
}
