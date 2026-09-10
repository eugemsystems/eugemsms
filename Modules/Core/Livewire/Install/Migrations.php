<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Install;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Modules\Core\Domain\Actions\Install\RunInstallMigrationsAction;
use Modules\Core\Domain\Support\Install\InstallStepKey;
use Modules\Core\Livewire\Install\Concerns\InstallStepComponent;

/**
 * `Install\Migrations` (Book A CORE-01 §5). Per-module status; resumable.
 * BR-CORE-01-006: a failure halts immediately and reports the failing
 * migration; prior migrations stay intact for resumption.
 */
#[Title('Migrations')]
#[Layout('core::layouts.install', ['step' => InstallStepKey::Migrations])]
final class Migrations extends InstallStepComponent
{
    public bool $hasRun = false;

    public bool $succeeded = false;

    public string $message = '';

    /**
     * @var array<int, string>
     */
    public array $ranMigrations = [];

    protected function stepKey(): InstallStepKey
    {
        return InstallStepKey::Migrations;
    }

    public function runMigrations(): void
    {
        $this->progress()->markRunning($this->stepKey());

        $result = app(RunInstallMigrationsAction::class)->execute();

        $this->hasRun = true;
        $this->succeeded = $result->successful;
        $this->ranMigrations = $result->ranMigrations;
        $this->message = $result->successful
            ? 'Migrations completed successfully.'
            : ($result->errorMessage ?? 'Migration failed.');

        if (! $result->successful) {
            $this->progress()->markFailed($this->stepKey(), $this->message);
        }
    }

    public function continue(): void
    {
        if (! $this->succeeded) {
            return;
        }

        $this->completeStepAndContinue();
    }

    public function render(): View
    {
        return view('core::install.migrations');
    }
}
