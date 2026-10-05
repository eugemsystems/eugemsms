<?php

declare(strict_types=1);

namespace Modules\Reporting\Livewire\Close;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Reporting\Domain\Actions\AcknowledgeCloseCheckAction;
use Modules\Reporting\Domain\Actions\GenerateClosePackAction;
use Modules\Reporting\Domain\Actions\RunAndRecordCloseChecklistAction;
use Modules\Reporting\Domain\DataObjects\AcknowledgeCloseCheckData;
use Modules\Reporting\Domain\DataObjects\GenerateClosePackData;
use Modules\Reporting\Domain\DataObjects\RunAndRecordCloseChecklistData;
use Modules\Reporting\Models\PeriodCloseChecklist;

/**
 * `Reports\Close\Checklist` (Book H3 FIN-12 §4 ⭐/§6,
 * `reporting.period.close`). Folds the spec's own separate "Close
 * pack" screen into this one — a pack is always generated FROM a
 * specific checklist run, so a second route would only need the
 * same checklist id this one already has selected. A blocking
 * failure's row never offers an acknowledge control — the backend's
 * own `AcknowledgeCloseCheckAction` refuses it by name
 * (`BlockingCheckCannotBeAcknowledgedException`), this screen simply
 * never shows the button for one rather than letting the click fail.
 */
#[Title('Close checklist')]
#[Layout('layouts.app')]
final class Checklist extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $termId = null;

    public string $periodType = 'financial';

    public ?int $selectedChecklistId = null;

    public string $acknowledgeCheckKey = '';

    public string $acknowledgeReason = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('reporting.period.close');
    }

    public function run(): void
    {
        $this->validate(['termId' => ['required', 'integer']]);

        $checklist = app(RunAndRecordCloseChecklistAction::class)->execute(new RunAndRecordCloseChecklistData(
            termId: (int) $this->termId,
            periodType: $this->periodType,
            runByUserId: (int) auth()->id(),
        ));

        $this->selectedChecklistId = $checklist->id;
        $this->toast(__('Checklist run — status :status.', ['status' => $checklist->overall_status]));
    }

    public function startAcknowledge(string $checkKey): void
    {
        $this->acknowledgeCheckKey = $checkKey;
        $this->acknowledgeReason = '';
    }

    public function acknowledge(): void
    {
        $this->validate(['acknowledgeReason' => ['required', 'string', 'min:5', 'max:500']]);

        try {
            app(AcknowledgeCloseCheckAction::class)->execute(new AcknowledgeCloseCheckData(
                checklistId: (int) $this->selectedChecklistId,
                checkKey: $this->acknowledgeCheckKey,
                reason: $this->acknowledgeReason,
                acknowledgedByUserId: (int) auth()->id(),
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->acknowledgeCheckKey = '';
        $this->toast(__('Check acknowledged.'));
    }

    public function generatePack(): void
    {
        try {
            app(GenerateClosePackAction::class)->execute(new GenerateClosePackData(
                checklistId: (int) $this->selectedChecklistId,
                generatedByUserId: (int) auth()->id(),
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Close pack generated.'));
    }

    public function render(): View
    {
        $selected = $this->selectedChecklistId !== null ? PeriodCloseChecklist::find($this->selectedChecklistId) : null;

        return view('reporting::close.checklist', [
            'terms' => Term::where('school_id', $this->school->id)->orderByDesc('id')->limit(20)->get(),
            'checklists' => PeriodCloseChecklist::where('school_id', $this->school->id)->orderByDesc('run_at')->limit(20)->get(),
            'selected' => $selected,
        ]);
    }
}
