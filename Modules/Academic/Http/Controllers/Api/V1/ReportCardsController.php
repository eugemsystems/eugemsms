<?php

declare(strict_types=1);

namespace Modules\Academic\Http\Controllers\Api\V1;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Academic\Models\TermResult;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Http\Support\ApiResponse;
use Modules\Core\Models\Term;
use Modules\People\Domain\Support\LinkedLearners;

/**
 * `GET /api/v1/students/{student}/report-cards` (Volume 1 §9.3, BR-ACA-05-018). A guardian sees
 * only published report cards of a learner they are currently linked to. A card withheld for
 * fees shows as withheld and carries no marks and no reason — the reason can name the debt, and
 * the fee conversation happens with the school, not in an app payload. Drafts and computed
 * results never leave the school.
 */
final class ReportCardsController
{
    public function index(Request $request, string $student, LinkedLearners $linked): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $link = $linked->linkFor($user, $student);
        abort_if($link === null, 404);

        $results = TermResult::query()
            ->where('student_id', $link->student_id)
            ->whereIn('status', ['published', 'withheld'])
            // History by default; an explicit X-Term-Id / X-Academic-Year-Id (already validated by the
            // session middleware) narrows the list to that term or year.
            ->when($request->hasHeader('X-Term-Id'), fn ($q) => $q->where('term_id', (int) SessionContext::termId()))
            ->when(! $request->hasHeader('X-Term-Id') && $request->hasHeader('X-Academic-Year-Id'), fn ($q) => $q->whereIn('term_id', Term::query()->where('academic_year_id', (int) SessionContext::yearId())->select('id')))
            ->orderByDesc('term_id')
            ->get();
        $terms = Term::query()->whereIn('id', $results->pluck('term_id'))->get()->keyBy('id');

        return ApiResponse::ok($results->map(function (TermResult $result) use ($terms): array {
            $term = $terms->get($result->term_id);
            $row = [
                'id' => $result->ulid,
                'term' => ['id' => $result->term_id, 'name' => $term !== null ? $term->name : null],
                'status' => $result->status,
            ];

            if ($result->status === 'published') {
                $row += [
                    'report_version' => $result->report_version,
                    'published_at' => $result->published_at?->toIso8601ZuluString(),
                    'average_percent' => $result->average_percent,
                    'subjects_taken' => $result->subjects_taken,
                    'class_position' => $result->class_position,
                    'class_size' => $result->class_size,
                    'attendance_percent' => $result->attendance_percent,
                    'conduct_grade' => $result->conduct_grade,
                    'class_teacher_comment' => $result->class_teacher_comment,
                    'head_comment' => $result->head_comment,
                ];
            }

            return $row;
        })->values()->all());
    }
}
