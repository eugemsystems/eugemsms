---
paths:
  - 'Modules/Core/Livewire/**'
---

# Livewire

## SchoolContext/SessionContext must be re-set every request, not just in mount() — use the boot{Trait}() hook
`InteractsWithSchool::loadSchool()`/`InteractsWithSession::loadSessionContext()` only ran from `mount()`, which Livewire calls ONCE on the initial full-page load — never again on subsequent `wire:click`/`wire:model` requests (each is a fresh HTTP request/container; no route group here runs an automatic context-resolution middleware). Since `SchoolContext`/`SessionContext` are per-request singletons, every `BelongsToSchool`/`BelongsToSession` query in a screen's own `render()` after the very first interaction silently returned ZERO rows (`SchoolScope`'s deliberate `1=0` when context is unset) — this was the real cause of "clicking a row/creating a record doesn't show until I refresh the page" across Years/Structure/Houses and likely every other `InteractsWithSchool` screen.

Fixed by adding `public function bootInteractsWithSchool()`/`bootInteractsWithSession()` (Livewire's `boot{TraitName}()` lifecycle-hook convention — MUST be `public`, not `protected`, or Livewire's `Wrapped::__call()` reports "method does not exist") that re-applies `SchoolContext::set($this->school)` on every request, guarded via `(new ReflectionProperty($this, 'school'))->isInitialized($this)` (not `isset()` — PHPStan flags `isset()` on a non-nullable typed property as always-true even though it's correct at runtime for an uninitialized one; same Reflection technique `GuardsPeriodStateWrites` already uses).

Gotcha for tests: `Livewire::test()` keeps the whole `->call()` chain in ONE PHP process/container, so `SchoolContext` set during `mount()` silently survives between calls and this bug is invisible to a normal test. To actually exercise the fresh-request boundary, call `SchoolContext::clear()` between the initial `Livewire::test(...)` and a follow-up `->call()`/`->set()` — see the regression tests in `SessionScreensTest.php`/`SchoolScreensTest.php`.
