<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Schools\Concerns;

use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\School;
use ReflectionProperty;

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

    /**
     * `SchoolContext` is a per-request singleton (Book A Part 1.5) — it
     * only lives as long as the PHP request that sets it. `mount()` sets
     * it once, on the initial full-page load, but Livewire does NOT
     * re-run `mount()` on subsequent requests (every `wire:click`/
     * `wire:model` round trip after that is a fresh HTTP request that
     * hydrates the component's public properties, including `$school`,
     * without re-invoking `mount()`). No route group in this app runs an
     * automatic context-resolution middleware for these screens (see
     * this trait's own class docblock and `InteractsWithSession`'s), so
     * without re-setting it here, `SchoolContext` is simply unset for
     * every follow-up request — and `SchoolScope` treats a missing
     * context as "zero rows", not "unfiltered" (Volume 1 principle #4).
     * That silently emptied out every `BelongsToSchool` query a screen's
     * own `render()` ran after the very first interaction (e.g. the
     * terms panel on `Core\Sessions\Years` after `selectYear`, or a
     * freshly created section/house not appearing until a hard reload
     * re-ran `mount()`).
     *
     * `boot{TraitName}()` is Livewire's own trait-hook convention — it
     * runs on every request, initial and subsequent alike, unlike
     * `mount()`. Guarded via `ReflectionProperty::isInitialized()` —
     * the same technique `GuardsPeriodStateWrites` already uses to read
     * a mixed-in property reflectively — because on the very first
     * request `boot()` fires before `mount()` has assigned
     * `$this->school` at all, and a plain `isset()` on a non-nullable
     * typed property is flagged by PHPStan as always-true even though,
     * at runtime, it correctly reports `false` for an uninitialized
     * typed property.
     */
    public function bootInteractsWithSchool(): void
    {
        if ((new ReflectionProperty($this, 'school'))->isInitialized($this)) {
            SchoolContext::set($this->school);
        }
    }
}
