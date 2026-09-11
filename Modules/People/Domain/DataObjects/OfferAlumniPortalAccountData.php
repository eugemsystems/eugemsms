<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

final readonly class OfferAlumniPortalAccountData
{
    public function __construct(
        public int $alumnusId,
        public ?string $email = null,
        public ?string $phone = null,
    ) {}
}
