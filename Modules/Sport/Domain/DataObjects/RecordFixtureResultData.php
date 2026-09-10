<?php

declare(strict_types=1);

namespace Modules\Sport\Domain\DataObjects;

final readonly class RecordFixtureResultData
{
    public function __construct(
        public int $fixtureId,
        public string $result,
        public ?string $scoreFor = null,
        public ?string $scoreAgainst = null,
        public ?string $matchReport = null,
    ) {}
}
