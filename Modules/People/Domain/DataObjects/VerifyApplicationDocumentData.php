<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

final readonly class VerifyApplicationDocumentData
{
    public function __construct(
        public int $documentId,
        public int $verifiedByUserId,
    ) {}
}
