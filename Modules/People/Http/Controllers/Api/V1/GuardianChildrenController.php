<?php

declare(strict_types=1);

namespace Modules\People\Http\Controllers\Api\V1;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\Support\ApiResponse;
use Modules\People\Domain\Support\LinkedLearners;

/**
 * `GET /api/v1/guardians/me/children` (Volume 1 §9.3). The learners the signed-in guardian is
 * linked to, with the permissions that link carries. Nothing is returned for a learner the
 * guardian is not currently linked to.
 */
final class GuardianChildrenController
{
    public function index(Request $request, LinkedLearners $linked): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::ok($linked->forUser($user)->map(fn ($link): array => [
            'id' => $link->student->ulid,
            'admission_number' => $link->student->admission_number,
            'first_name' => $link->student->first_name,
            'last_name' => $link->student->last_name,
            'grade_level_id' => $link->student->grade_level_id,
            'class_id' => $link->student->class_id,
            'relationship' => $link->relationship,
            'permissions' => [
                'may_view_full_balance' => $link->may_view_full_balance,
                'may_collect_learner' => $link->may_collect_learner,
                'may_authorise_exeat' => $link->may_authorise_exeat,
            ],
        ])->values()->all());
    }
}
