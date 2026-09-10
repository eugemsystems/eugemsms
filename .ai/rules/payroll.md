---
paths:
  - 'Modules/Payroll/**'
---

# Payroll

## Carbon's diffInDays() returns a noisy float — round before feeding bcmath
`CarbonInterface::diffInDays()` between a start-of-day instant and an end-of-day instant (e.g. `startOfMonth()` to `endOfMonth()`) returns a float that lands a hair below the true integer — observed as `29.999999999988` for what should be exactly 29 — because Carbon computes the diff from sub-second-precision timestamps.

Casting that float straight to a string and feeding it into `bcsub($x, $y, 1)` (scale 1) TRUNCATES rather than rounds, silently losing about a tenth of a day. In `UnpaidLeaveProrationCalculator` this produced a bogus proration factor (~0.9967 instead of exactly 1) and a wrong payslip gross even with zero unpaid-leave rows — the bug looked like a leave-query problem but was actually float noise from `diffInDays()`.

Fix: `(int) round($start->diffInDays($end))` before any bcmath touches it, never a raw cast. Applies to any day-count arithmetic derived from `diffInDays()`/`diffInHours()` etc. feeding bcmath — round to the nearest sane unit first.
