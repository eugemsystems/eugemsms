<?php

declare(strict_types=1);

namespace Modules\Finance\Livewire\Statements;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Finance\Domain\Actions\GenerateStatementAction;
use Modules\Finance\Domain\DataObjects\GenerateStatementData;
use Modules\People\Models\Guardian;
use Modules\People\Models\Student;

/**
 * `Finance\Statements\Generate` (Book B FIN-03 §5/BR-FIN-03-009/021,
 * `finance.statement.generate`) — per learner or per guardian, any date
 * range. Always built from `journal_lines`, never from any cached
 * figure, so a statement for a closed period regenerates identically
 * forever (AC-FIN-03-008).
 */
#[Title('Generate statement')]
#[Layout('layouts.app')]
final class Generate extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    public string $subledgerType = 'student';

    public string $partySearch = '';

    public ?int $selectedPartyId = null;

    public string $selectedPartyLabel = '';

    public string $currency = 'USD';

    public string $from;

    public string $to;

    public bool $hasGenerated = false;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('finance.statement.generate');
        $this->from = now()->startOfMonth()->toDateString();
        $this->to = now()->toDateString();
    }

    /**
     * A plain array, not a typed `Collection`, for the `Student|Guardian`
     * union: Larastan's `Collection<K,V>` is invariant, so a method
     * declared to return `Collection<int, Student|Guardian>` cannot
     * actually return either branch's own `Collection<int, Student>` /
     * `Collection<int, Guardian>` result — the same limitation
     * `.ai/rules/support.md` already documents for `FeeLineCalculator`.
     *
     * @return array<int, Student|Guardian>
     */
    public function partyResults(): array
    {
        if (mb_strlen($this->partySearch) < 2) {
            return [];
        }

        if ($this->subledgerType === 'student') {
            return Student::query()
                ->where(fn ($q) => $q->where('admission_number', 'like', "%{$this->partySearch}%")
                    ->orWhere('first_name', 'like', "%{$this->partySearch}%")
                    ->orWhere('last_name', 'like', "%{$this->partySearch}%"))
                ->limit(10)
                ->get()
                ->all();
        }

        return Guardian::query()
            ->where(fn ($q) => $q->where('first_name', 'like', "%{$this->partySearch}%")
                ->orWhere('last_name', 'like', "%{$this->partySearch}%")
                ->orWhere('organisation_name', 'like', "%{$this->partySearch}%"))
            ->limit(10)
            ->get()
            ->all();
    }

    public function selectParty(int $partyId): void
    {
        $this->selectedPartyId = $partyId;

        if ($this->subledgerType === 'student') {
            $student = Student::findOrFail($partyId);
            $this->selectedPartyLabel = "{$student->admission_number} — {$student->fullName()}";
        } else {
            $this->selectedPartyLabel = Guardian::findOrFail($partyId)->displayName();
        }

        $this->partySearch = '';
        $this->hasGenerated = false;
    }

    public function generate(): void
    {
        $this->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'currency' => ['required', 'size:3'],
        ]);

        if ($this->selectedPartyId === null) {
            $this->addError('currency', __('Select a learner or guardian first.'));

            return;
        }

        $this->hasGenerated = true;
    }

    public function render(): View
    {
        $statement = null;

        if ($this->hasGenerated && $this->selectedPartyId !== null) {
            $statement = app(GenerateStatementAction::class)->execute(new GenerateStatementData(
                schoolId: $this->school->id,
                subledgerType: $this->subledgerType,
                subledgerId: $this->selectedPartyId,
                currency: $this->currency,
                from: Carbon::parse($this->from),
                to: Carbon::parse($this->to),
            ));
        }

        return view('finance::statements.generate', [
            'statement' => $statement,
        ]);
    }
}
