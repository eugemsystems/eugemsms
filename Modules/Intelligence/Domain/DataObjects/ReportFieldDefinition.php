<?php

declare(strict_types=1);

namespace Modules\Intelligence\Domain\DataObjects;

/**
 * Book J INT-01 §2 ⭐/BR-INT-01-001/002/003.
 */
final readonly class ReportFieldDefinition
{
    /**
     * @param  array<int, mixed>|null  $enumOptions
     */
    public function __construct(
        public string $moduleCode,
        public string $entityKey,
        public string $fieldKey,
        public string $label,
        public string $dataType,
        public string $requiredPermission,
        public bool $isFilterable = true,
        public bool $isGroupable = true,
        public bool $isAggregatable = false,
        public bool $isSensitive = false,
        public ?array $enumOptions = null,
    ) {}
}
