---
paths:
  - 'Modules/*/Models/*.php'
---

# Modules Models

## Importing HasUlid is not the same as using it — a ulid column with no HasUlid trait fails NOT NULL on every insert
Found 4 models in one pass (`TimetableSlot`, `TimetableGenerationRun`, `TimetableConstraint`, `LessonSubstitution` — Book E ACA-03) that had `use Modules\Core\Domain\Concerns\HasUlid;` in the `use` import statements but never actually wrote `use HasUlid;` inside the class body. The migration's `$table->char('ulid', 26)->unique();` has no default, so every single insert fails with a NOT NULL constraint violation at the DB level — this only surfaces the first time a test actually creates one of these models, not at write-time or via static analysis.

**Why this slipped through:** PHPStan/Pint don't flag an unused `use` import specifically when the imported name also happens to match a trait name that's merely absent from the class body — Pint's `no_unused_imports` only fires for imports that are *never referenced anywhere in the file*, and the import line itself doesn't count as a reference needing the trait to be applied.

**How to apply:** whenever a model's docblock declares `@property string $ulid`, grep the class body itself (not just the `use` import list) for a bare `use HasUlid;` trait statement before trusting the model compiles a working row. When building several similar models in one batch (e.g. a new module's whole schema), verify each one's trait usage individually rather than assuming a copy-pasted skeleton carried it over correctly — this bug hit 4 different models built back-to-back in the same session, suggesting a copy-paste origin that dropped the trait line while keeping the import.

## Explicit $table when the class name doesn't pluralize to the migration's table name
When a spec names a table something Eloquent's pluralizer won't derive from the model class (e.g. `ScriptCustodyLogEntry` -> Eloquent guesses `script_custody_log_entries`, but the migration created `script_custody_log`), you MUST set `protected $table = '...'` explicitly. This only surfaces at query time ("no such table"), never via static analysis or Pint — the tenancy isolation test (`Modules/Core/tests/Feature/TenancyIsolationTest.php`, which exercises every `TenantModelRegistry`-registered model) is what actually catches it. Whenever a model's name doesn't read as the exact singular of its migration's `Schema::create('...')` argument, double-check and set `$table` explicitly rather than trusting the convention.
