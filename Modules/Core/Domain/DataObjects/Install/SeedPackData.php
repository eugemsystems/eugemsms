<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Install;

final readonly class SeedPackData
{
    /**
     * @param  array<int, string>  $packs
     */
    public function __construct(
        public int $schoolId,
        public array $packs,
    ) {}
}
