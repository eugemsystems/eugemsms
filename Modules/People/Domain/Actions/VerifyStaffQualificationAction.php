<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use InvalidArgumentException;
use Modules\Core\Domain\Actions\Action;
use Modules\People\Domain\DataObjects\VerifyStaffQualificationData;
use Modules\People\Models\Staff;
use Modules\People\Models\StaffQualification;

/**
 * ACT-VerifyStaffQualification (Book C PPL-04 §2). Marks a qualification as
 * checked against its certificate. A certificate must be on file, and nobody
 * verifies their own qualification.
 */
final class VerifyStaffQualificationAction extends Action
{
    public function execute(VerifyStaffQualificationData $data): StaffQualification
    {
        $qualification = StaffQualification::findOrFail($data->qualificationId);

        if ($qualification->certificate_file_id === null) {
            throw new InvalidArgumentException('Attach the certificate before verifying the qualification.');
        }

        if (Staff::query()->whereKey($qualification->staff_id)->where('user_id', $data->verifiedByUserId)->exists()) {
            throw new InvalidArgumentException('You cannot verify your own qualification.');
        }

        return $this->transaction(function () use ($qualification, $data): StaffQualification {
            $qualification->update(['is_verified' => true, 'verified_by' => $data->verifiedByUserId]);

            return $qualification->fresh();
        });
    }
}
