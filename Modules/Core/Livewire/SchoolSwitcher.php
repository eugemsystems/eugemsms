<?php

declare(strict_types=1);

namespace Modules\Core\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Modules\Core\Domain\Actions\Schools\SwitchActiveSchoolAction;
use Modules\Core\Domain\DataObjects\Schools\SwitchSchoolData;
use Modules\Core\Domain\Support\SchoolContext;

/**
 * `Core\SchoolSwitcher` (Book A CORE-02 §5) — a header dropdown, not a
 * page. Always available to an authenticated user with more than one
 * assigned school. A full page reload after switching is deliberate: the
 * new active school only takes effect once `SetSchoolContext` middleware
 * re-resolves it on the *next* request (see `SwitchActiveSchoolAction`).
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
        // Falls back to the user's primary school outside the CORE-02
        // routes that actually run `serp.school-context` (dashboard,
        // settings) — this dropdown is rendered from the shared app
        // shell, so it has to make sense everywhere, not just where the
        // middleware happens to have resolved a context already.
        $currentSchoolId = SchoolContext::currentId() ?? Auth::user()?->primarySchool()?->id;

        return view('core::livewire.school-switcher', [
            'schools' => Auth::user()?->schools()->orderBy('name')->get() ?? collect(),
            'currentSchoolId' => $currentSchoolId,
        ]);
    }
}
