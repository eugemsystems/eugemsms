<?php

declare(strict_types=1);

namespace Modules\Finance\Livewire\Journals;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Domain\Support\Currency;
use Modules\Core\Domain\Support\Money;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\School;
use Modules\Finance\Domain\Actions\CreateManualJournalAction;
use Modules\Finance\Domain\DataObjects\JournalLineData;
use Modules\Finance\Domain\DataObjects\PostJournalData;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\CostCentre;

/**
 * `Finance\Journals\Create` (Book B FIN-01 §8, `finance.journal.create_manual`)
 * — a manual journal is a draft (BR-FIN-01-018): this screen only calls
 * `CreateManualJournalAction`, never `PostJournalAction` directly, since
 * a manual entry always needs a second, different user's approval
 * (`Journals\Show::approve()`) before it becomes real money.
 */
#[Title('New manual journal')]
#[Layout('layouts.app')]
final class Create extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;
    use Toasts;

    public string $narration = '';

    public ?string $reference = null;

    public string $effectiveAt;

    /**
     * @var array<int, array{account_id: string, direction: string, currency: string, amount: string, cost_centre_id: string, narration: string}>
     */
    public array $lines = [];

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
        $this->authorizePermission('finance.journal.create_manual');
        $this->effectiveAt = now()->toDateString();
        $this->addLine();
        $this->addLine();
    }

    public function addLine(): void
    {
        $this->lines[] = [
            'account_id' => '',
            'direction' => 'DR',
            'currency' => 'USD',
            'amount' => '',
            'cost_centre_id' => '',
            'narration' => '',
        ];
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
    }

    public function save(): void
    {
        $this->validate([
            'narration' => ['required', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:100'],
            'effectiveAt' => ['required', 'date'],
            'lines' => ['array', 'min:2'],
            'lines.*.account_id' => ['required', 'integer'],
            'lines.*.direction' => ['required', 'in:DR,CR'],
            'lines.*.currency' => ['required', 'size:3'],
            'lines.*.amount' => ['required', 'regex:/^\d+(\.\d{1,4})?$/'],
        ]);

        $yearId = SessionContext::yearId();
        $termId = SessionContext::termId();

        if ($yearId === null || $termId === null) {
            $this->addError('narration', __('No active academic year/term is set for this school.'));

            return;
        }

        $lineData = collect($this->lines)->map(fn (array $line): JournalLineData => new JournalLineData(
            accountId: (int) $line['account_id'],
            direction: $line['direction'],
            amount: Money::fromDecimal($line['amount'], Currency::from($line['currency'])),
            costCentreId: $line['cost_centre_id'] !== '' ? (int) $line['cost_centre_id'] : null,
            narration: $line['narration'] !== '' ? $line['narration'] : null,
        ))->all();

        try {
            $journal = app(CreateManualJournalAction::class)->execute(new PostJournalData(
                schoolId: $this->school->id,
                academicYearId: $yearId,
                termId: $termId,
                journalType: 'MANUAL',
                narration: $this->narration,
                lines: $lineData,
                effectiveAt: Carbon::parse($this->effectiveAt),
                postedByUserId: (int) Auth::id(),
                reference: $this->reference,
            ));
        } catch (DomainException $e) {
            $this->addError('narration', $e->getMessage());

            return;
        }

        $this->redirectRoute('finance.journals.show', ['school' => $this->school, 'journal' => $journal], navigate: true);
    }

    public function render(): View
    {
        return view('finance::journals.create', [
            'accounts' => Account::query()->where('is_postable', true)->orderBy('code')->get(),
            'costCentres' => CostCentre::query()->orderBy('code')->get(),
        ]);
    }
}
