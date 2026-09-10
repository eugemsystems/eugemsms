<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\DataObjects;

/**
 * Book I COM-03 §3/BR-COM-03-002. A widget's SUMMARY only — a
 * balance figure, a count, a next date — never the full underlying
 * dataset (that's one tap, one focused call, away).
 */
final readonly class WidgetResolverResult
{
    /**
     * @param  array<string, mixed>  $summary
     */
    public function __construct(
        public string $key,
        public string $title,
        public array $summary,
    ) {}

    public function hasData(): bool
    {
        return $this->summary !== [];
    }
}
