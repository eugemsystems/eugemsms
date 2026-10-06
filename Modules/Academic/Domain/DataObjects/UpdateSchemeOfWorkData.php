<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class UpdateSchemeOfWorkData
{
    /**
     * @param  array<int, array<string, mixed>>  $plannedTopics  [{week, topic, objectives, resources}]
     */
    public function __construct(
        public int $schemeOfWorkId,
        public array $plannedTopics,
    ) {}
}
