---
paths:
  - 'Modules/Sport/**'
---

# Sport

Book H2 `OPS-07` — Sport, Houses & Co-curricular, the final module of
Book H2. Admin-UI pass built fourth in this sub-pass's own order
(OPS-04 → OPS-05 → OPS-06 → OPS-07, independent modules per the
book's own build order).

## What was built (7 screens, `Livewire/{Activities,Membership,Teams,Fixtures,Houses,Awards,Equipment}/`)

`Activities\Index`, `Membership\Index` (consent and medical clearance
status shown per member — `JoinActivityAction` itself is the only
gate), `Teams\Index`, `Fixtures\Index` ⭐ (folds the spec's own,
separate "Squad selection" and "Results" screens into this fixture's
own action bar — schedule, confirm, select squad, mark `BRD-02` roll
status `fixture`, record result, record injury — the same
list+detail folding `Maintenance\WorkOrders\Index` (Book H2 OPS-02)
already established for this book), `Houses\Leaderboard` ⭐⭐ (renamed
from the spec's own bare `Houses\Index` — see the collision note
below; folds house-competition creation and manual points into the
leaderboard read), `Awards\Index`, `Equipment\Index` (gap-fill, see
below).

## ⭐⭐ A real cross-module Livewire component-name collision was found and fixed here — read this before naming any new module-root component

`Modules\Sport\Livewire\Houses\Index` (this screen's name if copied
literally from the spec's own `Ops\Houses\Leaderboard` entry read as
a bare "Houses" folder) collides with `Modules\Core\Livewire\Houses\Index`
(Book A CORE-02's own house *register* screen, already shipped). Both
are exactly `Houses/Index.php` one level under their own module's
`Livewire/` root — the same shape of collision `Modules\Farm`'s
`Transfers/Index` vs. `Modules\Stores`'s `Transfers/Index` hit in the
prior OPS-01/02/03 pass. Caught by running the duplicate-name check
*before* writing the Houses screen's test, not after:

```
find . -path "*/Livewire/*" -name "*.php" | grep -v vendor | sed -E 's#.*/Livewire/##; s#\.php$##' | sort | uniq -d
```

Fixed by renaming the class to `Modules\Sport\Livewire\Houses\Leaderboard`
end to end (namespace, file, view path `sport::houses.leaderboard`,
route import, test import) — which also happens to match the spec's
own full component name (`Ops\Houses\Leaderboard`) more closely than
the bare `Index` would have. Rerun the check above before adding the
next module-root screen anywhere in this codebase; it is clean as of
this pass.

## Two screens are gap-fills — the spec's own §4 table names neither

- `Houses\Leaderboard` only names a read-only leaderboard in the
  spec's screen table (`activities.view`). Creating a house
  competition and recording manual points
  (`CreateHouseCompetitionAction`/`RecordHouseCompetitionResultAction`/
  `RecordManualHousePointsAction`) have no screen of their own
  anywhere in that table — folded into this one screen rather than
  left unreachable, gated by `activities.manage` for the write side.
- `Equipment\Index` — `IssueEquipmentAction`/`ReturnEquipmentAction`/
  `CheckOverdueEquipmentAction` exist (`BR-OPS-07-011`, equipment
  tracked through `FIN-09`'s `FixedAsset` register) but the spec's
  screen table names no screen for any of them.

## Permissions are registered under ONE module code, `SPORT`, matching the spec's own `activities.*` namespace

`manage` (activities, membership, and both gap-fill screens above),
`team.manage`, `fixture.manage`, `view` (leaderboard), `award.manage`.

## No gap-filling Actions were needed — only gap-filling screens

Every Sport table already had a real Action creating its rows before
this pass; the two gaps above are screens, not missing domain logic.

## Admin-UI test file

`Modules/Sport/tests/Feature/Admin/SportAdminUiTest.php`, own
`sportAdminFixture()`/`sportAdminUser()` pair (also needs a real
`admission` numbering series and a `SchoolSection`/`GradeLevel` pair,
since squad selection's own AC test creates a real `Student` via
`CreateStudentAction`). Three tests: a permission-refusal test, a
"renders every screen" smoke test, and a dedicated AC-OPS-07-001 test
(a learner with a declared, active `affects_physical_activity`
medical condition and no medical clearance recorded is blocked from
squad selection by name, and the fixture's own `squad_student_ids`
stays null — not partially populated).
