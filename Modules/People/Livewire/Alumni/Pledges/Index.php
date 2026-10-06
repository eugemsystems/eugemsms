<?php

declare(strict_types=1);

namespace Modules\People\Livewire\Alumni\Pledges;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Domain\Actions\CreatePledgeAction;
use Modules\People\Domain\DataObjects\CreatePledgeData;
use Modules\People\Models\CapitalCampaign;
use Modules\People\Models\Pledge;

/**
 * `Alumni\Pledges\Index` (Book K PPL-06 §5, `alumni.pledge.manage`). A pledge
 * is a stated intention, not income: nothing here posts to the ledger, and
 * only donations actually received move `paid to date` (BR-PPL-06-006).
 * Anonymous donors are marked — a recognition list built from this screen
 * must not print their name.
 */
#[Title('Pledges')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $campaignFilter = null;

    public string $donorName = '';

    public string $donorType = 'alumnus';

    public string $amount = '';

    public string $currency = 'USD';

    public ?int $campaignId = null;

    public string $recognitionTier = '';

    public bool $isAnonymous = false;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('alumni.pledge.manage');
    }

    public function create(): void
    {
        $this->authorizePermission('alumni.pledge.manage');
        $this->resetErrorBag();

        $this->validate(['donorName' => ['required', 'string', 'max:200'], 'amount' => ['required', 'numeric', 'gt:0']]);

        try {
            app(CreatePledgeAction::class)->execute(new CreatePledgeData(
                schoolId: $this->school->id, donorName: $this->donorName, donorType: $this->donorType,
                pledgedAmountMinor: (int) round((float) $this->amount * 100), currency: strtoupper($this->currency),
                campaignId: $this->campaignId, recognitionTier: $this->recognitionTier === '' ? null : $this->recognitionTier, isAnonymous: $this->isAnonymous,
            ));
        } catch (InvalidArgumentException $exception) {
            $this->addError('donorName', $exception->getMessage());

            return;
        }

        $this->reset('donorName', 'amount', 'campaignId', 'recognitionTier', 'isAnonymous');
        $this->toast(__('Pledge recorded.'));
    }

    public function render(): View
    {
        $pledges = Pledge::query()->when($this->campaignFilter !== null, fn ($q) => $q->where('campaign_id', $this->campaignFilter))->orderByDesc('id')->limit(200)->get();

        return view('people::alumni.pledges', [
            'pledges' => $pledges,
            'campaigns' => CapitalCampaign::query()->orderBy('name')->get(['id', 'name', 'status']),
        ]);
    }
}
