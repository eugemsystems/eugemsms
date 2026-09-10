<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Documents;

final readonly class RecordDocumentDownloadData
{
    public function __construct(
        public int $documentId,
    ) {}
}
