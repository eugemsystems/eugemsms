<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support;

use Modules\Core\Domain\Exceptions\MissingSessionContextException;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\Term;

/**
 * Request-scoped holder for the active `(academic_year, term)` session
 * (Volume 1 Part 4.2, Book A Part 1.5). Resolved by the
 * `SetSessionContext` middleware. `isCurrentLiveTerm()` drives the
 * historical-view banner (BR-CORE-03-007) — a hard requirement on every
 * admin screen, never decorative.
 */
final class SessionContextManager
{
    private ?AcademicYear $year = null;

    private ?Term $term = null;

    public function year(): AcademicYear
    {
        return $this->year ?? throw new MissingSessionContextException('No active academic year context is set for this request.');
    }

    public function term(): ?Term
    {
        return $this->term;
    }

    public function yearId(): ?int
    {
        return $this->year?->id;
    }

    public function termId(): ?int
    {
        return $this->term?->id;
    }

    public function isCurrentLiveTerm(): bool
    {
        return $this->term !== null && (bool) $this->term->is_current;
    }

    public function set(AcademicYear $year, ?Term $term = null): void
    {
        $this->year = $year;
        $this->term = $term;
    }

    public function clear(): void
    {
        $this->year = null;
        $this->term = null;
    }

    public function isSet(): bool
    {
        return $this->year !== null;
    }
}
