<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support\Audit;

use Modules\Core\Domain\Contracts\Audit\IntegrityCheck;
use Modules\Core\Domain\DataObjects\Audit\IntegrityCheckResult;

/**
 * A check named in Book A CORE-08 §4 whose owning module (trial
 * balance → FIN-*, cross-school FK → cross-cutting, cached balance →
 * FIN-*, orphan records → cross-cutting) is not built yet. Same
 * honest-placeholder shape as `PendingSeedPack`/`PendingRolloverHandler`.
 */
final readonly class PendingIntegrityCheck implements IntegrityCheck
{
    public function __construct(
        private string $checkType,
        private string $owningModule,
    ) {}

    public function checkType(): string
    {
        return $this->checkType;
    }

    public function isAvailable(): bool
    {
        return false;
    }

    public function run(?int $schoolId): IntegrityCheckResult
    {
        return new IntegrityCheckResult(status: 'error', failureDetails: [
            ['message' => "Not yet available — ships with {$this->owningModule}."],
        ]);
    }
}
