<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\ReportCards;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Livewire\Concerns\ChecksPermissions;
use Modules\Academic\Models\TermResult;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\School;
use Modules\Finance\Domain\Actions\CheckReportGateAction;
use Modules\Finance\Domain\Actions\GrantReportGateOverrideAction;
use Modules\Finance\Domain\DataObjects\CheckReportGateData;
use Modules\Finance\Domain\DataObjects\GrantReportGateOverrideData;

/**
 * `ReportCards\Withheld` (Book D ACA-05 §6, `academic.report_card.view`).
 * Who is withheld, and how much over the threshold. A one-learner override
 * needs `finance.report_gate.override` and a reason; it lets the stored card
 * be published without regenerating it (BR-ACA-05-015).
 */
#[Title('Withheld report cards')]
#[Layout('layouts.app')]
final class Withheld extends Component
{
    use AuthorizesPermissions;
    use ChecksPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;
    use Toasts;

    public ?int $overridingId = null;

    public string $reason = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
        $this->authorizePermission('academic.report_card.view');
    }

    public function startOverride(int $resultId): void
    {
        $this->authorizePermission('finance.report_gate.override');
        $this->overridingId = TermResult::query()->where('status', 'withheld')->whereKey($resultId)->value('id');
        $this->reason = '';
    }

    public function override(): void
    {
        $this->authorizePermission('finance.report_gate.override');
        $this->resetErrorBag();

        $result = $this->overridingId === null ? null : TermResult::query()->where('status', 'withheld')->find($this->overridingId);

        if ($result === null) {
            return;
        }

        try {
            app(GrantReportGateOverrideAction::class)->execute(new GrantReportGateOverrideData(
                schoolId: $this->school->id, studentId: $result->student_id, termId: $result->term_id, reason: $this->reason, grantedByUserId: (int) auth()->id(),
            ));
        } catch (DomainException|InvalidArgumentException $exception) {
            $this->addError('reason', $exception->getMessage());

            return;
        }

        $this->reset('overridingId', 'reason');
        $this->toast(__('Override recorded. The card can now be published.'));
    }

    public function render(): View
    {
        $termId = SessionContext::termId();
        $results = $termId === null ? collect() : TermResult::query()->with('student')->where('term_id', $termId)->where('status', 'withheld')->orderBy('class_id')->get();

        $balances = [];

        foreach ($results as $result) {
            $balances[$result->id] = app(CheckReportGateAction::class)->execute(new CheckReportGateData($this->school->id, $result->student_id, $result->term_id));
        }

        return view('academic::report-cards.withheld', ['results' => $results, 'balances' => $balances, 'canOverride' => $this->holds('finance.report_gate.override')]);
    }
}
