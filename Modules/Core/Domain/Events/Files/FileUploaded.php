<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Events\Files;

use Modules\Core\Models\File;

final class FileUploaded
{
    public function __construct(
        public readonly File $file,
    ) {}
}
