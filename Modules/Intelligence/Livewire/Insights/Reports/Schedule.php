<?php

declare(strict_types=1);

namespace Modules\Intelligence\Livewire\Insights\Reports;

use App\Concerns\Toasts;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Intelligence\Domain\Actions\ScheduleCustomReportAction;
use Modules\Intelligence\Models\CustomReport;
use Modules\Intelligence\Models\CustomReportSchedule;

/**
 * `Intelligence\Reports\Schedule` (Book J INT-01 §5, `report.schedule`).
 * Schedules one of the signed-in user's *own* reports for recurring
 * delivery to colleagues of the school. Delivery is the backend's:
 * each run is re-evaluated as the author and the recipient gets a
 * notification through CORE-09 that the report is ready — **no file is
 * attached** (rendering PDF/Excel/CSV is not built), and the cron wiring
 * that makes `RunScheduledReportsAction` fire is still the backend's
 * documented deferred step, so a schedule created here does not send
 * anything until that is wired. The screen says so. There is no
 * pause/delete Action, so a schedule can only be created.
 */
#[Title('Schedule a report')]
#[Layout('layouts.app')]
final class Schedule extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $reportId = null;

    public string $frequency = 'weekly';

    public string $format = 'csv';

    /** @var array<int, int> */
    public array $recipientIds = [];

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('report.schedule');
    }

    public function schedule(): void
    {
        $this->authorizePermission('report.schedule');

        $this->validate([
            'reportId' => ['required', 'integer'],
            'frequency' => ['required', 'in:daily,weekly,monthly,termly'],
            'format' => ['required', 'in:pdf,excel,csv'],
            'recipientIds' => ['required', 'array', 'min:1', 'max:50'],
            'recipientIds.*' => ['integer'],
        ]);

        $report = CustomReport::where('school_id', $this->school->id)->where('created_by', auth()->id())->findOrFail($this->reportId);

        $recipients = User::whereHas('schools', fn ($query) => $query->where('schools.id', $this->school->id))
            ->whereIn('users.id', $this->recipientIds)->pluck('users.id')
            ->map(fn ($id): array => ['recipientType' => 'user', 'recipientId' => (int) $id, 'channel' => 'email'])->values()->all();

        if ($recipients === []) {
            $this->addError('recipientIds', __('Choose recipients from this school’s staff.'));

            return;
        }

        app(ScheduleCustomReportAction::class)->execute($report->id, $this->frequency, $recipients, $this->format);

        $this->reset(['reportId', 'recipientIds']);
        $this->toast(__('Schedule created.'));
    }

    public function render(): View
    {
        $mine = CustomReport::where('school_id', $this->school->id)->where('created_by', auth()->id())->orderBy('name')->get(['id', 'name']);

        return view('intelligence::insights.reports.schedule', [
            'reports' => $mine,
            'schedules' => CustomReportSchedule::with('report:id,name')->where('school_id', $this->school->id)->whereIn('report_id', $mine->pluck('id'))->orderBy('next_run_at')->get(),
            'colleagues' => User::whereHas('schools', fn ($query) => $query->where('schools.id', $this->school->id))->orderBy('name')->limit(300)->get(['users.id', 'users.name']),
        ]);
    }
}
