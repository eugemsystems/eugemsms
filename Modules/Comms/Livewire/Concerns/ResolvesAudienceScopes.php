<?php

declare(strict_types=1);

namespace Modules\Comms\Livewire\Concerns;

use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\SchoolSection;

/**
 * The audience scopes a calendar event or notice can be aimed at from
 * the admin screens. `class` and `house` are deliberately absent:
 * `CalendarAudienceFilter` has no queryable source to resolve them
 * against, so a notice scoped to one would silently reach nobody
 * (see that class's own docblock).
 */
trait ResolvesAudienceScopes
{
    /**
     * @return array<string, string>
     */
    protected function audienceScopeOptions(): array
    {
        return [
            'whole_school' => __('Whole school'),
            'staff' => __('Staff only'),
            'section' => __('A school section'),
            'level' => __('A grade level'),
        ];
    }

    /**
     * @return array<int, string> id => name for the targets of a narrow scope, empty otherwise
     */
    protected function audienceScopeTargets(string $scope, int $schoolId): array
    {
        return match ($scope) {
            'section' => SchoolSection::where('school_id', $schoolId)->orderBy('name')->pluck('name', 'id')->all(),
            'level' => GradeLevel::where('school_id', $schoolId)->orderBy('name')->pluck('name', 'id')->all(),
            default => [],
        };
    }

    /**
     * A scope id is required exactly for the narrow scopes, and must be
     * one of this school's own targets.
     */
    protected function resolveAudienceScopeId(string $scope, ?int $scopeId, int $schoolId): ?int
    {
        if (! in_array($scope, ['section', 'level'], true)) {
            return null;
        }

        abort_unless($scopeId !== null && array_key_exists($scopeId, $this->audienceScopeTargets($scope, $schoolId)), 422);

        return $scopeId;
    }
}
