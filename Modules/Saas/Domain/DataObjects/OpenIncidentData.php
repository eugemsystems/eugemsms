<?php

declare(strict_types=1);

namespace Modules\Saas\Domain\DataObjects;

final readonly class OpenIncidentData
{
    /**
     * @param  array<int, string>  $affectedComponents
     */
    public function __construct(
        public string $title,
        public array $affectedComponents,
        public string $severity,
        public string $initialMessage,
        public bool $isPublic = true,
    ) {}
}
