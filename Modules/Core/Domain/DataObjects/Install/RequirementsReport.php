<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Install;

/**
 * BR-CORE-01-002: installation cannot proceed past the requirements step
 * while any mandatory requirement fails. Optional requirements produce
 * warnings only.
 */
final readonly class RequirementsReport
{
    /**
     * @param  array<int, RequirementCheck>  $checks
     */
    public function __construct(
        public array $checks,
    ) {}

    public function passesMandatory(): bool
    {
        foreach ($this->checks as $check) {
            if ($check->blocksInstallation()) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<int, RequirementCheck>
     */
    public function failures(): array
    {
        return array_values(array_filter($this->checks, fn (RequirementCheck $c): bool => $c->blocksInstallation()));
    }
}
