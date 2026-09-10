<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\People\Domain\DataObjects\UpdateStudentProfileData;
use Modules\People\Models\Student;

final class UpdateStudentProfileAction extends Action
{
    public function execute(UpdateStudentProfileData $data): Student
    {
        $student = Student::findOrFail($data->studentId);

        return $this->transaction(function () use ($student, $data): Student {
            $student->update(array_filter([
                'first_name' => $data->firstName,
                'middle_names' => $data->middleNames,
                'last_name' => $data->lastName,
                'preferred_name' => $data->preferredName,
                'home_language' => $data->homeLanguage,
                'religion' => $data->religion,
                'address_line_1' => $data->addressLine1,
                'address_line_2' => $data->addressLine2,
                'suburb' => $data->suburb,
                'city' => $data->city,
                'province' => $data->province,
                'notes' => $data->notes,
                'updated_by' => $data->updatedByUserId,
            ], fn ($value): bool => $value !== null));

            return $student;
        });
    }
}
