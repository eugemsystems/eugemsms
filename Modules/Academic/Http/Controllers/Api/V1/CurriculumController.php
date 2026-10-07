<?php

declare(strict_types=1);

namespace Modules\Academic\Http\Controllers\Api\V1;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Academic\Domain\DataObjects\Violation;
use Modules\Academic\Domain\Support\SubjectSelectionRuleEngine;
use Modules\Academic\Models\CurriculumFramework;
use Modules\Academic\Models\LevelSubjectOffering;
use Modules\Academic\Models\Subject;
use Modules\Academic\Models\SubjectGroup;
use Modules\Academic\Models\SubjectSelectionRule;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Http\Support\ApiResponse;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\GradeLevel;
use Modules\People\Domain\Support\LinkedLearners;

/**
 * `GET /api/v1/academic/{frameworks,subjects,subject-groups,offerings,selection-rules}` and
 * `POST .../selection-rules/validate` (Book D ACA-01 §6). Read-only reference data a mobile
 * subject-selection flow needs before `SubjectSelectionsController` ever submits anything — every
 * list is school-scoped only, no learner-linkage needed, since none of it is learner-specific.
 * `validate` is the one exception: it previews `SubjectSelectionRuleEngine`'s own verdict for a
 * named learner, so it requires the caller (guardian or the learner themselves) to be linked to
 * that student, the same way every other learner-specific endpoint in this codebase does.
 */
final class CurriculumController
{
    public function frameworks(Request $request): JsonResponse
    {
        $frameworks = CurriculumFramework::query()->where('status', 'active')->orderBy('name')->get();

        return ApiResponse::ok($frameworks->map(fn (CurriculumFramework $f): array => [
            'id' => $f->ulid,
            'code' => $f->code,
            'name' => $f->name,
            'authority' => $f->authority,
            'continuous_assessment_model' => $f->continuous_assessment_model,
        ])->values()->all());
    }

    public function subjects(Request $request): JsonResponse
    {
        $request->validate(['level' => ['nullable', 'string'], 'pathway' => ['nullable', 'string'], 'framework' => ['nullable', 'string']]);

        $query = Subject::query()->where('is_active', true);

        if ($request->filled('framework')) {
            $frameworkId = CurriculumFramework::query()->where('ulid', $request->string('framework'))->value('id');
            $query->where('framework_id', $frameworkId);
        }

        if ($request->filled('level') || $request->filled('pathway')) {
            $offerings = LevelSubjectOffering::query()->where('is_available', true);

            if ($request->filled('level')) {
                $gradeLevelId = GradeLevel::query()->where('ulid', $request->string('level'))->value('id');
                $offerings->where('grade_level_id', $gradeLevelId);
            }

            if ($request->filled('pathway')) {
                $offerings->whereHas('pathway', fn ($q) => $q->where('code', $request->string('pathway')));
            }

            $query->whereIn('id', $offerings->pluck('subject_id'));
        }

        return ApiResponse::ok($query->orderBy('name')->get()->map(fn (Subject $s): array => [
            'id' => $s->ulid,
            'code' => $s->code,
            'name' => $s->name,
            'short_name' => $s->short_name,
            'subject_type' => $s->subject_type,
            'is_examinable' => $s->is_examinable,
        ])->values()->all());
    }

    public function subjectGroups(Request $request): JsonResponse
    {
        $groups = SubjectGroup::query()->where('is_active', true)->orderBy('name')->get();

        return ApiResponse::ok($groups->map(fn (SubjectGroup $g): array => [
            'id' => $g->ulid,
            'code' => $g->code,
            'name' => $g->name,
            'requires_laboratory' => $g->requires_laboratory,
            'requires_workshop' => $g->requires_workshop,
        ])->values()->all());
    }

    public function offerings(Request $request): JsonResponse
    {
        $request->validate(['level' => ['nullable', 'string'], 'year' => ['nullable', 'string'], 'pathway' => ['nullable', 'string']]);

        $yearId = $request->filled('year')
            ? AcademicYear::query()->where('ulid', $request->string('year'))->value('id')
            : SessionContext::yearId();

        $query = LevelSubjectOffering::query()->where('is_available', true)->where('academic_year_id', $yearId)->with('subject:id,ulid,name');

        if ($request->filled('level')) {
            $gradeLevelId = GradeLevel::query()->where('ulid', $request->string('level'))->value('id');
            $query->where('grade_level_id', $gradeLevelId);
        }

        if ($request->filled('pathway')) {
            $query->whereHas('pathway', fn ($q) => $q->where('code', $request->string('pathway')));
        }

        return ApiResponse::ok($query->get()->map(fn (LevelSubjectOffering $o): array => [
            'subject' => ['id' => $o->subject->ulid, 'name' => $o->subject->name],
            'is_compulsory' => $o->is_compulsory,
            'option_block' => $o->option_block,
            'periods_per_week' => $o->periods_per_week,
            'max_learners' => $o->max_learners,
        ])->values()->all());
    }

    public function selectionRules(Request $request): JsonResponse
    {
        $request->validate(['level' => ['nullable', 'string'], 'pathway' => ['nullable', 'string']]);

        $query = SubjectSelectionRule::query()->where('is_active', true);

        if ($request->filled('level')) {
            $gradeLevelId = GradeLevel::query()->where('ulid', $request->string('level'))->value('id');
            $query->where(fn ($q) => $q->where('grade_level_id', $gradeLevelId)->orWhereNull('grade_level_id'));
        }

        if ($request->filled('pathway')) {
            $query->where(fn ($q) => $q->where('pathway', $request->string('pathway'))->orWhereNull('pathway'));
        }

        return ApiResponse::ok($query->get()->map(fn (SubjectSelectionRule $r): array => [
            'id' => $r->ulid,
            'rule_type' => $r->rule_type,
            'severity' => $r->severity,
            'message' => $r->message,
            'min_count' => $r->min_count,
            'max_count' => $r->max_count,
        ])->values()->all());
    }

    public function validateSelection(Request $request, SubjectSelectionRuleEngine $engine, LinkedLearners $linked): JsonResponse
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

        $student = $link->student;
        $subjectIds = Subject::query()->whereIn('ulid', $data['subject_ids'])->pluck('id');
        abort_if($subjectIds->count() !== count($data['subject_ids']), 422, 'Some subjects were not recognised.');

        $framework = Subject::query()->whereKey($subjectIds->first())->value('framework_id');

        $result = $engine->validate(
            $subjectIds,
            (int) $framework,
            $student->grade_level_id,
            $student->pathway,
            $student->school_id,
            (int) SessionContext::yearId(),
            $student->id,
        );

        return ApiResponse::ok([
            'is_valid' => $result->isValid,
            'warnings' => $result->warnings->map(fn (Violation $v): string => $v->message)->values()->all(),
            'blocks' => $result->blocks->map(fn (Violation $v): string => $v->message)->values()->all(),
        ]);
    }
}
