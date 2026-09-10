<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Install;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Modules\Core\Domain\Actions\Install\VerifyRequirementsAction;
use Modules\Core\Domain\Support\Install\InstallStepKey;
use Modules\Core\Livewire\Install\Concerns\InstallStepComponent;

/**
 * `Install\Requirements` (Book A CORE-01 §5). Live re-check button;
 * pass/warn/fail per item with remediation text. BR-CORE-01-002:
 * installation cannot proceed past this step while any mandatory
 * requirement fails.
 */
#[Title('Requirements')]
#[Layout('core::layouts.install', ['step' => InstallStepKey::Requirements])]
final class Requirements extends InstallStepComponent
{
    /**
     * @var array<int, array{name: string, status: string, mandatory: bool, message: string, remediation: ?string}>
     */
    public array $checks = [];

    public bool $passesMandatory = false;

    protected function stepKey(): InstallStepKey
    {
        return InstallStepKey::Requirements;
    }

    public function mount(): void
    {
        parent::mount();

        $this->runCheck();
    }

    public function runCheck(): void
    {
        $report = app(VerifyRequirementsAction::class)->execute();

        $this->checks = array_map(fn ($check): array => [
            'name' => $check->name,
            'status' => $check->status->value,
            'mandatory' => $check->mandatory,
            'message' => $check->message,
            'remediation' => $check->remediation,
        ], $report->checks);

        $this->passesMandatory = $report->passesMandatory();
    }

    public function continue(): void
    {
        if (! $this->passesMandatory) {
            return;
        }

        $this->completeStepAndContinue();
    }

    public function render(): View
    {
        return view('core::install.requirements');
    }
}
