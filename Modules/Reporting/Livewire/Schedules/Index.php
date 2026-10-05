<?php

declare(strict_types=1);

namespace Modules\Reporting\Livewire\Schedules;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Reporting\Domain\Actions\CreateReportDefinitionAction;
use Modules\Reporting\Domain\Actions\CreateReportScheduleAction;
use Modules\Reporting\Domain\DataObjects\CreateReportDefinitionData;
use Modules\Reporting\Domain\DataObjects\CreateReportScheduleData;
use Modules\Reporting\Models\ReportDefinition;
use Modules\Reporting\Models\ReportSchedule;

/**
 * `Reports\Schedules\Index` (Book H3 FIN-12 §2/§6,
 * `reporting.report.schedule`). Also hosts creating a
 * `ReportDefinition` row — a new, narrow, create-only Action this
 * pass added (`CreateReportDefinitionAction`): `report_definitions`
 * had a migration/model/factory but no Action anywhere ever created
 * one, which would have left `CreateReportScheduleAction`'s own
 * required `reportDefinitionId` FK with nothing real to point to.
 * No scheduler drains `next_run_at` yet (`ReportingServiceProvider`'s
 * own docblock) — this screen records the schedule honestly, it does
 * not pretend delivery happens.
 */
#[Title('Scheduled reports')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $definitionCode = '';

    public string $definitionName = '';

    public string $reportType = 'income_statement';

    public ?int $reportDefinitionId = null;

    public string $scheduleName = '';

    public string $frequency = 'monthly';

    public string $format = 'pdf';

    public string $recipientEmails = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('reporting.report.schedule');
    }

    public function createDefinition(): void
    {
        $this->validate([
            'definitionCode' => ['required', 'string', 'max:30'],
            'definitionName' => ['required', 'string', 'max:150'],
            'reportType' => ['required', 'in:trial_balance,income_statement,balance_sheet,cash_flow,departmental,custom'],
        ]);

        app(CreateReportDefinitionAction::class)->execute(new CreateReportDefinitionData(
            schoolId: $this->school->id,
            code: $this->definitionCode,
            name: $this->definitionName,
            reportType: $this->reportType,
        ));

        $this->reset(['definitionCode', 'definitionName']);
        $this->toast(__('Report definition created.'));
    }

    public function createSchedule(): void
    {
        $this->validate([
            'reportDefinitionId' => ['required', 'integer'],
            'scheduleName' => ['required', 'string', 'max:150'],
            'frequency' => ['required', 'in:daily,weekly,monthly,termly'],
            'format' => ['required', 'in:pdf,excel,both'],
            'recipientEmails' => ['required', 'string'],
        ]);

        app(CreateReportScheduleAction::class)->execute(new CreateReportScheduleData(
            schoolId: $this->school->id,
            reportDefinitionId: (int) $this->reportDefinitionId,
            name: $this->scheduleName,
            frequency: $this->frequency,
            recipients: array_map('trim', explode(',', $this->recipientEmails)),
            format: $this->format,
        ));

        $this->reset(['scheduleName', 'recipientEmails']);
        $this->toast(__('Schedule created — delivery is not yet automated; this records the schedule.'));
    }

    public function render(): View
    {
        return view('reporting::schedules.index', [
            'definitions' => ReportDefinition::where('school_id', $this->school->id)->orderBy('code')->get(),
            'schedules' => ReportSchedule::where('school_id', $this->school->id)->orderByDesc('id')->get(),
        ]);
    }
}
