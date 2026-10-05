<?php

declare(strict_types=1);

namespace Modules\Payroll\Livewire\Returns;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Payroll\Domain\Actions\CheckStatutoryReturnDeadlinesAction;
use Modules\Payroll\Domain\Actions\RecordStatutoryReturnSubmissionAction;
use Modules\Payroll\Domain\DataObjects\RecordStatutoryReturnSubmissionData;
use Modules\Payroll\Models\StatutoryReturn;

/**
 * `Payroll\Returns\Index` (Book H3 PPL-05 §4 step 8/§5/§6 🇿🇼,
 * `payroll.returns.manage`). Returns are auto-prepared on posting
 * (`PostPayrollRunAction` → `PrepareStatutoryReturnsAction`,
 * BR-PPL-05-020) — this screen never prepares one itself, only shows
 * the due dates, runs the due/overdue scan on demand (the honest
 * on-demand stand-in `CheckStatutoryReturnDeadlinesAction`'s own
 * docblock already describes, since no scheduler calls it daily
 * yet), and records a human's manual submission with its reference
 * (BR-PPL-05-022 — the system prepares and exports, it never files).
 * No `GenerateStatutoryReturnExportAction` exists in the domain layer
 * (verified by grep) — export is deliberately not built; the
 * `supporting_data` already captured on each return is shown
 * read-only instead of being re-derived here.
 */
#[Title('Statutory returns')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public int $dueCount = 0;

    public int $overdueCount = 0;

    public bool $checked = false;

    public ?int $recordingReturnId = null;

    public string $submissionReference = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('payroll.returns.manage');
    }

    public function checkDeadlines(): void
    {
        $result = app(CheckStatutoryReturnDeadlinesAction::class)->execute($this->school->id);

        $this->dueCount = $result['due']->count();
        $this->overdueCount = $result['overdue']->count();
        $this->checked = true;
    }

    public function startRecording(int $statutoryReturnId): void
    {
        $this->recordingReturnId = $statutoryReturnId;
        $this->submissionReference = '';
    }

    public function recordSubmission(): void
    {
        $this->validate(['submissionReference' => ['required', 'string', 'max:80']]);

        app(RecordStatutoryReturnSubmissionAction::class)->execute(new RecordStatutoryReturnSubmissionData(
            statutoryReturnId: (int) $this->recordingReturnId,
            submissionReference: $this->submissionReference,
        ));

        $this->recordingReturnId = null;
        $this->toast(__('Submission recorded.'));
    }

    public function render(): View
    {
        return view('payroll::returns.index', [
            'returns' => StatutoryReturn::where('school_id', $this->school->id)->orderBy('due_date')->get(),
        ]);
    }
}
