<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Sessions;

use App\Models\User;
use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Sessions\SessionContextResult;
use Modules\Core\Domain\DataObjects\Sessions\SwitchSessionData;
use Modules\Core\Domain\Events\Sessions\SessionSwitched;
use Modules\Core\Domain\Exceptions\UnauthorisedSchoolAccessException;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\Term;
use Modules\Core\Models\UserSessionPreference;

/**
 * ACT-SwitchSession (Book A CORE-03 §4). BR-CORE-03-006: a user may only
 * switch to a session belonging to their active school. Like
 * `SwitchActiveSchoolAction` (CORE-02), this never calls
 * `SessionContext::set()` directly — it persists the preference that
 * `SetSessionContext` middleware reads on the *next* request.
 */
final class SwitchSessionAction extends Action
{
    public function execute(SwitchSessionData $data): SessionContextResult
    {
        $user = User::query()->findOrFail($data->userId);

        if (! $user->isAssignedToSchool($data->schoolId)) {
            throw new UnauthorisedSchoolAccessException(
                'You are not assigned to this school.',
                ['school_id' => $data->schoolId],
            );
        }

        $year = AcademicYear::withoutGlobalScopes()
            ->where('id', $data->academicYearId)
            ->where('school_id', $data->schoolId)
            ->first();

        if ($year === null) {
            throw ValidationException::withMessages(['academic_year_id' => 'This academic year does not belong to that school.']);
        }

        $term = null;

        if ($data->termId !== null) {
            $term = Term::withoutGlobalScopes()
                ->where('id', $data->termId)
                ->where('academic_year_id', $year->id)
                ->first();

            if ($term === null) {
                throw ValidationException::withMessages(['term_id' => 'This term does not belong to that academic year.']);
            }
        }

        return $this->transaction(function () use ($user, $year, $term, $data): SessionContextResult {
            UserSessionPreference::updateOrCreate(
                ['user_id' => $user->id, 'school_id' => $data->schoolId],
                ['academic_year_id' => $year->id, 'term_id' => $term?->id, 'updated_at' => now()],
            );

            event(new SessionSwitched($user, $year, $term));

            return new SessionContextResult($year, $term);
        });
    }
}
