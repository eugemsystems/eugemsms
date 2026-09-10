---
paths:
  - 'Modules/People/Domain/Actions/*Application*.php'
---

# Actions

## Intake.places_accepted is a deposit-time counter, not an enrolment-time one — conversion's capacity check must use `>`, not `hasCapacity()`
`PayAcceptanceDepositAction` increments `Intake::places_accepted` at deposit-payment time, not at conversion — BR-PPL-02-008 gates *conversion*, not the deposit itself, so a school can accept more deposits than places (a real-world overbooking pattern) and the hard refusal only bites when someone tries to convert past target_places.

Because of that, `ConvertApplicationToStudentAction`'s capacity check must NOT reuse `Intake::hasCapacity()` (which is `places_accepted < target_places`) — by the time of conversion, `places_accepted` already includes *this* application's own reservation, so a `<` check would incorrectly block converting the very applicant who exactly fills the last place. The correct check is `places_accepted > target_places` (strictly greater): it only refuses once some *other* applicant's deposit has already pushed the intake over, not this one's own.
