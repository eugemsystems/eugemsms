<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Install;

use Modules\Core\Domain\Contracts\Install\SeedPackOutcome;

final readonly class SeedResult
{
    /**
     * @param  array<int, SeedPackOutcome>  $outcomes
     */
    public function __construct(
        public array $outcomes,
    ) {}

    /**
     * @return array<int, string>
     */
    public function ranPacks(): array
    {
        return array_values(array_map(
            fn (SeedPackOutcome $o): string => $o->packCode,
            array_filter($this->outcomes, fn (SeedPackOutcome $o): bool => $o->ran),
        ));
    }

    /**
     * @return array<int, string>
     */
    public function skippedPacks(): array
    {
        return array_values(array_map(
            fn (SeedPackOutcome $o): string => $o->packCode,
            array_filter($this->outcomes, fn (SeedPackOutcome $o): bool => ! $o->ran),
        ));
    }
}
