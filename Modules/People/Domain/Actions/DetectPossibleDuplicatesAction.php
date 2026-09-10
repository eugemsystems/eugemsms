<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Domain\Actions\Action;
use Modules\People\Domain\DataObjects\DetectPossibleDuplicatesData;
use Modules\People\Domain\DataObjects\DuplicateCandidate;
use Modules\People\Domain\Events\PossibleDuplicateLearnerDetected;
use Modules\People\Domain\Support\IdentifierHasher;
use Modules\People\Models\Student;

/**
 * ACT-DetectPossibleDuplicates (Book C PPL-01 §4/BR-PPL-01-009/
 * AC-PPL-01-005). Matches on identifier hash and on name plus date of
 * birth. Raises a review flag; **never** merges automatically — that is
 * `ACT-MergeDuplicateStudents`' job, heavily restricted and out of this
 * pass's scope.
 */
final class DetectPossibleDuplicatesAction extends Action
{
    protected bool $transactional = false;

    /**
     * @return array<int, DuplicateCandidate>
     */
    public function execute(DetectPossibleDuplicatesData $data): array
    {
        $candidates = [];

        $registrationHash = IdentifierHasher::hash($data->nationalRegistrationNo);
        $birthCertHash = IdentifierHasher::hash($data->birthCertificateNo);

        if ($registrationHash !== null) {
            foreach ($this->baseQuery($data)->where('national_registration_no_hash', $registrationHash)->get() as $match) {
                $candidates[$match->id] = new DuplicateCandidate($match->id, $match->admission_number, 'national_registration_no');
            }
        }

        if ($birthCertHash !== null) {
            foreach ($this->baseQuery($data)->where('birth_certificate_no_hash', $birthCertHash)->get() as $match) {
                $candidates[$match->id] ??= new DuplicateCandidate($match->id, $match->admission_number, 'birth_certificate_no');
            }
        }

        foreach ($this->baseQuery($data)
            ->where('first_name', $data->firstName)
            ->where('last_name', $data->lastName)
            ->where('date_of_birth', $data->dateOfBirth->toDateString())
            ->get() as $match) {
            $candidates[$match->id] ??= new DuplicateCandidate($match->id, $match->admission_number, 'name_and_date_of_birth');
        }

        $candidates = array_values($candidates);

        if ($candidates !== []) {
            event(new PossibleDuplicateLearnerDetected($data->schoolId, $candidates));
        }

        return $candidates;
    }

    /**
     * @return Builder<Student>
     */
    private function baseQuery(DetectPossibleDuplicatesData $data): Builder
    {
        $query = Student::withoutGlobalScopes()->where('school_id', $data->schoolId);

        if ($data->excludingStudentId !== null) {
            $query->where('id', '!=', $data->excludingStudentId);
        }

        return $query;
    }
}
