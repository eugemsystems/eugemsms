<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Install;

final readonly class DatabaseCredentialsData
{
    public function __construct(
        public string $driver,
        public string $host,
        public int $port,
        public string $database,
        public string $username,
        public string $password,
    ) {}
}
