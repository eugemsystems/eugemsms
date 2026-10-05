<?php

declare(strict_types=1);

namespace Modules\Intelligence\Livewire\Insights\Reports;

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
use Modules\Intelligence\Domain\Actions\RunSavedReportAction;
use Modules\Intelligence\Livewire\Concerns\PresentsReportResults;
use Modules\Intelligence\Models\CustomReport;
use Modules\Intelligence\Models\ReportShare;

/**
 * `Intelligence\Reports\Shared` (Book J INT-01 §5 — no permission
 * beyond school membership). Reports shared with the signed-in user,
 * directly or through a role. Running one re-evaluates it against the
 * *viewer's* permissions, never the author's (BR-INT-01-005,
 * AC-INT-01-002), so a field the viewer may not read simply does not
 * come back.
 */
#[Title('Reports shared with me')]
#[Layout('layouts.app')]
final class Shared extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use PresentsReportResults;

    public ?int $ranReportId = null;

    /** @var array{rows: array<int, array<string, mixed>>, rowCount: int, durationMs: int, wasRedirected: bool, redirectReason: ?string, truncated: bool}|null */
    public ?array $result = null;

    public ?string $error = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
    }

    public function run(int $reportId): void
    {
        $report = CustomReport::where('school_id', $this->school->id)->whereIn('id', $this->sharedReportIds())->findOrFail($reportId);
        $this->error = null;

        try {
            $this->result = $this->presentResult(app(RunSavedReportAction::class)->execute($report->id, $this->user()));
            $this->ranReportId = $report->id;
        } catch (InsufficientScopeException|InvalidArgumentException $exception) {
            $this->result = null;
            $this->error = $exception->getMessage();
        }
    }

    public function render(): View
    {
        return view('intelligence::insights.reports.shared', [
            'reports' => CustomReport::with('creator:id,name')->where('school_id', $this->school->id)->whereIn('id', $this->sharedReportIds())->orderByDesc('id')->limit(100)->get(),
        ]);
    }

    /**
     * @return array<int, int>
     */
    private function sharedReportIds(): array
    {
        $user = $this->user();
        $roleIds = $user->roles()->pluck('roles.id')->all();

        return ReportShare::where('school_id', $this->school->id)
            ->where(fn ($query) => $query
                ->where(fn ($direct) => $direct->where('shared_with_type', 'user')->where('shared_with_id', $user->id))
                ->orWhere(fn ($viaRole) => $viaRole->where('shared_with_type', 'role')->whereIn('shared_with_id', $roleIds)))
            ->pluck('report_id')->all();
    }

    private function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
