<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Schools\Concerns;

use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\School;

/**
 * Shared plumbing for every screen scoped to one specific school reached
 * via a `{school}` route parameter (Book A CORE-02 §5): authorises the
 * viewer is assigned to it, then sets `SchoolContext` so the screen's
 * own `BelongsToSchool` queries (sections, grade levels, classes,
 * houses, module entitlements) resolve correctly without each screen
 * re-deriving it. `core.school.*`/`core.structure.manage`/`core.module.manage`
 * permission checks named in the spec are not yet enforced here —
 * CORE-05 hasn't shipped a permission system to check against; every
 * screen still requires assignment to the school, which is the one
 * authorisation mechanism that already exists.
 */
trait InteractsWithSchool
{
    public School $school;

    protected function loadSchool(School $school): void
    {
        abort_unless(auth()->user()?->isAssignedToSchool($school->id) === true, 403);

        $this->school = $school;

        SchoolContext::set($school);
    }
}
