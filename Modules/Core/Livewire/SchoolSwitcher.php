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
 *
 * Second bugfix, same report, recurring ("sometimes working sometimes
 * not ... just blinking but nothing changes"): `$this->redirect(Referer)`
 * depends on the browser having actually sent a `Referer` header for
 * this fetch() call and on `window.location.href = <that URL>` reliably
 * forcing a real reload — neither is guaranteed (a referrer policy, an
 * ad blocker, or the URL happening to already match `window.location`
 * exactly can all make it a same-URL no-op that just re-renders nothing,
 * which reads as "it blinked"). `$this->js('window.location.reload()')`
 * is unconditional: it always re-fetches the exact page currently open
 * from the server, no header or URL comparison involved. A flashed toast
 * (read back by `resources/js/app.js` after the reload — see
 * `layouts/app/sidebar.blade.php`) gives explicit success feedback,
 * since a same-content reload can otherwise look like nothing happened.
 *
 * Third bugfix, same report, still recurring ("just flicking and
 * nothing" — reported repeatedly because every prior fix here was
 * real but incomplete, not because it didn't work): reloading the
 * CURRENT url is actively wrong on any `{school}`-scoped page (schools/
 * {school}/settings, /roles, /sessions/..., /houses, /structure, ...).
 * `InteractsWithSchool::bootInteractsWithSchool()` sets `SchoolContext`
 * from THAT URL'S OWN `{school}` segment on every request, unconditionally
 * — so reloading `/schools/{old-school}/settings` after switching to a
 * different school re-derives `SchoolContext` from the old school
 * again, every time, no matter what `UserSessionPreference` says. The
 * switch was never failing; the page you were on was simply pinned to
 * the old school by its own URL. `SchoolContext::currentId() !== null`
 * is true precisely on these pages (nothing else ever sets it — see
 * `ActiveSchoolResolver`'s docblock), so that's the signal to bounce to
 * the dashboard — never `{school}`-scoped — instead of reloading in
 * place.
 */
final class SchoolSwitcher extends Component
{
    public function switchTo(int $schoolId): void
    {
        $school = app(SwitchActiveSchoolAction::class)->execute(new SwitchSchoolData(
            userId: (int) Auth::id(),
            schoolId: $schoolId,
        ));

        session()->flash('serp_toast', ['text' => __('Switched to :school.', ['school' => $school->name]), 'variant' => 'success']);

        if (SchoolContext::currentId() !== null) {
            $this->redirect(route('dashboard'), navigate: true);

            return;
        }

        $this->js('window.location.reload()');
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
