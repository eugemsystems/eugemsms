<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Install;

final readonly class ServiceTestData
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        public string $service,
        public array $config = [],
    ) {}
}
