<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

use Illuminate\Support\Collection;

/**
 * Book D ACA-01 §3. `isValid` reflects only `block`-severity violations
 * (BR-ACA-01-008) — a `warn` violation is surfaced but does not by
 * itself make a selection invalid; the caller records an
 * acknowledgement instead of retrying.
 */
final readonly class SelectionResult
{
    /**
     * @param  Collection<int, Violation>  $warnings
     * @param  Collection<int, Violation>  $blocks
     */
    public function __construct(
        public bool $isValid,
        public Collection $warnings,
        public Collection $blocks,
    ) {}

    public function hasWarnings(): bool
    {
        return $this->warnings->isNotEmpty();
    }
}
