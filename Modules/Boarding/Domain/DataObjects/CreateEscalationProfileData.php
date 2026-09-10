<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\DataObjects;

final readonly class CreateEscalationProfileData
{
    /**
     * @param  array<int, EscalationStepInput>  $steps
     */
    public function __construct(
        public int $schoolId,
        public string $name,
        public array $steps,
        public ?string $description = null,
        public bool $isDefault = false,
    ) {}
}
