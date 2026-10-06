<?php

declare(strict_types=1);

namespace Modules\People\Livewire\Alumni\Endowments;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Finance\Models\DiscountScheme;
use Modules\Finance\Models\SchemeBudgetEnvelope;
use Modules\People\Domain\Actions\CreateBursaryEndowmentAction;
use Modules\People\Domain\DataObjects\CreateBursaryEndowmentData;
use Modules\People\Models\BursaryEndowment;
use Modules\People\Models\Donation;

/**
 * `Alumni\Endowments\Index` (Book K PPL-06 §5, `alumni.endowment.manage` ⚠).
 * An endowment links a donor to a FIN-07 scheme. Its available balance
 * (capital plus donations accumulated) is projected onto that scheme's
 * budget envelope, so FIN-07's own refusal applies once it runs out
 * (BR-PPL-06-008, AC-PPL-06-004) — shown here beside what the scheme has
 * already committed. Named recognition is hidden when the donor chose
 * anonymity.
 */
#[Title('Endowments')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $donorName = '';

    public ?int $fundsSchemeId = null;

    public string $capital = '';

    public string $annual = '';

    public string $currency = 'USD';

    public string $namedRecognition = '';

    public bool $isAnonymous = false;

    public string $startsOn = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('alumni.endowment.manage');

        $this->startsOn = now()->toDateString();
    }

    public function create(): void
    {
        $this->authorizePermission('alumni.endowment.manage');
        $this->resetErrorBag();

        $this->validate(['donorName' => ['required', 'string', 'max:200'], 'fundsSchemeId' => ['required', 'integer'], 'capital' => ['nullable', 'numeric', 'min:0'], 'annual' => ['nullable', 'numeric', 'min:0'], 'startsOn' => ['required', 'date']]);

        try {
            app(CreateBursaryEndowmentAction::class)->execute(new CreateBursaryEndowmentData(
                schoolId: $this->school->id, donorName: $this->donorName, currency: strtoupper($this->currency), fundsSchemeId: (int) $this->fundsSchemeId,
                startsOn: Carbon::parse($this->startsOn),
                endowmentCapitalMinor: $this->capital === '' ? null : (int) round((float) $this->capital * 100),
                annualCommitmentMinor: $this->annual === '' ? null : (int) round((float) $this->annual * 100),
                namedRecognition: $this->namedRecognition === '' ? null : $this->namedRecognition, isAnonymous: $this->isAnonymous,
            ));
        } catch (InvalidArgumentException $exception) {
            $this->addError('donorName', $exception->getMessage());

            return;
        }

        $this->reset('donorName', 'fundsSchemeId', 'capital', 'annual', 'namedRecognition', 'isAnonymous');
        $this->toast(__('Endowment created.'));
    }

    public function render(): View
    {
        $endowments = BursaryEndowment::query()->with('fundsScheme')->orderByDesc('id')->limit(100)->get();
        $year = AcademicYear::query()->where('is_current', true)->first();

        return view('people::alumni.endowments', [
            'endowments' => $endowments,
            'donated' => Donation::query()->whereIn('bursary_endowment_id', $endowments->pluck('id'))->selectRaw('bursary_endowment_id, sum(amount_minor) as total')->groupBy('bursary_endowment_id')->toBase()->get()->mapWithKeys(fn (object $row): array => [(int) $row->bursary_endowment_id => (int) $row->total]),
            'envelopes' => $year === null ? collect() : SchemeBudgetEnvelope::query()->where('academic_year_id', $year->id)->whereIn('scheme_id', $endowments->pluck('funds_scheme_id'))->get()->keyBy('scheme_id'),
            'schemes' => DiscountScheme::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
        ]);
    }
}
