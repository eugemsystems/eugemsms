<?php

declare(strict_types=1);

namespace Modules\Intelligence\Domain\DataObjects;

/**
 * Book J INT-01 §3 ⭐/BR-INT-01-008 (AC-INT-01-005). `wasRedirected`
 * is the literal "redirected... rather than executing live" the
 * acceptance criterion asks for — `rows` is empty and `redirectReason`
 * explains why whenever it's true, never a partial/truncated result
 * silently passed off as complete.
 */
final readonly class ReportResult
{
    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function __construct(
        public array $rows,
        public int $rowCount,
        public int $durationMs,
        public bool $wasRedirected = false,
        public ?string $redirectReason = null,
    ) {}
}
