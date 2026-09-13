<?php

declare(strict_types=1);

namespace Modules\Finance\Livewire\Journals;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Finance\Domain\Actions\ReverseJournalAction;
use Modules\Finance\Domain\DataObjects\ReverseJournalData;
use Modules\Finance\Models\Journal;

/**
 * `Finance\Journals\Reverse` (Book B FIN-01 §8, `finance.journal.reverse`)
 * — correction by reversal only (BR-FIN-01-013). `overrideCrossPeriod`
 * requires the separate `finance.journal.reverse_cross_period`
 * permission (BR-FIN-01-014): reversing into an already-closed period is
 * a materially bigger authority than reversing within the open one.
 */
#[Title('Reverse journal')]
#[Layout('layouts.app')]
final class Reverse extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    public Journal $journal;

    public string $reason = '';

    public bool $overrideCrossPeriod = false;

    public function mount(School $school, Journal $journal): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('finance.journal.reverse');
        $this->journal = $journal->load('lines.account');

        abort_if($journal->isReversed(), 409);
    }

    public function save(): void
    {
        $this->authorizePermission('finance.journal.reverse');

        $this->validate([
            'reason' => ['required', 'string', 'min:15', 'max:500'],
        ]);

        if ($this->overrideCrossPeriod) {
            $this->authorizePermission('finance.journal.reverse_cross_period');
        }

        try {
            $reversal = app(ReverseJournalAction::class)->execute(new ReverseJournalData(
                journalId: $this->journal->id,
                reason: $this->reason,
                reversedByUserId: (int) Auth::id(),
                overrideCrossPeriod: $this->overrideCrossPeriod,
            ));
        } catch (DomainException $e) {
            $this->addError('reason', $e->getMessage());

            return;
        }

        $this->redirectRoute('finance.journals.show', ['school' => $this->school, 'journal' => $reversal], navigate: true);
    }

    public function render(): View
    {
        return view('finance::journals.reverse');
    }
}
