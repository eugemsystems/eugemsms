<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Documents\AllocateNumberAction;
use Modules\Core\Domain\DataObjects\Documents\AllocateNumberData;
use Modules\People\Domain\DataObjects\CreateStaffData;
use Modules\People\Domain\DataObjects\FillEstablishmentPostData;
use Modules\People\Domain\Events\StaffCreated;
use Modules\People\Models\Staff;

/**
 * ACT-CreateStaff (Book C PPL-04 §2/BR-PPL-04-001). Allocates the
 * gapless `staff_number` and, when the new hire is assigned a post,
 * fills it in the same transaction so establishment and headcount
 * never drift apart.
 */
final class CreateStaffAction extends Action
{
    public function __construct(
        private readonly AllocateNumberAction $allocateNumber,
        private readonly FillEstablishmentPostAction $fillPost,
    ) {}

    public function execute(CreateStaffData $data): Staff
    {
        return $this->transaction(function () use ($data): Staff {
            $number = $this->allocateNumber->execute(new AllocateNumberData(
                schoolId: $data->schoolId,
                documentType: 'staff',
                allocatedByUserId: $data->createdByUserId,
            ));

            $staff = Staff::create([
                'school_id' => $data->schoolId,
                'user_id' => $data->userId,
                'staff_number' => $number->formatted_number,
                'title' => $data->title,
                'first_name' => $data->firstName,
                'middle_names' => $data->middleNames,
                'last_name' => $data->lastName,
                'date_of_birth' => $data->dateOfBirth->toDateString(),
                'gender' => $data->gender,
                'nationality' => $data->nationality,
                'national_registration_no' => $data->nationalRegistrationNo,
                'passport_no' => $data->passportNo,
                'primary_phone' => $data->primaryPhone,
                'staff_category' => $data->staffCategory,
                'department_id' => $data->departmentId,
                'post_id' => $data->postId,
                'reports_to_staff_id' => $data->reportsToStaffId,
                'joined_on' => $data->joinedOn->toDateString(),
                'status' => 'probation',
                'is_teaching' => $data->isTeaching,
                'teacher_registration_no' => $data->teacherRegistrationNo,
                'max_weekly_periods' => $data->maxWeeklyPeriods,
                'created_by' => $data->createdByUserId,
            ]);

            if ($data->postId !== null) {
                $this->fillPost->execute(new FillEstablishmentPostData(
                    postId: $data->postId,
                    overrideEstablishment: $data->overrideEstablishment,
                    overrideReason: $data->overrideReason,
                ));
            }

            event(new StaffCreated($staff));

            return $staff;
        });
    }
}
