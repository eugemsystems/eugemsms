<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Sessions;

use Modules\Core\Models\PeriodRollover;

/**
 * Book A CORE-03 §5 — shared, mutable state passed through every handler
 * in one roll-over run, so a later handler (e.g. `VerifyInvariantHandler`
 * at order 910) can read what an earlier one computed (e.g. the totals
 * `CarryForwardLearnerBalancesHandler` carried at order 70) without every
 * handler re-deriving it or handlers depending on each other directly.
 */
final class RolloverContext
{
    /**
     * @var array<string, mixed>
     */
    private array $shared = [];

    public function __construct(
        public readonly PeriodRollover $rollover,
    ) {}

    public function put(string $key, mixed $value): void
    {
        $this->shared[$key] = $value;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->shared[$key] ?? $default;
    }
}
