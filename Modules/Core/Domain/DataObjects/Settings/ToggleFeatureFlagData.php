<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Settings;

final readonly class ToggleFeatureFlagData
{
    public function __construct(
        public string $key,
        public bool $isEnabled,
        public ?string $scopeType = null,
        public ?int $scopeId = null,
    ) {}
}
