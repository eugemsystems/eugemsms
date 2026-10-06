<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use InvalidArgumentException;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Models\File;
use Modules\People\Domain\DataObjects\AddStaffQualificationData;
use Modules\People\Models\Staff;
use Modules\People\Models\StaffQualification;

/**
 * ACT-AddStaffQualification (Book C PPL-04 §2). Records a qualification against
 * a staff member; it starts unverified and is verified separately against the
 * certificate.
 */
final class AddStaffQualificationAction extends Action
{
    public const TYPES = ['certificate', 'diploma', 'degree', 'postgraduate', 'professional'];

    public function execute(AddStaffQualificationData $data): StaffQualification
    {
        if (! in_array($data->qualificationType, self::TYPES, true)) {
            throw new InvalidArgumentException("Unknown qualification type [{$data->qualificationType}].");
        }

        if (trim($data->title) === '' || trim($data->institution) === '' || strlen($data->country) !== 2) {
            throw new InvalidArgumentException('A qualification needs a title, an institution and a two-letter country.');
        }

        if ($data->yearObtained !== null && ($data->yearObtained < 1900 || $data->yearObtained > (int) now()->year)) {
            throw new InvalidArgumentException('The year obtained must be between 1900 and this year.');
        }

        if (! Staff::query()->whereKey($data->staffId)->exists()) {
            throw new InvalidArgumentException('That staff member does not belong to this school.');
        }

        if ($data->certificateFileId !== null && ! File::query()->whereKey($data->certificateFileId)->exists()) {
            throw new InvalidArgumentException('That certificate file does not belong to this school.');
        }

        return $this->transaction(fn (): StaffQualification => StaffQualification::create([
            'school_id' => $data->schoolId,
            'staff_id' => $data->staffId,
            'qualification_type' => $data->qualificationType,
            'title' => trim($data->title),
            'institution' => trim($data->institution),
            'country' => strtoupper($data->country),
            'year_obtained' => $data->yearObtained,
            'grade_class' => $data->gradeClass,
            'subjects' => $data->subjects === [] ? null : array_values($data->subjects),
            'certificate_file_id' => $data->certificateFileId,
            'is_verified' => false,
        ]));
    }
}
