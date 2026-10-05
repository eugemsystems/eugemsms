<?php

declare(strict_types=1);

namespace Modules\Facilities\Livewire\Utilisation;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\School;
use Modules\Facilities\Domain\Actions\ComputeUtilisationReportAction;
use Modules\Facilities\Models\BookableResource;

/**
 * `Utilisation\Index` (Book H2 OPS-05 §4/BR-OPS-05-009,
 * `facilities.report.view`). Utilisation and hire revenue, per
 * resource, for the active term.
 */
#[Title('Utilisation')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;

    /** @var array<int, array{resourceCode: string, resourceName: string, bookingCount: int, bookedHours: float, hireRevenueMinor: int, currency: string}> */
    public array $rows = [];

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
        $this->authorizePermission('facilities.report.view');

        $termId = SessionContext::termId();

        if ($termId === null) {
            return;
        }

        $resources = BookableResource::where('school_id', $school->id)->orderBy('code')->get();
        $action = app(ComputeUtilisationReportAction::class);

        foreach ($resources as $resource) {
            $result = $action->execute($resource->id, $termId);

            $this->rows[] = [
                'resourceCode' => $resource->code,
                'resourceName' => $resource->name,
                'bookingCount' => $result->bookingCount,
                'bookedHours' => $result->bookedHours,
                'hireRevenueMinor' => $result->hireRevenueMinor,
                'currency' => $result->currency,
            ];
        }
    }

    public function render(): View
    {
        return view('facilities::utilisation.index');
    }
}
