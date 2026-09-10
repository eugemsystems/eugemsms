<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Install;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Modules\Core\Domain\Actions\Install\TestServiceConnectionAction;
use Modules\Core\Domain\DataObjects\Install\ServiceTestData;
use Modules\Core\Domain\Support\Install\InstallStepKey;
use Modules\Core\Livewire\Install\Concerns\InstallStepComponent;

/**
 * `Install\Services` (Book A CORE-01 §5). Per-service config with
 * individual Test buttons, all skippable (BR-CORE-01-010).
 */
#[Title('Services')]
#[Layout('core::layouts.install', ['step' => InstallStepKey::Services])]
final class Services extends InstallStepComponent
{
    /**
     * @var array<string, array{success: bool, message: string}>
     */
    public array $results = [];

    /**
     * @var array<int, string>
     */
    public array $services = ['mail', 'storage', 'queue', 'sms', 'whatsapp'];

    protected function stepKey(): InstallStepKey
    {
        return InstallStepKey::Services;
    }

    public function test(string $service): void
    {
        $result = app(TestServiceConnectionAction::class)->execute(new ServiceTestData($service));

        $this->results[$service] = ['success' => $result->success, 'message' => $result->message];
    }

    public function continue(): void
    {
        $this->completeStepAndContinue(['tested' => array_keys($this->results)]);
    }

    public function render(): View
    {
        return view('core::install.services');
    }
}
