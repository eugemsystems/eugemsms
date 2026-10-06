<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\Api\V1;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Http\Support\ApiResponse;

/**
 * `/api/v1/me/*` (Volume 1 §9.3). The signed-in person, the schools they may act in and the
 * session (academic year and term) every other response is read in — shown so a client can
 * always tell the user which term they are looking at (§9.4 rule 6).
 */
final class MeController
{
    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $school = SchoolContext::current();

        return ApiResponse::ok([
            'id' => $user->ulid ?? (string) $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'active_school_id' => $school?->id,
            'abilities' => $user->currentAccessToken()->abilities ?? [],
            'session' => $this->session(),
        ]);
    }

    public function schools(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $primaryId = $user->primarySchool()?->id;

        return ApiResponse::ok($user->schools()->wherePivot('status', 'active')->get()->map(fn ($school): array => [
            'id' => $school->id,
            'name' => $school->name,
            'base_currency' => $school->base_currency,
            'is_primary' => $school->id === $primaryId,
        ])->values()->all());
    }

    /**
     * @return array{academic_year: array{id: int, name: string}, term: array{id: int, name: string, number: int}|null}|null
     */
    private function session(): ?array
    {
        if (! SessionContext::isSet()) {
            return null;
        }

        $year = SessionContext::year();

        $term = SessionContext::term();

        return [
            'academic_year' => ['id' => $year->id, 'name' => $year->name],
            'term' => $term === null ? null : ['id' => $term->id, 'name' => $term->name, 'number' => $term->number],
        ];
    }

    public function currentSession(): JsonResponse
    {
        return ApiResponse::ok(['session' => $this->session()]);
    }
}
