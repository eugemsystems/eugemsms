<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Sessions;

final readonly class ChecklistItemResult
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        public string $code,
        public string $label,
        public bool $passed,
        public bool $blocking,
        public string $message = '',
        public array $details = [],
    ) {}
}
