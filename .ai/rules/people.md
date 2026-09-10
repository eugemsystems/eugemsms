---
paths:
  - 'Modules/People/**'
---

# People

## PPL-03 is an intentionally minimal Guardian/Liability slice
Built solely to close FIN-03's billed-party dependency (Book B FIN-03 §3/§4 explicitly needs `fee_liabilities` and the liability resolution algorithm). Built: `guardians`, `student_guardian` (the rights matrix — only `is_primary_contact`/`is_emergency_contact`/`is_fee_responsible`/`may_collect_learner`/`may_view_full_balance`/`has_court_restriction` kept), `fee_liabilities`, `LiabilityResolver` (percentage rules round independently against a shared base — never batched through `Money::allocate()`, so a genuinely partial percentage split correctly falls through to the residual guardian per BR-FIN-03-007), `CreateGuardianAction`/`LinkGuardianToStudentAction`/`DeactivateStudentGuardianAction`/`CreateFeeLiabilityAction`.

Deliberately NOT built: `households` (sibling discounts/combined statements), `sponsorships` (budget envelopes, performance conditions), `guardian_verification` (gate terminal ID checks), portal provisioning, duplicate detection, the contact-update approval queue, and the liability-designer screen's "shares must total 100%" live check (the resolver itself tolerates and correctly resolves an incomplete split instead).

`CreateStudentAction` (PPL-01) still does not create or require a guardian — a student with zero guardians is possible in this codebase and will throw `NoFeeResponsibleGuardianException` the first time anything tries to invoice them. Every test/fixture that calls `IssueInvoicesForAssignmentAction` (directly, or via `CommitBillingRunAction`) must link at least one `is_fee_responsible` guardian first.
