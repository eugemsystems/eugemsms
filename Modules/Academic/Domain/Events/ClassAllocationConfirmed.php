<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Events;

use Modules\Academic\Models\ClassAllocation;

/**
 * Book D ACA-02 §9.
 */
final class ClassAllocationConfirmed
{
    public function __construct(public readonly ClassAllocation $allocation) {}
}
