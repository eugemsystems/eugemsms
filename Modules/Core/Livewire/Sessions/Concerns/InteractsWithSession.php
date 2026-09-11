<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Sessions\Concerns;

use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Models\School;
use Modules\Core\Models\UserSessionPreference;

/**
 * Resolves and sets `SessionContext` the same way `SetSessionContext`
 * middleware does (Book A CORE-03 §5, BR-CORE-03-005), but from a
 * screen's own `mount()` rather than middleware — no route group in this
 * app runs automatic context-resolution middleware yet (see
 * `InteractsWithSchool`'s own docblock), so every session-aware screen
 * resolves its context explicitly, the same way school context is
 * resolved per-screen today. Unlike the middleware, a school with no
 * academic year yet is left unset rather than rejected — some of these
 * very screens (`Years`, `YearWizard`) exist to create that first year,
 * and populating this is a courtesy for the historical-view banner, not
 * a hard requirement to render the page.
 */
trait InteractsWithSession
{
    protected function loadSessionContext(School $school): void
    {
        $user = auth()->user();
        $preferredYear = null;
        $preferredTerm = null;

        if ($user !== null) {
            $preference = UserSessionPreference::where('user_id', $user->id)
                ->where('school_id', $school->id)
                ->first();

            $preferredYear = $preference?->academicYear;
            $preferredTerm = $preference?->term;
        }

        $year = $preferredYear ?? $school->currentAcademicYear();

        if ($year === null) {
            return;
        }

        $term = $preferredTerm ?? $year->currentTerm();

        SessionContext::set($year, $term);
    }
}
