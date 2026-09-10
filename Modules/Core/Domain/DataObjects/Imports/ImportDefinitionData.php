<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Imports;

final readonly class ImportDefinitionData
{
    /**
     * @param  array<int, string>  $dependsOn  other import keys that must have a completed batch first
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $moduleCode,
        public string $importerClass,
        public string $requiredPermission,
        public ?string $description = null,
        public array $dependsOn = [],
        public bool $isRollbackable = true,
        public int $sortOrder = 0,
    ) {}
}
