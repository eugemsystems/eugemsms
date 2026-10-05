<?php

declare(strict_types=1);

namespace Modules\Transport\Livewire\RouteCosts;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Transport\Domain\Actions\ComputeRouteCostingAction;
use Modules\Transport\Models\Route;

/**
 * `RouteCosts\Index` (Book H2 OPS-01 §5/BR-OPS-01-018, `transport.report.view`).
 */
#[Title('Route costing')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    public ?int $routeId = null;

    public string $periodStart = '';

    public string $periodEnd = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('transport.report.view');
        $this->periodStart = now()->startOfMonth()->toDateString();
        $this->periodEnd = now()->endOfMonth()->toDateString();
    }

    public function render(): View
    {
        $result = $this->routeId !== null
            ? app(ComputeRouteCostingAction::class)->execute($this->routeId, Carbon::parse($this->periodStart), Carbon::parse($this->periodEnd))
            : null;

        return view('transport::route-costs.index', [
            'routes' => Route::where('school_id', $this->school->id)->orderBy('code')->get(),
            'result' => $result,
        ]);
    }
}
