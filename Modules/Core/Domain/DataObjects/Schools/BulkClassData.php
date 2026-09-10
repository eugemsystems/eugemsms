<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Schools;

final readonly class BulkClassData
{
    /**
     * @param  array<int, CreateClassData>  $classes
     */
    public function __construct(
        public array $classes,
    ) {}
}
