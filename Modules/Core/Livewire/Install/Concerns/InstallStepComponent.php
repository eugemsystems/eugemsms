<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Install\Concerns;

use Livewire\Component;
use Modules\Core\Domain\Support\Install\InstallProgress;
use Modules\Core\Domain\Support\Install\InstallStepKey;

/**
 * Shared plumbing for the eleven installer wizard screens (Book A CORE-01
 * §5). BR-CORE-01-003: a screen ahead of the resumable step redirects
 * back — the wizard cannot be skipped ahead by guessing a URL.
 */
abstract class InstallStepComponent extends Component
{
    abstract protected function stepKey(): InstallStepKey;

    public function mount(): void
    {
        $this->guardStepIsReachable();
    }

    protected function guardStepIsReachable(): void
    {
        $ordered = InstallStepKey::ordered();
        $thisIndex = array_search($this->stepKey(), $ordered, true);
        $resumeIndex = array_search($this->progress()->resumeStep(), $ordered, true);

        if ($thisIndex > $resumeIndex) {
            $this->redirect(route($this->progress()->resumeStep()->routeName()));
        }
    }

    protected function progress(): InstallProgress
    {
        return app(InstallProgress::class);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function completeStepAndContinue(array $payload = []): void
    {
        $this->progress()->markCompleted($this->stepKey(), $payload);

        $next = $this->stepKey()->next();

        $this->redirect(route(($next ?? $this->stepKey())->routeName()));
    }
}
