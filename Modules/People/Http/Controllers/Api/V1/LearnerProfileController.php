<?php

declare(strict_types=1);

namespace Modules\People\Http\Controllers\Api\V1;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\Support\ApiResponse;
use Modules\People\Models\Student;

/**
 * `GET /api/v1/me/profile` (Book C PPL-01 §9) — a learner token's own reduced profile, resolved
 * the same way `Modules\People\Domain\Support\LinkedLearners::ownRecords()` already does for a
 * learner who signs in themselves: a `Student` row whose own `user_id` is this token's user.
 *
 * `PATCH /api/v1/me/profile` is deliberately NOT built here: unlike a guardian's own contact
 * details (`RequestGuardianContactUpdateAction`, PPL-03), no Action anywhere in the domain layer
 * lets a learner request a profile change of their own — confirmed by grep, not assumed. Inventing
 * one would be new backend business logic, not exposing an existing Action, so it stays a
 * documented gap rather than guessed at here.
 */
final class LearnerProfileController
{
    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $student = Student::query()->where('user_id', $user->id)->where('status', 'active')->first();
        abort_if($student === null, 404);

        return ApiResponse::ok([
            'id' => $student->ulid,
            'admission_number' => $student->admission_number,
            'first_name' => $student->first_name,
            'last_name' => $student->last_name,
            'preferred_name' => $student->preferred_name,
            'grade_level_id' => $student->grade_level_id,
            'class_id' => $student->class_id,
            'house_id' => $student->house_id,
            'status' => $student->status,
        ]);
    }
}
