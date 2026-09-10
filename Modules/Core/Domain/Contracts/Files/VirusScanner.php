<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Contracts\Files;

use Modules\Core\Domain\DataObjects\Files\ScanResult;

/**
 * Book A CORE-10 BR-CORE-10-005. A real implementation (ClamAV via a
 * daemon socket, a cloud scanning API) is a new dependency, deferred —
 * `NullVirusScanner` reports every file `skipped` in the meantime,
 * same deferred-dependency shape as every other Null* provider here.
 */
interface VirusScanner
{
    public function scan(string $filePath): ScanResult;
}
