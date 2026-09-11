<?php

declare(strict_types=1);

namespace Modules\Saas\Domain\DataObjects;

use Illuminate\Support\Carbon;

final readonly class StartOnboardingChecklistData
{
    /**
     * @param  array<int, array{key: string, label: string}>  $steps
     */
    public function __construct(
        public int $tenantId,
        public int $schoolId,
        public array $steps,
        public ?Carbon $targetGoLiveDate = null,
        public ?int $assignedSuccessManager = null,
    ) {}
}
