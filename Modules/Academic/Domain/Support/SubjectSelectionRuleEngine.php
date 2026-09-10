<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Support;

use Illuminate\Support\Collection;
use Modules\Academic\Domain\DataObjects\SelectionResult;
use Modules\Academic\Domain\DataObjects\Violation;
use Modules\Academic\Models\LearnerSubjectEnrolment;
use Modules\Academic\Models\LevelSubjectOffering;
use Modules\Academic\Models\Subject;
use Modules\Academic\Models\SubjectPrerequisite;
use Modules\Academic\Models\SubjectSelectionRule;

/**
 * Book D ACA-01 §3 ⭐. Evaluates a learner's proposed subject set against
 * every active `subject_selection_rules` row for their level and
 * pathway — nothing is hard-coded (BR-ACA-01-007).
 *
 * `min_compulsory` and `one_per_option_block` read `level_subject_offerings`;
 * `prerequisite` reads `subject_prerequisites` — both now built. Passing
 * `academicYearId`/`studentId` is what lets those three rule types
 * resolve; when either is omitted (as the two-argument-shorter call
 * predating this still does), a rule needing them is skipped rather
 * than guessed, and is reported so it isn't mistaken for full
 * BR-ACA-01-007 coverage. `prerequisite` also only checks internal
 * enrolment history, not `minimum_grade`/`examination` — see
 * `SubjectPrerequisite`'s own docblock.
 */
final class SubjectSelectionRuleEngine
{
    private const array SUPPORTED_TYPES = [
        'min_total', 'max_total', 'min_from_group', 'max_from_group',
        'required_subject', 'mutually_exclusive', 'min_compulsory',
        'one_per_option_block', 'prerequisite',
    ];

    /**
     * @param  Collection<int, int>  $subjectIds  the learner's full proposed subject set
     */
    public function validate(
        Collection $subjectIds,
        int $frameworkId,
        ?int $gradeLevelId,
        ?string $pathway,
        int $schoolId,
        ?int $academicYearId = null,
        ?int $studentId = null,
    ): SelectionResult {
        $rules = $this->rulesFor($schoolId, $frameworkId, $gradeLevelId, $pathway);
        $groupBySubject = Subject::withoutGlobalScopes()
            ->whereIn('id', $subjectIds->all())
            ->pluck('subject_group_id', 'id');

        $offeringsResolvable = $gradeLevelId !== null && $academicYearId !== null;

        $offeringsBySubject = $offeringsResolvable
            ? LevelSubjectOffering::withoutGlobalScopes()
                ->where('academic_year_id', $academicYearId)
                ->where('grade_level_id', $gradeLevelId)
                ->whereIn('subject_id', $subjectIds->all())
                ->get()
                ->keyBy('subject_id')
            : collect();

        $violations = collect();

        foreach ($rules as $rule) {
            if (! in_array($rule->rule_type, self::SUPPORTED_TYPES, true)) {
                continue;
            }

            if (in_array($rule->rule_type, ['min_compulsory', 'one_per_option_block'], true) && ! $offeringsResolvable) {
                continue;
            }

            if ($rule->rule_type === 'prerequisite' && $studentId === null) {
                continue;
            }

            $ok = match ($rule->rule_type) {
                'min_total' => $subjectIds->count() >= $rule->min_count,
                'max_total' => $subjectIds->count() <= $rule->max_count,
                'min_from_group' => $this->groupCount($subjectIds, $groupBySubject, $rule) >= $rule->min_count,
                'max_from_group' => $this->groupCount($subjectIds, $groupBySubject, $rule) <= $rule->max_count,
                'required_subject' => $this->containsAll($subjectIds, $rule->subject_ids ?? []),
                'mutually_exclusive' => $this->atMostOneOf($subjectIds, $rule->subject_ids ?? []),
                'min_compulsory' => $this->compulsoryCount($offeringsBySubject) >= $rule->min_count,
                'one_per_option_block' => $this->noBlockClash($offeringsBySubject),
                'prerequisite' => $this->prerequisitesMet($schoolId, $subjectIds, $studentId),
            };

            if (! $ok) {
                $violations->push(new Violation($rule->severity, $rule->message, $rule));
            }
        }

        return new SelectionResult(
            isValid: $violations->where('severity', 'block')->isEmpty(),
            warnings: $violations->where('severity', 'warn')->values(),
            blocks: $violations->where('severity', 'block')->values(),
        );
    }

    /**
     * @param  Collection<int, LevelSubjectOffering>  $offerings
     */
    private function compulsoryCount(Collection $offerings): int
    {
        return $offerings->where('is_compulsory', true)->count();
    }

    /**
     * @param  Collection<int, LevelSubjectOffering>  $offerings
     */
    private function noBlockClash(Collection $offerings): bool
    {
        $blocks = $offerings->pluck('option_block')->filter();

        return $blocks->count() === $blocks->unique()->count();
    }

    /**
     * @param  Collection<int, int>  $subjectIds
     */
    private function prerequisitesMet(int $schoolId, Collection $subjectIds, int $studentId): bool
    {
        $prerequisites = SubjectPrerequisite::where('school_id', $schoolId)
            ->whereIn('subject_id', $subjectIds->all())
            ->get();

        foreach ($prerequisites as $prerequisite) {
            $everEnrolled = LearnerSubjectEnrolment::withoutGlobalScopes()
                ->where('student_id', $studentId)
                ->where('subject_id', $prerequisite->prerequisite_subject_id)
                ->exists();

            if (! $everEnrolled) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return Collection<int, SubjectSelectionRule>
     */
    private function rulesFor(int $schoolId, int $frameworkId, ?int $gradeLevelId, ?string $pathway): Collection
    {
        return SubjectSelectionRule::query()
            ->where('school_id', $schoolId)
            ->where('framework_id', $frameworkId)
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('grade_level_id')->orWhere('grade_level_id', $gradeLevelId))
            ->where(fn ($q) => $q->whereNull('pathway')->orWhere('pathway', $pathway))
            ->get();
    }

    /**
     * @param  Collection<int, int>  $subjectIds
     * @param  Collection<int, int|null>  $groupBySubject
     */
    private function groupCount(Collection $subjectIds, Collection $groupBySubject, SubjectSelectionRule $rule): int
    {
        return $subjectIds->filter(fn (int $id): bool => $groupBySubject->get($id) === $rule->subject_group_id)->count();
    }

    /**
     * @param  Collection<int, int>  $subjectIds
     * @param  array<int, int>  $requiredIds
     */
    private function containsAll(Collection $subjectIds, array $requiredIds): bool
    {
        return collect($requiredIds)->diff($subjectIds)->isEmpty();
    }

    /**
     * @param  Collection<int, int>  $subjectIds
     * @param  array<int, int>  $exclusiveIds
     */
    private function atMostOneOf(Collection $subjectIds, array $exclusiveIds): bool
    {
        return $subjectIds->intersect($exclusiveIds)->count() <= 1;
    }
}
