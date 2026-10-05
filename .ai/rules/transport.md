---
paths:
  - 'Modules/Transport/**'
---

# Transport

Book H2 `OPS-01` — Transport & Fleet Management 🇿🇼. Admin-UI pass built
in the book's own order, second after `OPS-02` (Maintenance & Works,
`Modules/Operations`) since vehicles reference its `maintenance_assets`/
`work_orders` for real.

## What was built (11 screens, `Livewire/{Fleet,Compliance,Drivers,Routes,Assignment,Trips,Manifest,Fuel,FuelAnomalies,Incidents,RouteCosts}/`)

`Fleet\Index` (register + ground/reactivate, folded one screen),
`Compliance\Index` 🇿🇼 (the seven statutory types from BR-OPS-01-002,
each tracked with independent expiry), `Drivers\Index`, `Routes\Index`
(folds zone management alongside route+stop creation — the spec names no
separate "Zones" screen, only "Routes & stops ... zone assignment"),
`Assignment\Index` (shows the resulting termly transport fee live,
BR-OPS-01-006), `Trips\Index` (schedule + depart + odometer),
`Manifest\Show` (tap-to-board/alight), `Fuel\Index`, `FuelAnomalies\Index`
⭐, `Incidents\Index`, `RouteCosts\Index`.

## 🇿🇼 Statutory figures are configuration, never hard-coded

`transport.compliance_alert_days` (default `[60,30,7]`) is read live
through `SettingResolver` in `Compliance\Index::render()` — the screen
displays whatever the resolved value is, and never duplicates `[60, 30, 7]`
as a literal. `transport.fuel_variance_tolerance_percent`,
`transport.fuel_rolling_window_days`, and `transport.min_refuel_hours`
are likewise only ever read through the setting resolver inside the
Actions themselves (`RecordFuelLogAction`/`CheckCumulativeFuelAnomalyAction`)
— no screen recomputes or re-states them.

## One new gap-filling Action: `ReactivateVehicleAction`

`GroundVehicleAction` shipped in the backend pass with no reverse
transition. Without it, `Compliance\Index`'s own "grounded vehicles" view
(and `Fleet\Index`'s status column) would have been a one-way trip —
once a certificate was renewed, there would have been no path back to
`active`. Added narrow and forward-only in the other direction: only a
`grounded` vehicle may reactivate, and `ScheduleTripAction`'s own
expired-compliance check still runs independently on the next trip
regardless of this flag (a reactivated vehicle with a still-expired
other certificate is still refused by that check, not this one).

## No `UpdateXAction` exists anywhere in this module

Every mutation is either a create (`CreateVehicleAction`/`CreateDriverAction`/
`CreateRouteAction`/`CreateTransportZoneAction`) or a named lifecycle
transition (`GroundVehicleAction`/`ReactivateVehicleAction`/
`ScheduleTripAction`/`DepartTripAction`/`RecordTripOdometerAction`/
`RecordBoardingAction`/`RecordFuelAnomalyExplanationAction`) — confirmed
by `ls Modules/Transport/Domain/Actions | grep ^Update` returning
nothing, the same create-only/lifecycle-only precedent every book since
Book D has documented.

## Permissions are registered under ONE module code, `TRANSPORT`

The spec's own §5 screen table already names every permission under a
single `transport.*` namespace (`transport.view`/`.manage`/`.assign`/
`.trip.manage`/`.drive`/`.fuel.record`/`.fuel.review`/`.incident.manage`/
`.report.view`) — unlike Book H1's `Modules\Stores`, which genuinely
needed a four-way split because its own spec names four distinct
top-level namespaces. One `PermissionRegistry::register('TRANSPORT', [...])`
call in `TransportServiceProvider::registerPermissions()`.

## `Modules\Transport\Models\Route` collides with `Illuminate\Support\Facades\Route` — alias the facade, not the model

`TransportServiceProvider::registerLivewireRoutes()` needs both the
`Route` facade (to register routes) and `Modules\Transport\Models\Route`
is already imported elsewhere in the same file (the tenant-model
registry). Importing the facade as plain `Route` fails to compile
("Cannot use ... as Route because the name is already in use"). Fixed by
importing the facade as `use Illuminate\Support\Facades\Route as RouteFacade;`
and calling `RouteFacade::middleware('web')->group(...)` — the model
keeps its own plain, more-frequently-used name.

## Admin-UI test file

`Modules/Transport/tests/Feature/Admin/TransportAdminUiTest.php`, own
`transportAdminFixture()`/`transportAdminUser()` pair (last-dot-split
permission parser, matching every other admin-UI test file in this
codebase). Four tests: a permission-refusal test, a "renders every
screen" smoke test, a dedicated AC-OPS-01-001 test (`ScheduleTripAction`
itself — never duplicated client-side — refuses a vehicle with an
expired certificate of fitness, naming it), and a dedicated AC-OPS-01-002
test (`Assignment\Index`'s `previewFeeMinor` reads the correct termly fee
off the picked stop's own zone before the assignment is even confirmed).
