<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Documents;

final readonly class VerifyDocumentData
{
    public function __construct(
        public string $verificationCode,
    ) {}
}
