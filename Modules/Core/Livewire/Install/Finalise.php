<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Install;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Modules\Core\Domain\Actions\Install\FinaliseInstallationAction;
use Modules\Core\Domain\DataObjects\Install\FinaliseInstallationData;
use Modules\Core\Domain\Support\Install\DeploymentMode;
use Modules\Core\Domain\Support\Install\InstallProgress;
use Modules\Core\Domain\Support\Install\InstallStepKey;
use Modules\Core\Livewire\Install\Concerns\InstallStepComponent;
use Modules\Core\Models\SystemInstallation;

/**
 * `Install\Finalise` (Book A CORE-01 §5). Runs optimisation, writes the
 * lock, shows credentials summary and login link. BR-CORE-01-001: once
 * this runs, every installer route 404s — nothing here needs the
 * `mount()` step-reachability redirect once already finalised.
 */
#[Title('Finalise')]
#[Layout('core::layouts.install', ['step' => InstallStepKey::Finalise])]
final class Finalise extends InstallStepComponent
{
    public bool $finalised = false;

    public ?string $installationUuid = null;

    protected function stepKey(): InstallStepKey
    {
        return InstallStepKey::Finalise;
    }

    public function mount(): void
    {
        parent::mount();

        if (InstallProgress::isInstalled()) {
            $this->finalised = true;
            $this->installationUuid = SystemInstallation::query()->latest('id')->first()?->installation_uuid;
        }
    }

    public function finalise(): void
    {
        $environment = $this->progress()->payloadOf(InstallStepKey::Environment);
        $licence = $this->progress()->payloadOf(InstallStepKey::Licence);

        $installation = app(FinaliseInstallationAction::class)->execute(new FinaliseInstallationData(
            deploymentMode: DeploymentMode::from($environment['deployment_mode'] ?? 'saas'),
            licenceKey: $licence['licence_key'] ?? null,
            licenceActivatedAt: isset($licence['activated_at']) ? Carbon::parse($licence['activated_at']) : null,
            licenceExpiresAt: isset($licence['expires_at']) ? Carbon::parse($licence['expires_at']) : null,
        ));

        $this->progress()->markCompleted($this->stepKey());
        $this->finalised = true;
        $this->installationUuid = $installation->installation_uuid;
    }

    public function render(): View
    {
        return view('core::install.finalise');
    }
}
