<?php

declare(strict_types=1);

namespace Modules\Academic\Http\Controllers\Api\V1;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Academic\Domain\Actions\GuardianApproveSubjectSelectionAction;
use Modules\Academic\Domain\Actions\SubmitSubjectSelectionAction;
use Modules\Academic\Domain\DataObjects\GuardianApproveSubjectSelectionData;
use Modules\Academic\Domain\DataObjects\SubmitSubjectSelectionData;
use Modules\Academic\Models\Pathway;
use Modules\Academic\Models\Subject;
use Modules\Academic\Models\SubjectSelectionSubmission;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Http\Support\ApiResponse;
use Modules\Finance\Domain\Actions\PreviewIndicativeFeeAction;
use Modules\Finance\Domain\DataObjects\PreviewIndicativeFeeData;
use Modules\People\Domain\Support\LinkedLearners;

/**
 * `POST /api/v1/academic/selections`, `GET .../selections/{ulid}`,
 * `POST .../selections/{ulid}/approve`, `POST .../selections/preview-fee` (Book D ACA-02 §6/§7).
 * The public/portal submission path `Academic\Selection\Form`'s own docblock names as unbuilt — a
 * guardian or the learner themselves (via `LinkedLearners`'s self-link) submits a choice, previews
 * its indicative fee through the same `PreviewIndicativeFeeAction` the staff screen uses (never a
 * second calculation, BR-ACA-02-016), and a guardian approves it. School approval and allocation
 * stay staff-only (`Selection\Approvals`) — no API route for either, since nothing in the spec
 * names a guardian/learner action for that half of the workflow.
 */
final class SubjectSelectionsController
{
    public function store(Request $request, LinkedLearners $linked): JsonResponse
    {
        $data = $request->validate([
            'student' => ['required', 'string'],
            'pathway' => ['nullable', 'string'],
            'subject_ids' => ['required', 'array', 'min:1'],
            'subject_ids.*' => ['string'],
            'reserve_subject_ids' => ['nullable', 'array'],
            'reserve_subject_ids.*' => ['string'],
            'acknowledge_warnings' => ['nullable', 'boolean'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $link = $linked->linkFor($user, $data['student']);
        abort_if($link === null, 404);
        $student = $link->student;

        $yearId = SessionContext::yearId();
        abort_if($yearId === null, 422, 'No active academic year is set for this school.');

        $pathwayId = isset($data['pathway']) ? Pathway::query()->where('ulid', $data['pathway'])->value('id') : null;
        $subjectIds = Subject::query()->whereIn('ulid', $data['subject_ids'])->pluck('id')->all();
        $reserveIds = isset($data['reserve_subject_ids']) ? Subject::query()->whereIn('ulid', $data['reserve_subject_ids'])->pluck('id')->all() : null;

        $termId = SessionContext::termId();
        $feePreview = $termId !== null
            ? app(PreviewIndicativeFeeAction::class)->execute(new PreviewIndicativeFeeData(studentId: $student->id, termId: $termId, subjectIds: $subjectIds))
            : null;

        $submission = app(SubmitSubjectSelectionAction::class)->execute(new SubmitSubjectSelectionData(
            studentId: $student->id,
            academicYearId: $yearId,
            gradeLevelId: (int) $student->grade_level_id,
            selectedSubjectIds: $subjectIds,
            submittedByUserId: $user->id,
            pathwayId: $pathwayId,
            reserveSubjectIds: $reserveIds,
            indicativeFeeMinor: $feePreview?->amountMinor,
            indicativeFeeCurrency: $feePreview?->currency,
            acknowledgeWarnings: (bool) ($data['acknowledge_warnings'] ?? false),
        ));

        return ApiResponse::ok($this->present($submission), status: 201);
    }

    public function previewFee(Request $request, LinkedLearners $linked): JsonResponse
    {
        $data = $request->validate([
            'student' => ['required', 'string'],
            'subject_ids' => ['required', 'array', 'min:1'],
            'subject_ids.*' => ['string'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $link = $linked->linkFor($user, $data['student']);
        abort_if($link === null, 404);

        $termId = SessionContext::termId();
        abort_if($termId === null, 422, 'No active term is set for this school.');

        $subjectIds = Subject::query()->whereIn('ulid', $data['subject_ids'])->pluck('id')->all();
        $preview = app(PreviewIndicativeFeeAction::class)->execute(new PreviewIndicativeFeeData(studentId: $link->student_id, termId: $termId, subjectIds: $subjectIds));

        return ApiResponse::ok(['amount_minor' => $preview->amountMinor, 'currency' => $preview->currency]);
    }

    public function show(Request $request, string $selection, LinkedLearners $linked): JsonResponse
    {
        $submission = SubjectSelectionSubmission::query()->where('ulid', $selection)->firstOrFail();

        /** @var User $user */
        $user = $request->user();
        $link = $linked->linkFor($user, $submission->student->ulid);
        abort_if($link === null, 404);

        return ApiResponse::ok($this->present($submission));
    }

    public function approve(Request $request, string $selection, LinkedLearners $linked): JsonResponse
    {
        $submission = SubjectSelectionSubmission::query()->where('ulid', $selection)->firstOrFail();

        /** @var User $user */
        $user = $request->user();
        $link = $linked->linkFor($user, $submission->student->ulid);
        abort_if($link === null, 404);

        $submission = app(GuardianApproveSubjectSelectionAction::class)->execute(new GuardianApproveSubjectSelectionData(
            submissionId: $submission->id,
            approvedByUserId: $user->id,
        ));

        return ApiResponse::ok($this->present($submission));
    }

    /**
     * @return array<string, mixed>
     */
    private function present(SubjectSelectionSubmission $submission): array
    {
        return [
            'id' => $submission->ulid,
            'status' => $submission->status,
            'subjects' => Subject::query()->whereIn('id', $submission->selected_subject_ids)->get()->map(fn (Subject $s): array => ['id' => $s->ulid, 'name' => $s->name])->values()->all(),
            'indicative_fee_minor' => $submission->indicative_fee_minor,
            'indicative_fee_currency' => $submission->indicative_fee_currency,
            'validation_result' => $submission->validation_result,
            'submitted_at' => $submission->submitted_at?->toIso8601ZuluString(),
            'approved_at' => $submission->approved_at?->toIso8601ZuluString(),
        ];
    }
}
