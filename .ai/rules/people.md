---
paths:
  - 'Modules/People/**'
---

# People

## PPL-03 is an intentionally minimal Guardian/Liability slice
Built solely to close FIN-03's billed-party dependency (Book B FIN-03 §3/§4 explicitly needs `fee_liabilities` and the liability resolution algorithm). Built: `guardians`, `student_guardian` (the rights matrix — only `is_primary_contact`/`is_emergency_contact`/`is_fee_responsible`/`may_collect_learner`/`may_view_full_balance`/`has_court_restriction` kept), `fee_liabilities`, `LiabilityResolver` (percentage rules round independently against a shared base — never batched through `Money::allocate()`, so a genuinely partial percentage split correctly falls through to the residual guardian per BR-FIN-03-007), `CreateGuardianAction`/`LinkGuardianToStudentAction`/`DeactivateStudentGuardianAction`/`CreateFeeLiabilityAction`.

Deliberately NOT built: `households` (sibling discounts/combined statements), `sponsorships` (budget envelopes, performance conditions), `guardian_verification` (gate terminal ID checks), portal provisioning, duplicate detection, the contact-update approval queue, and the liability-designer screen's "shares must total 100%" live check (the resolver itself tolerates and correctly resolves an incomplete split instead).

`CreateStudentAction` (PPL-01) still does not create or require a guardian — a student with zero guardians is possible in this codebase and will throw `NoFeeResponsibleGuardianException` the first time anything tries to invoice them. Every test/fixture that calls `IssueInvoicesForAssignmentAction` (directly, or via `CommitBillingRunAction`) must link at least one `is_fee_responsible` guardian first.

## PPL-01's admin UI pass found four tables in its own spec that were never built
Four of `students`' own sibling tables — `student_documents`, `student_prior_schools`, `student_siblings`, `student_timeline` — have no migration, model, or Action at all (verified directly: `Modules/People/database/migrations/` has no file for any of them). This was never flagged anywhere before this pass. Building `People\Students\Documents`/`PriorHistory`/`Siblings`/a real `Timeline` tab needs the backend built first — that is new backend work, not an admin-UI retrofit, and is deliberately **not** done here. `People\Students\Show`'s own tabs (Overview/Academic/Financial/Guardians) are what's real today; "Academic" folds billing-attribute history in as the closest stand-in for a timeline.

Also not built, confirmed by the same check: `AllocateToClassAction`/`AllocateToHouseAction` (the balancing algorithms `People\Allocation\Classes`/`Houses` would need), `BulkAllocateClassesAction` (draft→review→commit), `GenerateIdCardAction`, and `MergeDuplicateStudentsAction` (`DetectPossibleDuplicatesAction` only ever flags, via a stateless check with no persisted queue — see its own docblock). None of these five screens exist in this pass either.

`PreviewBillingAttributeChangeAction` (new, for `People\Students\ChangeAttribute`'s live fee-impact preview) reuses `FeeStructureResolver`/`FeeLineCalculator` against an **unsaved clone** (`$student->replicate()`) of the real learner with the proposed attribute applied — both classes read straight off whatever `Student` instance they're given (including its lazy relations, re-resolved correctly once a FK like `grade_level_id` changes on the clone), so this works without persisting anything or touching `ChangeBillingAttributeAction`'s own history write.

## `DetectPossibleDuplicatesAction`'s name+DOB match was silently broken
`date_of_birth` is declared `$table->date(...)`, but the stored value round-trips as a full datetime string ("2015-03-10 00:00:00", confirmed via `getRawOriginal()`) — the same class of trap `.ai/rules/finance.md` already documents for `effective_at`. The original code compared it with a plain `where('date_of_birth', $date->toDateString())` (bare "Y-m-d"), which **never matched**, silently, for every school since this action was built — caught only by this pass's own admin-UI test, not by any prior test. Fixed to `whereDate(...)`, which compares the calendar date portably regardless of how the driver stores it. Check any future exact-date comparison against a `date()`-cast column the same way.

## Livewire cannot hold an arbitrary DTO object (or an array of them) as a public property
`BillingAttributeChangePreview`/`DuplicateCandidate` objects, assigned directly to a public Livewire property, either throw ("Property type not supported in Livewire for property") or — worse — silently come back empty after the next server round-trip, with no error at all (an array of `DuplicateCandidate` objects did this: the action found the match, but `$component->get('duplicates')` read back `[]` after the next `->call()`). Convert any DTO/DTO-array to a plain array (or scalars) the moment it's assigned to a public property; only read the rich object inside the same method call that produced it.
