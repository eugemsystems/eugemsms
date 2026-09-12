<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Schools;

use App\Models\User;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Contracts\Schools\AuditLogger;
use Modules\Core\Domain\DataObjects\Schools\SwitchSchoolData;
use Modules\Core\Domain\Events\Schools\ActiveSchoolSwitched;
use Modules\Core\Domain\Exceptions\UnauthorisedSchoolAccessException;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Core\Models\UserSessionPreference;

/**
 * ACT-SwitchActiveSchool (Book A CORE-02 §3). BR-CORE-02-008: switching
 * resets the session context to the target school's current term, and
 * writes an audit entry. BR-CORE-02-009: switching to a school the user
 * isn't assigned to throws and is logged as a security event.
 *
 * This never calls `SchoolContext::set()`/`SessionContext::set()`
 * directly — those are request-scoped and middleware-owned (Book A Part
 * 1.5). What it persists here (`UserSessionPreference`, touched so
 * `SetSchoolContext` resolves it as the most recent) is what the
 * `SetSchoolContext`/`SetSessionContext` middleware reads on the
 * *next* request to actually flip the context.
 *
 * "Flushes the permission cache, re-resolves the role set" (the rest of
 * BR-CORE-02-008) has no work to do yet — CORE-05 hasn't shipped a
 * permission system for there to be a cache of.
 *
 * Bugfix (2026-09-12, user-reported, reproduced against real data after
 * five rounds of "it's still not switching" — every earlier fix in this
 * file's git history was real but addressed a different, coincidental
 * symptom): `updateOrCreate()`'s `fill($values)` silently drops
 * `updated_at` — it isn't in `UserSessionPreference::$fillable` — and
 * Eloquent's `save()` only calls `updateTimestamps()` if the model is
 * ALREADY dirty from some OTHER changed attribute (`Model::save()`
 * checks `isDirty()` before touching timestamps at all). So whenever the
 * freshly computed `academic_year_id`/`term_id` happen to already match
 * what's stored — exactly the case for a school whose "current" term
 * never changed since the last time this ran — NOTHING is dirty, no
 * timestamp bump happens, and no UPDATE query is even issued.
 * `ActiveSchoolResolver::resolveId()` picks the most recently switched
 * school by that same `updated_at`, so switching TO such a school
 * silently failed to register as the newer choice, every time,
 * specifically because nothing else about it had changed — while
 * switching to a DIFFERENT school (whose term happened to differ) worked
 * fine, making this look random rather than deterministic. `touch()`
 * calls `updateTimestamps()` unconditionally and only THEN checks
 * dirtiness, so it always persists.
 */
final class SwitchActiveSchoolAction extends Action
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(SwitchSchoolData $data): School
    {
        $user = User::query()->findOrFail($data->userId);
        $school = School::query()->find($data->schoolId);

        if ($school === null || ! $user->isAssignedToSchool($data->schoolId)) {
            $this->auditLogger->securityEvent($user, 'school.switch.unauthorised', ['school_id' => $data->schoolId]);

            throw new UnauthorisedSchoolAccessException(
                'You are not assigned to the requested school.',
                ['school_id' => $data->schoolId],
            );
        }

        return $this->transaction(function () use ($user, $school): School {
            // Not `$school->currentAcademicYear()`/`currentTerm()`: those
            // relation queries carry BelongsToSchool's global scope, which
            // filters by the *ambient* SchoolContext — almost never the
            // target school here, since switching to it is the whole
            // point. Queried directly, scoped explicitly by the target
            // school's own id instead.
            $year = AcademicYear::withoutGlobalScopes()
                ->where('school_id', $school->id)->where('is_current', true)->first();
            $term = $year !== null
                ? Term::withoutGlobalScopes()
                    ->where('school_id', $school->id)->where('academic_year_id', $year->id)
                    ->where('is_current', true)->first()
                : null;

            $preference = UserSessionPreference::updateOrCreate(
                ['user_id' => $user->id, 'school_id' => $school->id],
                [
                    'academic_year_id' => $year?->id,
                    'term_id' => $term?->id,
                ],
            );

            // Must always bump, even when nothing else about this
            // preference actually changed — see this class's own
            // docblock. `updateOrCreate()`'s own `save()` cannot be
            // trusted to do this on its own.
            $preference->touch();

            $this->auditLogger->record($user, 'school.switched', ['school_id' => $school->id]);

            event(new ActiveSchoolSwitched($user, $school));

            return $school;
        });
    }
}
