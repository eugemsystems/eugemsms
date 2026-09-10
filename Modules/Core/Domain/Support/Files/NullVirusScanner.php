<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support\Files;

use Modules\Core\Domain\Contracts\Files\VirusScanner;
use Modules\Core\Domain\DataObjects\Files\ScanResult;

final class NullVirusScanner implements VirusScanner
{
    public function scan(string $filePath): ScanResult
    {
        return new ScanResult(status: 'skipped', detail: 'No virus scanning engine is configured yet.');
    }
}
