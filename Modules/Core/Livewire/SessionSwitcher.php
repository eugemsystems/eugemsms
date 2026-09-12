<?php

declare(strict_types=1);

namespace Modules\Core\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Modules\Core\Domain\Actions\Sessions\SwitchSessionAction;
use Modules\Core\Domain\DataObjects\Sessions\SwitchSessionData;
use Modules\Core\Domain\Support\ActiveSchoolResolver;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Models\AcademicYear;

/**
 * `Core\SessionSwitcher` (Book A CORE-03 §5) — a header dropdown, not a
 * page, mirroring `SchoolSwitcher`. Switching only takes effect for the
 * *next* request (`SwitchSessionAction` just persists the preference —
 * see that action's own docblock), so a full page reload is deliberate.
 *
 * Bugfix (2026-09-12, user-reported: "sometimes navigating on other
 * links is hiding the term selection"): same root cause as
 * `SchoolSwitcher`'s own bugfix note — falling back straight to
 * `primarySchool()` instead of `ActiveSchoolResolver::resolveId()` meant
 * this widget silently reverted to the user's PRIMARY school (not
 * whichever school they'd actually switched to) the moment they landed
 * on any page with no `{school}` in its own URL, which could easily have
 * no current academic year configured — making the whole dropdown
 * disappear (`@if ($years->isNotEmpty())` in the view).
 */
final class SessionSwitcher extends Component
{
    public function switchTo(int $academicYearId, ?int $termId): void
    {
        $schoolId = SchoolContext::currentId() ?? ActiveSchoolResolver::resolveId(Auth::user());

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
        $schoolId = SchoolContext::currentId() ?? ActiveSchoolResolver::resolveId(Auth::user());

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
