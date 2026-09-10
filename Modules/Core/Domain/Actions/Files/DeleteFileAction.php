<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Files;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Models\File;

/**
 * ACT-DeleteFile (Book A CORE-10 BR-CORE-10-011). Soft delete only —
 * physical removal happens later, via
 * `PurgeExpiredSoftDeletedFilesAction`, after the retention window.
 */
final class DeleteFileAction extends Action
{
    public function execute(File $file): void
    {
        $this->transaction(function () use ($file): void {
            $file->delete();
        });
    }
}
