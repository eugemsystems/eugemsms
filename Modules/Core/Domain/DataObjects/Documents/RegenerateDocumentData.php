<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Documents;

final readonly class RegenerateDocumentData
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public int $documentId,
        public array $data,
    ) {}
}
