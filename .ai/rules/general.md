---
paths:
  - '**/*'
---

# General

## Test/PHPStan/Pint output is auto-compacted to JSON — don't fight it
Running `vendor/bin/pest`, `php artisan test`, or the PHPStan phar in this environment returns a compact JSON summary (`{"tool":"pest","result":...,"tests":N,"passed":N,"assertions":N}` / `{"tool":"phpstan","result":...,"errors":N,"error_details":{...}}`) instead of normal CLI output — even when output is redirected to a file. This is intentional harness/Boost tooling, not a bug and not something in this repo's config (checked composer.json, phpunit.xml, tests/Pest.php — none of it is here).

Trust the JSON: for pest, compare `tests` vs `passed` (equal = everything passed) rather than the top-level `result` field, which can read "failed" even at 100% pass (likely reflects a nonzero exit code from something unrelated, e.g. a risky-test warning). For phpstan, `error_details` gives full file/line/message/identifier — that's the real diagnostic, use it directly instead of trying to coax verbose/raw output out of the runner.
