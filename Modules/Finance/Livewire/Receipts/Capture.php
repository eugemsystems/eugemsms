<?php

declare(strict_types=1);

namespace Modules\Finance\Livewire\Receipts;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Finance\Domain\Actions\CreateReceiptAction;
use Modules\Finance\Domain\DataObjects\CreateReceiptData;
use Modules\Finance\Livewire\Concerns\ResolvesSystemAccounts;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\Invoice;
use Modules\Finance\Models\TillSession;
use Modules\People\Models\Student;

/**
 * `Finance\Receipts\Capture` (Book B FIN-04 §4 ⭐/BR-FIN-04-001/019,
 * `finance.receipt.create`) — "the most performance-critical screen in
 * the product". Search-as-you-type, balance shown the moment a learner
 * is selected, multi-tender split. Sub-300ms search and literal
 * client-side print are perf/hardware concerns this admin-panel pass
 * doesn't chase — the screen is fully keyboard-operable (every field
 * reachable by Tab, `wire:submit` on Enter) and functionally complete;
 * "instant print" is a follow-up once a receipt PDF template exists
 * (Book A CORE-08 territory, not built here).
 *
 * An unidentified payer (no learner selected) still posts — as a
 * suspense receipt, never held off-ledger (BR-FIN-04-010).
 */
#[Title('Capture receipt')]
#[Layout('layouts.app')]
final class Capture extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use ResolvesSystemAccounts;

    public TillSession $tillSession;

    public string $studentSearch = '';

    public ?int $selectedStudentId = null;

    public string $selectedStudentLabel = '';

    public ?string $studentBalance = null;

    public string $receiptType = 'fee';

    public string $payerName = '';

    public string $payerPhone = '';

    public string $currency = 'USD';

    public ?string $narration = null;

    /**
     * @var array<int, array{tender_type: string, amount: string, reference: string, bank_account_id: string}>
     */
    public array $tenders = [];

    public function mount(School $school, TillSession $tillSession): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('finance.receipt.create');

        abort_unless($tillSession->cashier_id === Auth::id() && $tillSession->status === 'open', 403, __('This is not your own open till session.'));

        $this->tillSession = $tillSession;
        $this->addTender();
    }

    public function addTender(): void
    {
        $this->tenders[] = ['tender_type' => 'cash', 'amount' => '', 'reference' => '', 'bank_account_id' => ''];
    }

    public function removeTender(int $index): void
    {
        unset($this->tenders[$index]);
        $this->tenders = array_values($this->tenders);
    }

    /**
     * @return Collection<int, Student>
     */
    public function studentResults(): Collection
    {
        if (mb_strlen($this->studentSearch) < 2) {
            return new Collection;
        }

        return Student::query()
            ->where(fn ($q) => $q->where('admission_number', 'like', "%{$this->studentSearch}%")
                ->orWhere('first_name', 'like', "%{$this->studentSearch}%")
                ->orWhere('last_name', 'like', "%{$this->studentSearch}%"))
            ->limit(10)
            ->get();
    }

    public function selectStudent(int $studentId): void
    {
        $student = Student::findOrFail($studentId);
        $this->selectedStudentId = $student->id;
        $this->selectedStudentLabel = "{$student->admission_number} — {$student->fullName()}";
        $this->studentSearch = '';
        $this->refreshStudentBalance();
    }

    public function updatedCurrency(): void
    {
        $this->refreshStudentBalance();
    }

    /**
     * Invoices bill the responsible guardian (FIN-03's own
     * `billed_party_type`), so the debtor subledger is guardian-keyed —
     * but every `Invoice` row still carries its own `student_id`
     * directly, and `balance_minor` is the same cached figure
     * `GenerateAgedDebtorsReportAction` sums for the aged-debtors
     * report. Summing open invoices here (rather than a
     * `student`-subledger balance, which would only ever hold
     * overpayment/uncleared-cheque lines) is what actually matches
     * what the cashier needs to see.
     */
    private function refreshStudentBalance(): void
    {
        if ($this->selectedStudentId === null) {
            $this->studentBalance = null;

            return;
        }

        $balanceMinor = Invoice::query()
            ->where('student_id', $this->selectedStudentId)
            ->where('currency', $this->currency)
            ->where('balance_minor', '>', 0)
            ->sum('balance_minor');

        $this->studentBalance = number_format($balanceMinor / 100, 2);
    }

    public function clearStudent(): void
    {
        $this->selectedStudentId = null;
        $this->selectedStudentLabel = '';
        $this->studentBalance = null;
    }

    public function save(): void
    {
        $this->validate([
            'payerName' => ['required', 'string', 'max:200'],
            'receiptType' => ['required', 'in:fee,tuckshop,hire,sundry,deposit'],
            'currency' => ['required', 'size:3'],
            'tenders' => ['array', 'min:1'],
            'tenders.*.tender_type' => ['required', 'string'],
            'tenders.*.amount' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
        ]);

        $tenderData = array_map(fn (array $tender): array => [
            'tender_type' => $tender['tender_type'],
            'amount_minor' => (int) round((float) $tender['amount'] * 100),
            'currency' => $this->currency,
            'reference' => $tender['reference'] !== '' ? $tender['reference'] : null,
            'bank_account_id' => $tender['bank_account_id'] !== '' ? (int) $tender['bank_account_id'] : null,
        ], $this->tenders);

        $hasCheque = collect($this->tenders)->contains(fn (array $t): bool => $t['tender_type'] === 'cheque');

        try {
            $receipt = app(CreateReceiptAction::class)->execute(new CreateReceiptData(
                schoolId: $this->school->id,
                academicYearId: $this->tillSession->academic_year_id,
                termId: $this->tillSession->term_id,
                receiptType: $this->receiptType,
                payerType: $this->selectedStudentId !== null ? 'guardian' : 'external',
                payerName: $this->payerName,
                currency: $this->currency,
                tenders: $tenderData,
                receivedByUserId: (int) Auth::id(),
                tillSessionId: $this->tillSession->id,
                studentId: $this->selectedStudentId,
                payerPhone: $this->payerPhone !== '' ? $this->payerPhone : null,
                narration: $this->narration,
                creditBalanceAccountId: $this->systemAccount('credit_balance')?->id,
                suspenseAccountId: $this->systemAccount('suspense')?->id,
                unclearedChequeAccountId: $hasCheque ? $this->systemAccount('uncleared_cheque')?->id : null,
                effectiveDate: Carbon::now(),
            ));
        } catch (DomainException $e) {
            $this->addError('payerName', $e->getMessage());

            return;
        }

        $this->redirectRoute('finance.receipts.show', ['school' => $this->school, 'receipt' => $receipt], navigate: true);
    }

    public function render(): View
    {
        return view('finance::receipts.capture', [
            'bankAccounts' => Account::where('is_postable', true)->orderBy('code')->get(),
        ]);
    }
}
