<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Schools;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Schools\AssignUserData;
use Modules\Core\Models\School;

/**
 * ACT-AssignUserToSchool (Book A CORE-02 §3). BR-CORE-02-007: a user may
 * be assigned to many schools but has exactly one `is_primary = 1` — the
 * new assignment unsets it on every other school when requested primary.
 */
final class AssignUserToSchoolAction extends Action
{
    public function execute(AssignUserData $data): void
    {
        Validator::make(
            ['school_id' => $data->schoolId, 'user_id' => $data->userId],
            [
                'school_id' => ['required', 'integer', 'exists:schools,id'],
                'user_id' => ['required', 'integer', 'exists:users,id'],
            ],
        )->validate();

        $this->transaction(function () use ($data): void {
            $school = School::query()->findOrFail($data->schoolId);
            $user = User::query()->findOrFail($data->userId);

            if ($data->isPrimary) {
                DB::table('school_user')->where('user_id', $user->id)->update(['is_primary' => false]);
            }

            $school->users()->syncWithoutDetaching([
                $user->id => [
                    'is_primary' => $data->isPrimary,
                    'status' => 'active',
                    'assigned_at' => now(),
                    'assigned_by' => $data->assignedByUserId,
                ],
            ]);
        });
    }
}
