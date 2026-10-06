<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Modules\Core\Http\Support\ApiResponse;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\Term;

/**
 * `/api/v1/lookups/*` (Volume 1 §9.3): reference data a client caches aggressively. Responses
 * carry a private `Cache-Control` so an app can reuse them for a few minutes between refreshes.
 */
final class LookupsController
{
    public function terms(): JsonResponse
    {
        $years = AcademicYear::query()->orderByDesc('starts_on')->limit(5)->get();
        $terms = Term::query()->whereIn('academic_year_id', $years->pluck('id'))->orderBy('starts_on')->get()->groupBy('academic_year_id');

        return $this->cached(ApiResponse::ok($years->map(fn (AcademicYear $year): array => [
            'id' => $year->id,
            'name' => $year->name,
            'terms' => ($terms->get($year->id) ?? collect())->map(fn (Term $term): array => [
                'id' => $term->id, 'number' => $term->number, 'name' => $term->name,
                'starts_on' => $term->starts_on->toDateString(), 'ends_on' => $term->ends_on->toDateString(),
            ])->values()->all(),
        ])->values()->all()));
    }

    public function gradeLevels(): JsonResponse
    {
        return $this->cached(ApiResponse::ok(GradeLevel::query()->orderBy('ordinal')->get()->map(fn (GradeLevel $level): array => [
            'id' => $level->id, 'code' => $level->code, 'name' => $level->name,
        ])->values()->all()));
    }

    private function cached(JsonResponse $response): JsonResponse
    {
        return $response->header('Cache-Control', 'private, max-age=300');
    }
}
