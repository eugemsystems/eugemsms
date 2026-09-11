<?php

declare(strict_types=1);

namespace Modules\People\Domain\Listeners;

use Modules\People\Domain\Actions\CreateAlumniRecordAction;
use Modules\People\Domain\DataObjects\CreateAlumniRecordData;
use Modules\People\Domain\Events\LearnerStatusChanged;

/**
 * Book K PPL-06 §3 ⭐/BR-PPL-06-001. No dedicated `LearnerGraduated`
 * event exists in this codebase — graduation is just one more
 * `ChangeStudentStatusAction` transition, so this listens to the
 * generic `LearnerStatusChanged` and filters for `toStatus ===
 * 'graduated'`, exactly the same "generic event, module-specific
 * filter" shape `ACA-04`'s attendance listener uses elsewhere.
 */
final class CreateAlumniRecordOnGraduationListener
{
    public function __construct(
        private readonly CreateAlumniRecordAction $createAlumniRecord,
    ) {}

    public function handle(LearnerStatusChanged $event): void
    {
        if ($event->toStatus !== 'graduated') {
            return;
        }

        $this->createAlumniRecord->execute(new CreateAlumniRecordData(studentId: $event->student->id));
    }
}
