<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Install;

use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rules\Enum;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Modules\Core\Domain\Support\Install\DeploymentMode;
use Modules\Core\Domain\Support\Install\InstallStepKey;
use Modules\Core\Livewire\Install\Concerns\InstallStepComponent;

/**
 * `Install\Environment` (Book A CORE-01 §5). App name, URL, timezone,
 * locale, deployment mode. BR-CORE-01-009: timezone defaults to
 * Africa/Harare, locale to en_ZW, base currency to USD — all changeable.
 */
#[Title('Environment')]
#[Layout('core::layouts.install', ['step' => InstallStepKey::Environment])]
final class Environment extends InstallStepComponent
{
    public string $appName = '';

    public string $appUrl = '';

    public string $timezone = 'Africa/Harare';

    public string $locale = 'en_ZW';

    public string $deploymentMode = 'saas';

    protected function stepKey(): InstallStepKey
    {
        return InstallStepKey::Environment;
    }

    public function mount(): void
    {
        parent::mount();

        $saved = $this->progress()->payloadOf($this->stepKey());
        $this->appName = $saved['app_name'] ?? (string) config('app.name');
        $this->appUrl = $saved['app_url'] ?? (string) config('app.url');
        $this->timezone = $saved['timezone'] ?? $this->timezone;
        $this->locale = $saved['locale'] ?? $this->locale;
        $this->deploymentMode = $saved['deployment_mode'] ?? $this->deploymentMode;
    }

    public function continue(): void
    {
        $this->validate([
            'appName' => ['required', 'string', 'max:255'],
            'appUrl' => ['required', 'url', 'max:255'],
            'timezone' => ['required', 'timezone'],
            'locale' => ['required', 'string', 'max:10'],
            'deploymentMode' => ['required', new Enum(DeploymentMode::class)],
        ]);

        $this->completeStepAndContinue([
            'app_name' => $this->appName,
            'app_url' => $this->appUrl,
            'timezone' => $this->timezone,
            'locale' => $this->locale,
            'deployment_mode' => $this->deploymentMode,
        ]);
    }

    public function render(): View
    {
        return view('core::install.environment');
    }
}
