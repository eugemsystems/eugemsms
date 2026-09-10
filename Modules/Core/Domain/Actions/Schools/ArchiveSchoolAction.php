<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Schools;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Contracts\Schools\SchoolLifecycleGuard;
use Modules\Core\Domain\DataObjects\Schools\ArchiveSchoolData;
use Modules\Core\Domain\Events\Schools\SchoolArchived;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Models\School;

/**
 * ACT-ArchiveSchool (Book A CORE-02 §3). BR-CORE-02-005: a school can
 * only be archived, never deleted, and only once every registered
 * blocking condition (active learners, open financial periods — both
 * owned by modules not built yet) reports clear. BR-CORE-02-006:
 * archiving preserves all data; only the `status` flips.
 */
final class ArchiveSchoolAction extends Action
{
    public function __construct(
        private readonly SchoolLifecycleGuard $lifecycleGuard,
    ) {}

    public function execute(ArchiveSchoolData $data): void
    {
        $school = School::query()->findOrFail($data->schoolId);

        if ($school->status === 'archived') {
            throw new InvalidStateTransitionException(
                'This school is already archived.',
                ['school_id' => $school->id],
            );
        }

        $blockers = $this->lifecycleGuard->archiveBlockers($school);

        if ($blockers !== []) {
            throw new InvalidStateTransitionException(
                'This school cannot be archived: '.implode(', ', $blockers).'.',
                ['school_id' => $school->id, 'blockers' => $blockers],
            );
        }

        $this->transaction(function () use ($school, $data): void {
            $school->status = 'archived';
            $school->updated_by = $data->actingUserId;
            $school->save();

            event(new SchoolArchived($school, $data->reason));
        });
    }
}
