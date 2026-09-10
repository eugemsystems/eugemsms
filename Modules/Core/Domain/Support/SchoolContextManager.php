<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support;

use Modules\Core\Domain\Exceptions\MissingSchoolContextException;
use Modules\Core\Models\School;

/**
 * Request-scoped holder for the active school. Resolved by the
 * `SetSchoolContext` middleware and available for injection; never written
 * to from inside an Action (Book A Part 1.5) — an Action receives context
 * on its DTO instead.
 */
final class SchoolContextManager
{
    private ?School $school = null;

    public function current(): ?School
    {
        return $this->school;
    }

    public function currentId(): ?int
    {
        return $this->school?->id;
    }

    public function set(School $school): void
    {
        $this->school = $school;
    }

    public function clear(): void
    {
        $this->school = null;
    }

    public function assertSet(): void
    {
        if ($this->school === null) {
            throw new MissingSchoolContextException('No active school context is set for this request.');
        }
    }
}
