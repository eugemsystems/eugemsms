---
paths:
  - 'Modules/Academic/**'
---

# Academic

## Academic module is an intentionally minimal ACA-01/ACA-02 slice
Built solely to close the ACA-02 subject-count dependency FIN-02 (Book B) was specified against (BR-FIN-02-004) — not the full Book D spec. Built: curriculum_frameworks, subject_groups, subjects, subject_selection_rules (block/warn rule engine covering min_total/max_total/min_from_group/max_from_group/required_subject/mutually_exclusive only), learner_subject_enrolments (⭐ the billing source of truth), subject_enrolment_changes (append-only proration snapshot), SubjectEnrolmentQuery (the FIN-02 contract), EnrolSubjectAction/DropSubjectAction.

Deliberately NOT built yet: `pathways` table (Student.pathway stays a plain string), `level_subject_offerings` (so min_compulsory/one_per_option_block/prerequisite rule types are unsupported — skipped, not silently passed), `subject_prerequisites`, `syllabi`, `class_allocations`, `teaching_groups` (no capacity enforcement), `subject_selection_submissions` (no guardian-approval screen flow), and CORE-07 approval-chain wiring for the subject-change cutoff (past-cutoff just throws, no ApprovalRequest row is created yet).

Critically: `SubjectEnrolmentAdded`/`SubjectEnrolmentDropped` are emitted but have NO listener yet — FIN-02's billing reaction (raise the per-subject charge / credit note, steps 7-14 of the add/drop billing flow) is FIN-02's own scope, still unbuilt. Don't assume billing_status ever moves past 'pending' until FIN-02 ships that listener.

## subject_selection_rules keeps its plain-string pathway column, not pathway_id FK
Book D ACA-01's spec defines `subject_selection_rules.pathway_id` as a FK to the new `pathways` table. When `pathways` was built (completing ACA-01), this column was deliberately LEFT as its already-shipped plain string `pathway` rather than migrated to `pathway_id` — refactoring it would have touched `SubjectSelectionRuleEngine`, `EnrolSubjectAction`, and the already-passing ACA-02 billing tests for a completeness detail with no functional gap (string matching works identically to FK matching here since `Student.pathway` is also a plain string, not itself an FK).

New ACA-01 tables that don't have this legacy constraint (`level_subject_offerings`) use the proper `pathway_id` FK from the start. If a future pass needs `subject_selection_rules` to join against the `pathways` catalog (e.g. for the rule-builder screen to show pathway names, not codes), that's the point to do this migration — not before.

## Academic never depends on Finance directly — cross-module fee data flows through the caller, not a new Action-to-Action call
Every existing cross-module reference between these two modules runs Finance → Academic (Finance imports Academic's models/events: `BillMidTermSubjectChangeAction`, `RaiseMidTermSubjectChangeBillingListener`, `FeeStructureResolver`'s `SubjectEnrolmentQuery` dependency). Academic has never imported anything from `Modules\Finance`, and that should stay true.

When an Academic workflow needs a Finance-computed figure (e.g. `SubjectSelectionSubmission.indicative_fee_minor`, per Book D ACA-02 §7/BR-ACA-02-016), don't have the Academic Action call into Finance to compute it. Instead: Finance exposes its own standalone Action (`Modules\Finance\Domain\Actions\PreviewIndicativeFeeAction`), the caller (a controller, or whatever layer sits above both modules) calls that action first and passes the resulting figure into the Academic action's Data object as a plain nullable field. `SubmitSubjectSelectionAction` just stores what it's given — it never resolves the fee itself.

**Why:** avoids a first-ever Academic→Finance dependency, keeping the module graph acyclic exactly as it's been throughout the codebase. See `SubmitSubjectSelectionAction`'s docblock for where this was decided. Related: [[Collection<K,V> is invariant in PHPStan/Larastan — use plain array<int,V> for override params passed across method boundaries]].
