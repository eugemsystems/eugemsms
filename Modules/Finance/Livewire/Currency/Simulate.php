<?php

declare(strict_types=1);

namespace Modules\Finance\Livewire\Currency;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Finance\Domain\Actions\SimulateRateChangeAction;
use Modules\Finance\Domain\DataObjects\SimulateRateChangeData;
use Modules\Finance\Models\SchoolCurrency;

/**
 * `Finance\Currency\Simulate` (Book B FIN-06 §6, `finance.rate.view`) —
 * a standalone impact simulator (BR-FIN-06-013/AC-FIN-06-005): what
 * would total debtors, total creditors, and the FX result look like at
 * a proposed rate, before anyone captures or approves it. Read-only —
 * `SimulateRateChangeAction` never persists anything.
 *
 * `$hasPreviewed` is a plain bool, not the `RateChangeSimulation` DTO
 * itself, as the component's public state: Livewire's property
 * synthesizer only knows how to hydrate scalars/arrays/collections/its
 * own recognised value objects, not an arbitrary readonly class — a
 * `public ?RateChangeSimulation $result` throws "Property type not
 * supported in Livewire" the moment the round trip tries to dehydrate
 * it. Recomputing from the (plain-string) inputs on every render is
 * cheap enough for a one-off preview screen.
 */
#[Title('Rate impact simulator')]
#[Layout('layouts.app')]
final class Simulate extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    public string $foreignCurrency = 'ZWG';

    public string $proposedRate = '';

    public string $asAt;

    public bool $hasPreviewed = false;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('finance.rate.view');
        $this->asAt = now()->toDateString();
    }

    public function preview(): void
    {
        $this->validate([
            'foreignCurrency' => ['required', 'size:3'],
            'proposedRate' => ['required', 'regex:/^\d+(\.\d{1,10})?$/'],
            'asAt' => ['required', 'date'],
        ]);

        $this->hasPreviewed = true;
    }

    public function render(): View
    {
        $result = null;

        if ($this->hasPreviewed) {
            try {
                $result = app(SimulateRateChangeAction::class)->execute(new SimulateRateChangeData(
                    schoolId: $this->school->id,
                    foreignCurrency: $this->foreignCurrency,
                    proposedRate: $this->proposedRate,
                    asAt: Carbon::parse($this->asAt),
                ));
            } catch (DomainException $e) {
                $this->hasPreviewed = false;
                $this->addError('proposedRate', $e->getMessage());
            }
        }

        return view('finance::currency.simulate', [
            'foreignCurrencies' => SchoolCurrency::where('school_id', $this->school->id)->where('is_base', false)->get(),
            'result' => $result,
        ]);
    }
}
