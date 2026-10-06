<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use InvalidArgumentException;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Models\GradeLevel;
use Modules\People\Domain\DataObjects\CreateEnquiryData;
use Modules\People\Domain\Support\PhoneNumberNormaliser;
use Modules\People\Models\Enquiry;
use Modules\People\Models\Intake;

/**
 * ACT-CreateEnquiry (Book C PPL-02 §2). A new lead at the top of the funnel. It
 * needs a way to reach the enquirer (phone or email); phones are stored as E.164.
 */
final class CreateEnquiryAction extends Action
{
    public const SOURCES = ['website', 'walk_in', 'phone', 'referral', 'alumni', 'social_media', 'open_day', 'agent'];

    public function execute(CreateEnquiryData $data): Enquiry
    {
        if (! in_array($data->source, self::SOURCES, true)) {
            throw new InvalidArgumentException("Unknown enquiry source [{$data->source}].");
        }

        if (trim($data->enquirerName) === '' || (trim((string) $data->enquirerPhone) === '' && trim((string) $data->enquirerEmail) === '')) {
            throw new InvalidArgumentException('An enquiry needs the enquirer\'s name and a phone number or email.');
        }

        if ($data->enquirerEmail !== null && $data->enquirerEmail !== '' && filter_var($data->enquirerEmail, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('That email address is not valid.');
        }

        if ($data->learnerDob !== null && $data->learnerDob->isFuture()) {
            throw new InvalidArgumentException('The learner\'s date of birth cannot be in the future.');
        }

        if (($data->intakeId !== null && ! Intake::query()->whereKey($data->intakeId)->exists())
            || ($data->interestedGradeLevelId !== null && ! GradeLevel::query()->whereKey($data->interestedGradeLevelId)->exists())) {
            throw new InvalidArgumentException('That intake or grade level does not belong to this school.');
        }

        $phone = trim((string) $data->enquirerPhone) === '' ? null : PhoneNumberNormaliser::normalise((string) $data->enquirerPhone);

        return $this->transaction(fn (): Enquiry => Enquiry::create([
            'school_id' => $data->schoolId,
            'intake_id' => $data->intakeId,
            'source' => $data->source,
            'enquirer_name' => trim($data->enquirerName),
            'enquirer_phone' => $phone,
            'enquirer_email' => $data->enquirerEmail === '' ? null : $data->enquirerEmail,
            'learner_name' => $data->learnerName,
            'learner_dob' => $data->learnerDob?->toDateString(),
            'interested_grade_level_id' => $data->interestedGradeLevelId,
            'interested_residency' => $data->interestedResidency,
            'message' => $data->message,
            'stage' => 'new',
            'assigned_to' => $data->assignedTo,
            'next_follow_up_on' => $data->nextFollowUpOn?->toDateString(),
        ]));
    }
}
