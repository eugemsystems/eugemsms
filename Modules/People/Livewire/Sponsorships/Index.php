<?php

declare(strict_types=1);

namespace Modules\People\Livewire\Sponsorships;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Domain\Actions\CreateSponsorshipAction;
use Modules\People\Domain\DataObjects\CreateSponsorshipData;
use Modules\People\Models\Guardian;
use Modules\People\Models\Sponsorship;

/**
 * `People\Sponsorships\Index` (Book C PPL-03 §7, `people.sponsorships.manage`).
 * Organisations that fund learners. The sponsor must be an organisation
 * guardian. Fees for a sponsored learner are invoiced to the sponsor, never
 * written off (BR-PPL-03-018).
 */
#[Title('Sponsorships')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $guardianId = null;

    public string $name = '';

    public string $type = 'full';

    public string $budget = '';

    public string $maxBeneficiaries = '';

    public string $startsOn = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('people.sponsorships.manage');
        $this->startsOn = now()->toDateString();
    }

    public function create(): void
    {
        $this->authorizePermission('people.sponsorships.manage');
        $this->resetErrorBag();
        $this->validate(['name' => ['required', 'string', 'max:200'], 'startsOn' => ['required', 'date'], 'budget' => ['nullable', 'numeric', 'gt:0'], 'maxBeneficiaries' => ['nullable', 'integer', 'min:1']]);

        try {
            app(CreateSponsorshipAction::class)->execute(new CreateSponsorshipData(
                schoolId: $this->school->id, guardianId: (int) $this->guardianId, name: $this->name, sponsorshipType: $this->type, startsOn: Carbon::parse($this->startsOn), createdByUserId: (int) auth()->id(),
                budgetMinor: $this->budget === '' ? null : (int) round((float) $this->budget * 100), budgetCurrency: $this->budget === '' ? null : $this->school->base_currency,
                maxBeneficiaries: $this->maxBeneficiaries === '' ? null : (int) $this->maxBeneficiaries,
            ));
        } catch (DomainException|InvalidArgumentException $exception) {
            $this->addError('guardianId', $exception->getMessage());

            return;
        }

        $this->reset('guardianId', 'name', 'budget', 'maxBeneficiaries');
        $this->toast(__('Sponsorship created as a draft.'));
    }

    public function render(): View
    {
        $sponsorships = Sponsorship::query()->withCount(['beneficiaries as active_beneficiaries' => fn ($q) => $q->where('status', 'active')])->orderByDesc('id')->limit(100)->get();

        return view('people::sponsorships.index', [
            'sponsorships' => $sponsorships,
            'sponsors' => Guardian::query()->where('guardian_type', 'organisation')->orderBy('organisation_name')->get(),
            'names' => Guardian::query()->whereIn('id', $sponsorships->pluck('guardian_id'))->get()->mapWithKeys(fn (Guardian $g): array => [$g->id => $g->displayName()]),
        ]);
    }
}
