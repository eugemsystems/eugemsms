<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\DataObjects;

use Closure;
use Illuminate\Support\Collection;

/**
 * Book I COM-02 §3 ⭐/BR-COM-02-003. `allowedFields` is the whitelist a
 * rule author may reference — `AutomationEntityRegistry::assertFieldAllowed()`
 * refuses anything else at save time. `scanner` returns the candidate
 * records for one school, already resolved to a flat field map plus
 * the context a notification dispatch needs.
 */
final readonly class AutomationEntityDefinition
{
    /**
     * @param  array<int, string>  $allowedFields
     * @param  Closure(int): Collection<int, AutomationScanRecord>  $scanner
     */
    public function __construct(
        public string $entityKey,
        public array $allowedFields,
        public Closure $scanner,
    ) {}
}
