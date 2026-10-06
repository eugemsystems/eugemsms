<?php

declare(strict_types=1);

namespace Modules\People\Livewire\Alumni\Campaigns;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Finance\Models\Account;
use Modules\People\Domain\Actions\CreateCapitalCampaignAction;
use Modules\People\Domain\DataObjects\CreateCapitalCampaignData;
use Modules\People\Models\CapitalCampaign;

/**
 * `Alumni\Campaigns\Index` (Book K PPL-06 §5, `alumni.campaign.manage`).
 * Campaign progress is the amount actually RECEIVED — derived from
 * donations, never typed in and never the pledged total
 * (BR-PPL-06-010, AC-PPL-06-003). Donations post to the campaign's own
 * income account.
 */
#[Title('Campaigns')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $name = '';

    public string $purpose = '';

    public string $target = '';

    public string $currency = 'USD';

    public string $startsOn = '';

    public string $endsOn = '';

    public ?int $incomeAccountId = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('alumni.campaign.manage');

        $this->startsOn = now()->toDateString();
    }

    public function create(): void
    {
        $this->authorizePermission('alumni.campaign.manage');
        $this->resetErrorBag();

        $this->validate(['name' => ['required', 'string', 'max:150'], 'purpose' => ['required', 'string', 'max:2000'], 'target' => ['required', 'numeric', 'gt:0'], 'startsOn' => ['required', 'date'], 'endsOn' => ['nullable', 'date'], 'incomeAccountId' => ['required', 'integer']]);

        try {
            app(CreateCapitalCampaignAction::class)->execute(new CreateCapitalCampaignData(
                schoolId: $this->school->id, name: $this->name, purpose: $this->purpose, targetAmountMinor: (int) round((float) $this->target * 100),
                currency: strtoupper($this->currency), startsOn: Carbon::parse($this->startsOn), incomeAccountId: (int) $this->incomeAccountId,
                endsOn: $this->endsOn === '' ? null : Carbon::parse($this->endsOn),
            ));
        } catch (InvalidArgumentException $exception) {
            $this->addError('name', $exception->getMessage());

            return;
        }

        $this->reset('name', 'purpose', 'target', 'endsOn', 'incomeAccountId');
        $this->toast(__('Campaign created.'));
    }

    public function render(): View
    {
        return view('people::alumni.campaigns', [
            'campaigns' => CapitalCampaign::query()->withSum('pledges as pledged_total', 'pledged_amount_minor')->orderByDesc('id')->limit(100)->get(),
            'accounts' => Account::query()->where('is_postable', true)->orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }
}
