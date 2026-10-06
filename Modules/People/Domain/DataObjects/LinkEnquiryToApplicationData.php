<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

final readonly class LinkEnquiryToApplicationData
{
    public function __construct(
        public int $enquiryId,
        public int $applicationId,
    ) {}
}
