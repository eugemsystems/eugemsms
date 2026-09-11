<?php

declare(strict_types=1);

namespace Modules\Core\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Modules\Core\Domain\Actions\Sessions\SwitchSessionAction;
use Modules\Core\Domain\DataObjects\Sessions\SwitchSessionData;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Models\AcademicYear;

/**
 * `Core\SessionSwitcher` (Book A CORE-03 §5) — a header dropdown, not a
 * page, mirroring `SchoolSwitcher`. Switching only takes effect for the
 * *next* request (`SwitchSessionAction` just persists the preference —
 * see that action's own docblock), so a full page reload is deliberate.
 */
final class SessionSwitcher extends Component
{
    public function switchTo(int $academicYearId, ?int $termId): void
    {
        $schoolId = SchoolContext::currentId() ?? Auth::user()?->primarySchool()?->id;

        if ($schoolId === null) {
            return;
        }

        app(SwitchSessionAction::class)->execute(new SwitchSessionData(
            userId: (int) Auth::id(),
            schoolId: $schoolId,
            academicYearId: $academicYearId,
            termId: $termId,
        ));

        $this->redirect(request()->header('Referer') ?? route('dashboard'));
    }

    public function render(): View
    {
        $schoolId = SchoolContext::currentId() ?? Auth::user()?->primarySchool()?->id;

        $years = $schoolId !== null
            ? AcademicYear::query()->where('school_id', $schoolId)->with('terms')->orderByDesc('starts_on')->get()
            : collect();

        return view('core::livewire.session-switcher', [
            'schoolId' => $schoolId,
            'years' => $years,
            'currentYearId' => SessionContext::isSet() ? SessionContext::yearId() : null,
            'currentTermId' => SessionContext::isSet() ? SessionContext::termId() : null,
        ]);
    }
}
