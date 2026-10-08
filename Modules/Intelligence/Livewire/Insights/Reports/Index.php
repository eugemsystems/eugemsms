<?php

declare(strict_types=1);

namespace Modules\Intelligence\Livewire\Insights\Reports;

use App\Concerns\Toasts;
use App\Models\User;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\InsufficientScopeException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Intelligence\Domain\Actions\DeleteCustomReportAction;
use Modules\Intelligence\Domain\Actions\GenerateReportExportAction;
use Modules\Intelligence\Domain\Actions\RunSavedReportAction;
use Modules\Intelligence\Domain\Actions\ShareReportAction;
use Modules\Intelligence\Domain\Actions\UpdateCustomReportAction;
use Modules\Intelligence\Domain\DataObjects\UpdateCustomReportData;
use Modules\Intelligence\Livewire\Concerns\PresentsReportResults;
use Modules\Intelligence\Models\CustomReport;
use Modules\Intelligence\Models\ReportShare;
use Symfony\Component\HttpFoundation\Response;

/**
 * `Intelligence\Reports\Index` (Book J INT-01 §5, `report.build`) — the
 * signed-in user's own reports: run, edit, delete, and share with a
 * colleague. Sharing only names *who may run* a report; what each
 * person gets back is re-evaluated against their own permissions
 * every time (BR-INT-01-005), which the Action does and the share
 * form says. Only the author may edit, delete, or share.
 * **Gap closed**: edit renames/re-describes a report via
 * `UpdateCustomReportAction` (the underlying query itself is not
 * re-opened here — a different query is a new report, built again in
 * `Reports\Builder`); delete is a real `DeleteCustomReportAction` hard
 * delete, cascading its schedules and shares.
 */
#[Title('My reports')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use PresentsReportResults;
    use Toasts;

    public ?int $shareReportId = null;

    public ?int $shareWithUserId = null;

    public ?int $ranReportId = null;

    public ?int $editReportId = null;

    public string $editName = '';

    public string $editDescription = '';

    public string $editChartType = '';

    /** @var array{rows: array<int, array<string, mixed>>, rowCount: int, durationMs: int, wasRedirected: bool, redirectReason: ?string, truncated: bool}|null */
    public ?array $result = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('report.build');
    }

    public function run(int $reportId): void
    {
        $this->authorizePermission('report.build');

        $report = $this->ownReport($reportId);

        try {
            $this->result = $this->presentResult(app(RunSavedReportAction::class)->execute($report->id, $this->user()));
            $this->ranReportId = $report->id;
        } catch (InsufficientScopeException|InvalidArgumentException $exception) {
            $this->result = null;
            $this->toast($exception->getMessage(), 'danger');
        }
    }

    public function export(int $reportId, string $format): ?Response
    {
        $this->authorizePermission('report.build');

        $report = $this->ownReport($reportId);

        try {
            $result = app(RunSavedReportAction::class)->execute($report->id, $this->user());
            $file = app(GenerateReportExportAction::class)->execute($result, $format, $report->name);
        } catch (InsufficientScopeException|InvalidArgumentException $exception) {
            $this->toast($exception->getMessage(), 'danger');

            return null;
        }

        return response($file->content, 200, [
            'Content-Type' => $file->mimeType,
            'Content-Disposition' => 'attachment; filename="'.$file->filename.'"',
        ]);
    }

    public function editStart(int $reportId): void
    {
        $report = $this->ownReport($reportId);

        $this->editReportId = $report->id;
        $this->editName = $report->name;
        $this->editDescription = (string) $report->description;
        $this->editChartType = (string) $report->chart_type;
    }

    public function saveEdit(): void
    {
        $this->authorizePermission('report.build');

        $this->validate([
            'editName' => ['required', 'string', 'max:150'],
            'editDescription' => ['nullable', 'string', 'max:1000'],
            'editChartType' => ['nullable', 'in:,bar,line,pie,table'],
        ]);

        $report = $this->ownReport((int) $this->editReportId);

        app(UpdateCustomReportAction::class)->execute(new UpdateCustomReportData(
            reportId: $report->id,
            name: $this->editName,
            description: $this->editDescription !== '' ? $this->editDescription : null,
            chartType: $this->editChartType !== '' ? $this->editChartType : null,
        ));

        $this->reset(['editReportId', 'editName', 'editDescription', 'editChartType']);
        $this->toast(__('Report updated.'));
    }

    public function delete(int $reportId): void
    {
        $this->authorizePermission('report.build');

        $report = $this->ownReport($reportId);

        app(DeleteCustomReportAction::class)->execute($report->id);

        $this->toast(__('Report deleted.'));
    }

    public function share(): void
    {
        $this->authorizePermission('report.build');

        $this->validate(['shareReportId' => ['required', 'integer'], 'shareWithUserId' => ['required', 'integer']]);

        $report = $this->ownReport((int) $this->shareReportId);
        $colleague = User::whereHas('schools', fn ($query) => $query->where('schools.id', $this->school->id))->findOrFail($this->shareWithUserId);

        app(ShareReportAction::class)->execute($report->id, 'user', $colleague->id, (int) auth()->id());

        $this->reset(['shareReportId', 'shareWithUserId']);
        $this->toast(__(':name can now run this report; they see only what their own permissions allow.', ['name' => $colleague->name]));
    }

    public function render(): View
    {
        $reports = CustomReport::where('school_id', $this->school->id)->where('created_by', auth()->id())->orderByDesc('id')->limit(100)->get();

        return view('intelligence::insights.reports.index', [
            'reports' => $reports,
            'shareCounts' => ReportShare::whereIn('report_id', $reports->pluck('id'))->selectRaw('report_id, count(*) as total')->groupBy('report_id')->toBase()->pluck('total', 'report_id'),
            'colleagues' => User::whereHas('schools', fn ($query) => $query->where('schools.id', $this->school->id))->where('users.id', '!=', auth()->id())->orderBy('name')->limit(300)->get(['users.id', 'users.name']),
        ]);
    }

    private function ownReport(int $reportId): CustomReport
    {
        return CustomReport::where('school_id', $this->school->id)->where('created_by', auth()->id())->findOrFail($reportId);
    }

    private function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
