<?php

declare(strict_types=1);

namespace Modules\Core\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Modules\Core\Domain\Actions\Schools\SwitchActiveSchoolAction;
use Modules\Core\Domain\DataObjects\Schools\SwitchSchoolData;
use Modules\Core\Domain\Support\ActiveSchoolResolver;
use Modules\Core\Domain\Support\SchoolContext;

/**
 * `Core\SchoolSwitcher` (Book A CORE-02 §5) — a header dropdown, not a
 * page. Always available to an authenticated user with more than one
 * assigned school. A full page reload after switching is deliberate.
 *
 * Bugfix (2026-09-12, user-reported: "when i choose a different school
 * ... nothing changes"): `SetSchoolContext` middleware — the thing this
 * class's docblock used to say re-resolves the switch on the next
 * request — is never actually applied to any real route in this app
 * (see `Modules\Core\Domain\Support\ActiveSchoolResolver`'s own
 * docblock for the full story). `render()` now calls
 * `ActiveSchoolResolver::resolveId()` directly instead of jumping
 * straight to `primarySchool()`, so a switch actually shows up on any
 * page with no `{school}` in its own URL (dashboard, Users, Feature
 * flags, and this widget itself) — a `{school}`-scoped page still
 * correctly shows whatever school its URL names, unaffected by this.
 */
final class SchoolSwitcher extends Component
{
    public function switchTo(int $schoolId): void
    {
        app(SwitchActiveSchoolAction::class)->execute(new SwitchSchoolData(
            userId: (int) Auth::id(),
            schoolId: $schoolId,
        ));

        $this->redirect(request()->header('Referer') ?? route('dashboard'));
    }

    public function render(): View
    {
        $currentSchoolId = SchoolContext::currentId() ?? ActiveSchoolResolver::resolveId(Auth::user());

        return view('core::livewire.school-switcher', [
            'schools' => Auth::user()?->schools()->orderBy('name')->get() ?? collect(),
            'currentSchoolId' => $currentSchoolId,
        ]);
    }
}
