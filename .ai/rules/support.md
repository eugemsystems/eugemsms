---
paths:
  - 'Modules/Finance/Domain/Support/*.php'
---

# Support

## Collection<K,V> is invariant in PHPStan/Larastan — use plain array<int,V> for override params passed across method boundaries
Passing a `Collection<int, array{...}>` through an optional parameter from one method to another (e.g. `compute()` forwarding `$proposedSubjects` to `perSubject()`) triggers "Template type TValue on class Illuminate\Support\Collection is not covariant" even when both `@param` annotations are textually identical. This is a real Larastan limitation, not a shape mismatch.

Fix: don't use `Collection` for a value that's only ever `count()`'d and iterated — declare it as a plain `array<int, array{...}>` (or narrower, but NOT `list<>` unless you actually force reindexing with `->values()` first, since `->all()` alone doesn't prove list-ness to PHPStan). Build it with `->map(...)->values()->all()` at the source. See `FeeLineCalculator::compute()`/`perSubject()` and `PreviewIndicativeFeeAction` (Book D ACA-02 §7) for the pattern — the `$proposedSubjects` override that lets `PreviewIndicativeFeeAction` price a subject set with no `learner_subject_enrolments` rows yet.

**Why:** hit this converting `FeeStructureResolver`/`FeeLineCalculator` to accept a proposed (not-yet-enrolled) subject set for [[indicative-fee-preview]]. Switching `Collection` → plain `array` is not "widening to silence a check" — it's using the right type for data that never needs Collection's methods.
