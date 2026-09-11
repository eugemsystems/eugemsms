<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\People\Domain\DataObjects\RecordCareerUpdateData;
use Modules\People\Models\AlumniCareerUpdate;
use Modules\People\Models\Alumnus;

/**
 * ACT-RecordCareerUpdate (Book K PPL-06 §4/BR-PPL-06-004). Always
 * lands `verified = false` — only `VerifyCareerUpdateAction` (the
 * school's own action) ever flips it.
 */
final class RecordCareerUpdateAction extends Action
{
    public function execute(RecordCareerUpdateData $data): AlumniCareerUpdate
    {
        $alumnus = Alumnus::findOrFail($data->alumnusId);

        return $this->transaction(fn (): AlumniCareerUpdate => AlumniCareerUpdate::create([
            'school_id' => $alumnus->school_id,
            'alumnus_id' => $alumnus->id,
            'update_type' => $data->updateType,
            'title' => $data->title,
            'institution_or_employer' => $data->institutionOrEmployer,
            'starts_on' => $data->startsOn?->toDateString(),
            'ends_on' => $data->endsOn?->toDateString(),
            'is_current' => $data->isCurrent,
            'verified' => false,
            'submitted_at' => now(),
        ]));
    }
}
