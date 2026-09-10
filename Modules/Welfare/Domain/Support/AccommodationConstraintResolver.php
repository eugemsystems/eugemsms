<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\Support;

use Modules\Welfare\Models\MedicalCondition;

/**
 * Book G BRD-06 §4/BR-BRD-06-009/AC-BRD-06-009 — closes the
 * `medical_proximity`/`mobility_ground_floor` stub `Modules\Boarding\Domain\Support\BedAllocationEngine`
 * left open (BRD-01 §3): `AllocateBedAction`/`RunBulkAllocationAction`
 * call this to get the two hard-constraint booleans the engine already
 * accepts. Only the boolean crosses the boundary — never the
 * `accommodation_requirement` text, and never the diagnosis.
 */
final class AccommodationConstraintResolver
{
    /**
     * @return array{requiresGroundFloor: bool, requiresExitProximity: bool}
     */
    public function resolve(int $studentId): array
    {
        $requirements = MedicalCondition::query()
            ->where('student_id', $studentId)
            ->where('status', 'active')
            ->where('affects_accommodation', true)
            ->pluck('accommodation_requirement')
            ->filter()
            ->map(fn (string $requirement): string => mb_strtolower($requirement));

        return [
            'requiresGroundFloor' => $requirements->contains(fn (string $r): bool => str_contains($r, 'ground floor')),
            'requiresExitProximity' => $requirements->contains(fn (string $r): bool => str_contains($r, 'exit')),
        ];
    }
}
