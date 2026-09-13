<?php

declare(strict_types=1);

namespace Modules\Finance\Livewire\Debtors;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Support\Currency;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Finance\Domain\Actions\RecordDebtorChaseNoteAction;
use Modules\Finance\Domain\DataObjects\RecordDebtorChaseNoteData;
use Modules\Finance\Models\DebtorChaseNote;
use Modules\Finance\Models\Invoice;

/**
 * `Finance\Debtors\Workbench` (Book B FIN-03 §5, `finance.debtor.manage`)
 * — a prioritised chase list (highest balance first) with call notes
 * and outcomes.
 */
#[Title('Debtor workbench')]
#[Layout('layouts.app')]
final class Workbench extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $currency = 'USD';

    public ?int $notingStudentId = null;

    public string $outcome = 'promised_to_pay';

    public string $note = '';

    public ?string $nextActionOn = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('finance.debtor.manage');
    }

    public function openNoteModal(int $studentId): void
    {
        $this->notingStudentId = $studentId;
        $this->outcome = 'promised_to_pay';
        $this->note = '';
        $this->nextActionOn = null;
        $this->resetErrorBag();
    }

    public function saveNote(): void
    {
        $this->validate([
            'outcome' => ['required', 'in:promised_to_pay,no_answer,disputed,payment_plan_requested,unreachable,other'],
            'note' => ['required', 'string', 'max:1000'],
            'nextActionOn' => ['nullable', 'date'],
        ]);

        app(RecordDebtorChaseNoteAction::class)->execute(new RecordDebtorChaseNoteData(
            schoolId: $this->school->id,
            studentId: (int) $this->notingStudentId,
            outcome: $this->outcome,
            note: $this->note,
            recordedByUserId: (int) Auth::id(),
            nextActionOn: $this->nextActionOn,
        ));

        $this->notingStudentId = null;
        $this->toast(__('Chase note recorded.'));
    }

    public function render(): View
    {
        $chaseList = Invoice::query()
            ->where('currency', $this->currency)
            ->where('balance_minor', '>', 0)
            ->with('student')
            ->get()
            ->groupBy('student_id')
            ->map(fn ($invoices) => [
                'student' => $invoices->first()->student,
                'total_balance_minor' => $invoices->sum('balance_minor'),
                'invoice_count' => $invoices->count(),
                'oldest_due_date' => $invoices->min('due_date'),
            ])
            ->sortByDesc('total_balance_minor')
            ->values();

        $recentNotes = $this->notingStudentId !== null
            ? DebtorChaseNote::where('student_id', $this->notingStudentId)->orderByDesc('id')->limit(5)->get()
            : collect();

        return view('finance::debtors.workbench', [
            'chaseList' => $chaseList,
            'recentNotes' => $recentNotes,
            'currencies' => Currency::cases(),
        ]);
    }
}
