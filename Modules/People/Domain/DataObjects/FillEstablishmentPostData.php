<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

final readonly class FillEstablishmentPostData
{
    public function __construct(
        public int $postId,
        public bool $overrideEstablishment = false,
        public ?string $overrideReason = null,
    ) {}
}
