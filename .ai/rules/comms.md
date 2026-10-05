---
paths:
  - 'Modules/Comms/**'
---

# Comms

## Livewire component names collide across modules — put every Comms screen under a unique namespace
Livewire derives a component's name from its class path relative to the `Livewire::addLocation()` namespace, so `Modules\Comms\Livewire\Gateways\Index` and Finance's own `Modules\Finance\Livewire\Gateways\Index` both resolve to `gateways.index`. `Livewire::test()`/routes mount the right class, but every subsequent update request (`->call()`, `wire:click`) re-hydrates whichever class the name resolves to — here Finance's, which silently ran `finance.gateway.manage` against the Comms screen and 403'd, surfacing only as "Invalid Livewire snapshot structure".

How to apply: before naming a new Livewire directory, `find Modules -path '*Livewire/<Dir>*'`. Comms screens live under `Livewire/Messaging/` (COM-01) for this reason; give later COM modules their own unique top-level directory too (e.g. `Automation/`, `Portal/`, `Calendar/`, `Meetings/`, `Feedback/`) and check for clashes first.

## COM-01 admin screens: credentials are write-only, webhook log is metadata-only
`MessageGateway.credentials`/`webhook_secret` are `encrypted` casts and must never be selected into a view (BR-COM-01-001) — the gateway list selects an explicit non-secret column list. `messaging_gateway_webhooks.raw_headers`/`raw_payload` can carry recipient numbers and are never displayed; the log selects receipt metadata only.

## Gate permissions in views via PermissionScopeResolver, not `@can`
No view in this codebase uses `@can`; scoped (own/assigned/section/school) permissions are resolved by `PermissionScopeResolver`, and I did not verify that Laravel's Gate honours them. Compute a `$canManage` boolean in the component's `render()` via `app(PermissionScopeResolver::class)->has(auth()->user(), '...', PermissionScope::Own)` and branch on that. Every mutating Livewire method must still call `authorizePermission()` itself — hiding a button is not enforcement.

## COM-02 Builder looks its rule up by ulid inside mount()
Other modules (Welfare, Academic) bind the record as a route parameter and add an `abort_unless($model->school_id === $school->id, 404)`; both work. `Builder` instead takes the rule's ulid as a plain string and queries `AutomationRule::where('school_id', …)->where('ulid', …)->firstOrFail()` after `loadSchool()`, so the tenant scope applies and a cross-school ulid is a not-found. This was a precaution, not a verified binding bug. Name the held model property differently from the route parameter (`$automationRule`, not `$rule`).

## Livewire public properties are client-tamperable — re-derive ids and rows server-side before a write
`AttendanceReview` holds `$sessionId` and `$suggestions` as public properties, but a client can set any of them. `confirm()` therefore re-runs `PreviewAttendanceReconciliationAction` for the (school-scoped) meeting and takes only the teacher's chosen *statuses* from component state; the session id and each matched student id come from the server. Do the same wherever a screen writes to another module on the strength of ids it displayed.

## addError() persists across Livewire requests — reset the bag when a method doesn't call validate()
`validate()` clears the error bag; a method that only calls `addError()` does not, so a stale message survives a later successful call. Call `$this->resetErrorBag()` at the start of such methods (found when `AttendanceReview::confirm()` kept showing "choose a status" after a successful confirm).
