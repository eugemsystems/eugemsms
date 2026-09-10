<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Settings;

final readonly class DefineCustomFieldData
{
    /**
     * @param  array<int, mixed>|null  $options
     * @param  array<int, string>|null  $visibleToRoles
     */
    public function __construct(
        public int $schoolId,
        public string $entityType,
        public string $key,
        public string $label,
        public string $dataType,
        public ?int $actingUserId = null,
        public ?string $description = null,
        public ?array $options = null,
        public ?string $validationRules = null,
        public bool $isRequired = false,
        public bool $isSearchable = false,
        public bool $isExposedInApi = true,
        public bool $isPrintable = false,
        public ?array $visibleToRoles = null,
        public ?string $groupLabel = null,
        public int $sortOrder = 0,
    ) {}
}
