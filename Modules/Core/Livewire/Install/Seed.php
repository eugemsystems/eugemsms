<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Install;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Modules\Core\Domain\Actions\Install\SeedZimbabweBaselineAction;
use Modules\Core\Domain\Contracts\Install\SeedPack;
use Modules\Core\Domain\DataObjects\Install\SeedPackData;
use Modules\Core\Domain\Registry\SeedPackRegistry;
use Modules\Core\Domain\Support\Install\InstallStepKey;
use Modules\Core\Livewire\Install\Concerns\InstallStepComponent;

/**
 * `Install\Seed` (Book A CORE-01 §5/§7). Checkbox list of seed packs with
 * descriptions.
 */
#[Title('Baseline Seed')]
#[Layout('core::layouts.install', ['step' => InstallStepKey::Seed])]
final class Seed extends InstallStepComponent
{
    /**
     * @var array<int, string>
     */
    public array $selectedPacks = ['calendar'];

    protected function stepKey(): InstallStepKey
    {
        return InstallStepKey::Seed;
    }

    /**
     * @return array<string, SeedPack>
     */
    #[Computed]
    public function packs(): array
    {
        return SeedPackRegistry::all();
    }

    public function continue(): void
    {
        $schoolId = $this->progress()->payloadOf(InstallStepKey::Organisation)['school_id'] ?? null;

        abort_if($schoolId === null, 500, 'No school was found for this installation.');

        $available = array_values(array_filter(
            $this->selectedPacks,
            fn (string $code): bool => (SeedPackRegistry::find($code)?->isAvailable()) === true,
        ));

        app(SeedZimbabweBaselineAction::class)->execute(new SeedPackData(
            schoolId: $schoolId,
            packs: $available,
        ));

        $this->completeStepAndContinue(['packs' => $available]);
    }

    public function render(): View
    {
        return view('core::install.seed');
    }
}
