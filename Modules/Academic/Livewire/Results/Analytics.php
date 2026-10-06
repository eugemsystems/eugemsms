<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Results;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\BuildResultsAnalyticsAction;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\School;
use Modules\Core\Models\SchoolClass;

/**
 * `Results\Analytics` (Book D ACA-05 §6, `academic.result.view`). Subject,
 * class and teacher roll-ups for the current term and a trend across recent
 * terms. Facts for a person to read, not a ranking of teachers.
 */
#[Title('Performance analytics')]
#[Layout('layouts.app')]
final class Analytics extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;

    public ?int $classId = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
        $this->authorizePermission('academic.result.view');
    }

    public function render(): View
    {
        $termId = SessionContext::termId();

        return view('academic::report-cards.analytics', [
            'classes' => SchoolClass::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'data' => $termId === null ? ['subjects' => [], 'classes' => [], 'teachers' => [], 'trend' => []] : app(BuildResultsAnalyticsAction::class)->execute($termId, $this->classId),
        ]);
    }
}
