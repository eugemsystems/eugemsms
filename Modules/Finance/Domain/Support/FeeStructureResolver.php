<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Support;

use Carbon\CarbonInterface;
use Modules\Academic\Domain\Support\SubjectEnrolmentQuery;
use Modules\Core\Models\Term;
use Modules\Finance\Models\FeeStructure;
use Modules\Finance\Models\FeeStructureRule;
use Modules\People\Models\Student;

/**
 * Book B FIN-02 §3/§5 ⭐/BR-FIN-02-001/002/003. Evaluates every active
 * structure for the school/year/term in `priority` ascending order; the
 * first whose rules **all** match is selected. Every structure
 * evaluated — matched or not — is recorded, because the resolution
 * trace is the answer to every fee dispute the school will ever have.
 *
 * `custom_field` rules are not evaluated (no custom-field store exists
 * yet) — a rule of that type always fails to match, the same "skip and
 * report" treatment `SubjectSelectionRuleEngine` (Book D ACA-01) gives
 * an unsupported rule type, never a silent pass.
 *
 * `$subjectCountOverride` (Book D ACA-02 §7/BR-ACA-02-016) lets
 * `PreviewIndicativeFeeAction` resolve against a *proposed* subject
 * set that has no `learner_subject_enrolments` rows yet — omitted, the
 * real billable count is read as usual.
 */
final class FeeStructureResolver
{
    public function __construct(
        private readonly SubjectEnrolmentQuery $subjectEnrolments,
    ) {}

    /**
     * @return array{selected: ?FeeStructure, trace: array<string, mixed>}
     */
    public function resolve(Student $student, Term $term, CarbonInterface $on, ?int $subjectCountOverride = null): array
    {
        $attributes = $this->attributesFor($student, $term, $on, $subjectCountOverride);

        $structures = FeeStructure::query()
            ->where('school_id', $student->school_id)
            ->where('academic_year_id', $term->academic_year_id)
            ->where('status', 'active')
            ->where(fn ($q) => $q->whereNull('term_id')->orWhere('term_id', $term->id))
            ->with('rules')
            ->orderBy('priority')
            ->get();

        $evaluated = [];
        $selected = null;

        foreach ($structures as $structure) {
            $failedRule = null;
            $matchedDescriptions = [];

            foreach ($structure->rules as $rule) {
                $description = $this->describe($rule);

                if ($this->ruleMatches($rule, $attributes)) {
                    $matchedDescriptions[] = $description;
                } else {
                    $failedRule = $description;
                    break;
                }
            }

            $matched = $failedRule === null;

            $evaluated[] = [
                'structure' => $structure->name,
                'priority' => $structure->priority,
                'matched' => $matched,
                ...($matched ? ['rules_matched' => $matchedDescriptions] : ['failed_rule' => $failedRule]),
            ];

            if ($matched && $selected === null) {
                $selected = $structure;
            }
        }

        return [
            'selected' => $selected,
            'trace' => [
                'learner' => ['id' => $student->ulid, 'admission_no' => $student->admission_number],
                'attributes_evaluated' => $attributes,
                'structures_evaluated' => $evaluated,
                'selected_structure' => $selected !== null ? ['id' => $selected->ulid, 'version' => $selected->version] : null,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function attributesFor(Student $student, Term $term, CarbonInterface $on, ?int $subjectCountOverride = null): array
    {
        return [
            'section' => $student->section?->code,
            'grade_level' => $student->gradeLevel?->code,
            'class' => $student->schoolClass?->code,
            'enrolment_type' => $student->enrolment_type,
            'residency' => $student->residency,
            'pathway' => $student->pathway,
            'house' => $student->house?->code,
            'nationality' => $student->nationality,
            'gender' => $student->gender,
            'entry_cohort' => $student->entry_cohort_year,
            'subject_count' => $subjectCountOverride ?? $this->subjectEnrolments->billableCountOn($student, $term, $on),
            'joined_on' => $student->enrolled_on?->toDateString(),
        ];
    }

    private function describe(FeeStructureRule $rule): string
    {
        $value = is_array($rule->value) ? implode(',', $rule->value) : (string) $rule->value;

        return "{$rule->attribute} {$rule->operator} {$value}";
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function ruleMatches(FeeStructureRule $rule, array $attributes): bool
    {
        if ($rule->attribute === 'custom_field') {
            return false;
        }

        $actual = $attributes[$rule->attribute] ?? null;

        return match ($rule->operator) {
            'equals' => (string) $actual === (string) $rule->value,
            'in' => in_array((string) $actual, array_map('strval', (array) $rule->value), true),
            'not_in' => ! in_array((string) $actual, array_map('strval', (array) $rule->value), true),
            'exists' => $actual !== null,
            'between' => is_numeric($actual)
                && (float) $actual >= (float) ($rule->value[0] ?? PHP_INT_MIN)
                && (float) $actual <= (float) ($rule->value[1] ?? PHP_INT_MAX),
            default => false,
        };
    }
}
