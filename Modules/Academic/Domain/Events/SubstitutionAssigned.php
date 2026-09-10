<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Events;

use Modules\Academic\Models\LessonSubstitution;

final class SubstitutionAssigned
{
    public function __construct(public readonly LessonSubstitution $substitution) {}
}
