---
paths:
  - 'Modules/Facilities/**'
---

# Facilities

Book H2 `OPS-05` — Facilities Booking & External Hire. Admin-UI pass
built second in this sub-pass's own order (OPS-04 → OPS-05 → OPS-06
→ OPS-07, independent modules per the book's own build order).

## What was built (5 screens, `Livewire/{Resources,Calendar,Request,Hire,Utilisation}/`)

`Resources\Index` ⭐ (gap-fill, see below), `Calendar\Index`
(read-only week view, `facilities.view`), `Request\Index` ⭐ (booking
request with a live clash check — `CheckResourceAvailabilityAction`
run on every field change before submit, and again for real inside
`RequestBookingAction` itself on submit; also handles cancel and
recurring expansion), `Hire\Index` ⭐ (folds the spec's own, separate
"Approvals" screen into the external hire lifecycle — approve,
record deposit, confirm, complete, assess damage & refund), `Utilisation\Index`
(per-resource booking count/hours/revenue for the active term).

## `Resources\Index` is a gap-filling screen — the spec's own §4 table names none

The spec's own screen table (`Resource calendar`, `Booking request`,
`Approvals`, `External hire`, `Utilisation`) never names a screen
that creates a `BookableResource` row, even though
`CreateBookableResourceAction` exists and every other screen depends
on that register already being populated. Built here under a new
permission, `facilities.manage`, rather than silently reusing
`facilities.book` (request) or `facilities.hire.manage` (hire) for an
unrelated register-management action.

## `Approvals` is folded into `Hire\Index`, not built as a separate screen

The only thing `facilities.approve` ever approves in this book is an
external hire (`BR-OPS-05-003` — "external hire requires approval, a
signed contract, and a deposit before confirmation"). There is no
internal-booking approval path (`RequestBookingAction` auto-approves
an internal booking with no further gate). A separate "Approvals"
screen would therefore show exactly the same rows `Hire\Index`
already lists. The `facilities.approve` permission is still
registered (for a future role split) even though this pass's own
`Hire\Index` gates its whole screen on `facilities.hire.manage`.

## The AC-OPS-05-001 test needed a real published timetable, not a stub

Proving "teaching always wins" (`BR-OPS-05-001`) against
`CheckResourceAvailabilityAction`'s own timetable-clash branch
requires a genuinely published `Modules\Academic` timetable with a
real slot at a venue — not a bare row. The reliable fixture recipe,
confirmed by `Modules\Academic\tests\Feature\Aca03TimetableTest.php`:
build a `PeriodStructure` with `cycleDays: 1` (every weekday then
resolves to cycle day 1 via `CycleDayResolver`, side-stepping all
day-of-cycle modulo arithmetic), create one `PeriodSlot` at the hours
you intend to clash with, create the `Timetable`/`TimetableSlot`
through `CreateTimetableSlotAction`, then call
`PublishTimetableAction` (which sets `effective_from` to "today" if
unset, `effective_to` stays null). The booking window under test must
land on or after that `effective_from` date — the fixture picks the
next weekday on/after "now" for both the term's own window and the
booking itself, so the test never depends on which day of the week it
happens to run.

## Permissions are registered under ONE module code, `FACILITIES`

`manage` (resource register, this pass's own gap-fill), `view`
(calendar), `book` (request), `approve` (registered, unused by this
pass's own screen gating — see above), `hire.manage` (the whole
external hire lifecycle), `report.view` (utilisation).

## No gap-filling Actions were needed beyond the one documented screen gap

Every `ResourceBooking` lifecycle transition (`RequestBookingAction`
→ `ApproveExternalHireAction` → `ConfirmBookingAction` →
`CompleteBookingAction` → `AssessDamageAndRefundDepositAction`, plus
`CancelBookingAction`/`ExpandRecurringBookingAction`) already existed
before this pass.

## Admin-UI test file

`Modules/Facilities/tests/Feature/Admin/FacilitiesAdminUiTest.php`,
own `facilitiesAdminFixture()`/`facilitiesAdminUser()` pair. Three
tests: a permission-refusal test, a "renders every screen" smoke
test, and a dedicated AC-OPS-05-001 test (an external hire request
that overlaps a real published teaching timetable slot is refused,
naming the clash, and creates no `ResourceBooking` row at all).
