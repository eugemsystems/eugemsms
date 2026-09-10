<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\People\Domain\DataObjects\DeactivateStudentGuardianData;
use Modules\People\Domain\Events\GuardianUnlinked;
use Modules\People\Domain\Exceptions\LastFeeResponsibleGuardianException;
use Modules\People\Models\StudentGuardian;

/**
 * ACT-DeactivateStudentGuardian (Book C PPL-03 §6/BR-PPL-03-022,
 * AC-PPL-03-009). Refuses to remove the last `is_fee_responsible`
 * relationship a learner has — there is always a residual payer of
 * last resort.
 */
final class DeactivateStudentGuardianAction extends Action
{
    public function execute(DeactivateStudentGuardianData $data): StudentGuardian
    {
        $link = StudentGuardian::findOrFail($data->studentGuardianId);

        if ($link->is_fee_responsible) {
            $otherResponsible = StudentGuardian::query()
                ->where('student_id', $link->student_id)
                ->where('id', '!=', $link->id)
                ->where('is_fee_responsible', true)
                ->where('status', 'active')
                ->exists();

            if (! $otherResponsible) {
                throw LastFeeResponsibleGuardianException::forStudent($link->student_id);
            }
        }

        return $this->transaction(function () use ($link, $data): StudentGuardian {
            $link->update(['status' => 'inactive', 'effective_to' => now()->toDateString(), 'updated_by' => $data->deactivatedByUserId]);

            event(new GuardianUnlinked($link));

            return $link;
        });
    }
}
