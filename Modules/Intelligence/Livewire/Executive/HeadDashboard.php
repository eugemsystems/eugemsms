<?php

declare(strict_types=1);

namespace Modules\Intelligence\Livewire\Executive;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Comms\Domain\DataObjects\WidgetResolverResult;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Intelligence\Domain\Actions\BuildExecutiveDashboardAction;
use Modules\Intelligence\Domain\Actions\GenerateExecutiveDigestAction;
use Modules\Intelligence\Domain\Actions\GetCommodityAnomalyDashboardAction;
use Modules\Intelligence\Domain\Actions\GetEnrolmentComparativeAction;
use Modules\Intelligence\Livewire\Concerns\BuildsKpiTiles;
use Modules\Intelligence\Models\ExecutiveDigest;

/**
 * `Intelligence\Executive\HeadDashboard` (Book J INT-02 §4,
 * `executive.dashboard.view`). Colour-coded KPI tiles, the executive
 * widgets resolved through COM-03's own registry
 * (`BuildExecutiveDashboardAction`, BR-INT-02-001), a three-year
 * enrolment comparative read from warehouse snapshots — never a live
 * cross-year aggregation (BR-INT-02-008, AC-INT-02-004) — and the count
 * of stock-consumption anomalies FIN-09 already detects (this module
 * only surfaces them). "Send me today's digest" generates the
 * exceptions-only digest for the signed-in user through CORE-09.
 */
#[Title('Head dashboard')]
#[Layout('layouts.app')]
final class HeadDashboard extends Component
{
    use AuthorizesPermissions;
    use BuildsKpiTiles;
    use InteractsWithSchool;
    use Toasts;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('executive.dashboard.view');
    }

    public function sendDigest(): void
    {
        $this->authorizePermission('executive.dashboard.view');

        $year = AcademicYear::where('school_id', $this->school->id)->where('is_current', true)->first();

        if ($year === null) {
            $this->toast(__('There is no current academic year to summarise.'), 'danger');

            return;
        }

        $digest = app(GenerateExecutiveDigestAction::class)->execute($this->school->id, $year->id, $this->currentUser());

        $this->toast($digest->content_summary['all_green'] ?? false
            ? __('All indicators are on target today.')
            : __(':n indicator(s) need attention — digest sent.', ['n' => count($digest->content_summary['exceptions'] ?? [])]));
    }

    public function render(): View
    {
        $thisYear = (int) now()->year;
        $anomalies = app(GetCommodityAnomalyDashboardAction::class)->execute($this->school->id)['consumption_anomalies'];

        return view('intelligence::executive.head-dashboard', [
            'tiles' => $this->kpiTiles(),
            'widgets' => array_map(fn (WidgetResolverResult $widget): array => ['title' => $widget->title, 'summary' => $widget->summary], app(BuildExecutiveDashboardAction::class)->execute($this->currentUser(), $this->school->id)),
            'comparative' => app(GetEnrolmentComparativeAction::class)->execute($this->school->id, [$thisYear - 2, $thisYear - 1, $thisYear]),
            'openAnomalies' => count(array_filter($anomalies, fn ($anomaly): bool => $anomaly->status !== 'resolved' && $anomaly->status !== 'closed')),
            'anomaliesUrl' => Route::has('stores.inventory.anomalies') ? route('stores.inventory.anomalies', $this->school) : null,
            'todaysDigest' => ExecutiveDigest::where('school_id', $this->school->id)->where('recipient_user_id', auth()->id())->whereDate('digest_date', now()->toDateString())->first(),
        ]);
    }
}
