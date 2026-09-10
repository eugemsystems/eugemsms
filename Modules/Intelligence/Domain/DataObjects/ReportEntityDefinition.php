<?php

declare(strict_types=1);

namespace Modules\Intelligence\Domain\DataObjects;

/**
 * Book J INT-01 §2 ⭐/BR-INT-01-001/004.
 */
final readonly class ReportEntityDefinition
{
    /**
     * @param  class-string  $baseModelClass
     * @param  array<int, string>  $allowedJoinEntityKeys
     */
    public function __construct(
        public string $entityKey,
        public string $moduleCode,
        public string $baseModelClass,
        public bool $defaultSchoolScoped = true,
        public array $allowedJoinEntityKeys = [],
    ) {}
}
