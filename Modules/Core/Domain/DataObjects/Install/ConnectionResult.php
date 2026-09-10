<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Install;

final readonly class ConnectionResult
{
    /**
     * @param  array<int, string>  $conflictingTables
     */
    public function __construct(
        public bool $connected,
        public ?string $serverVersion,
        public DatabaseSchemaState $schemaState,
        public array $conflictingTables,
        public string $message,
    ) {}

    public function canProceed(): bool
    {
        return $this->connected && $this->schemaState !== DatabaseSchemaState::Conflicting;
    }
}
