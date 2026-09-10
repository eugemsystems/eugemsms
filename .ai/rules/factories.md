---
paths:
  - Modules/Core/database/factories/GradeLevelFactory.php
---

# Factories

## GradeLevelFactory's default ordinal must stay globally unique-safe
`ordinal`/`code` are only unique per-school in the DB (`unique(['school_id','ordinal'])`), but the factory used `fake()->unique()->numberBetween(1, 13)` — a *global*, process-wide unique pool. Once enough tests create enough default GradeLevels in one run (e.g. TenancyIsolationTest, which creates one per registered tenant model per school), Faker's unique cache exhausts with "Maximum retries of 10000 reached". Fixed by widening the range to 1-32000 (still within smallint bounds). If you need a realistic small ordinal in a test, pass it explicitly via `->create(['ordinal' => ...])` rather than relying on the factory default.
