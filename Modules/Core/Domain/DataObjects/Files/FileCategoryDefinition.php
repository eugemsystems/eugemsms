<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Files;

final readonly class FileCategoryDefinition
{
    /**
     * @param  array<int, string>  $allowedMimes
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $moduleCode,
        public array $allowedMimes,
        public int $maxSizeBytes,
        public bool $isSensitive = false,
        public bool $generatesVariants = false,
        public bool $requiresExpiry = false,
        public ?int $retentionYears = null,
    ) {}
}
