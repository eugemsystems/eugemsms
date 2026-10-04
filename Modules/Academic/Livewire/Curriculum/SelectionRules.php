<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Curriculum;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\CreateSubjectSelectionRuleAction;
use Modules\Academic\Domain\DataObjects\CreateSubjectSelectionRuleData;
use Modules\Academic\Domain\Support\SubjectSelectionRuleEngine;
use Modules\Academic\Models\CurriculumFramework;
use Modules\Academic\Models\Pathway;
use Modules\Academic\Models\Subject;
use Modules\Academic\Models\SubjectGroup;
use Modules\Academic\Models\SubjectSelectionRule;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\School;

/**
 * `Curriculum\SelectionRules` (Book D ACA-01 §3 ⭐/BR-ACA-01-007/008,
 * `academic.selection_rules.manage` ⚠). The rule builder plus a live
 * tester — pick a level, a pathway, and a subject set, run it through
 * the SAME `SubjectSelectionRuleEngine` `EnrolSubjectAction` enforces
 * server-side, and see pass or fail before anything is saved anywhere.
 * `min_compulsory`/`one_per_option_block`/`prerequisite` are skipped by
 * the tester (no `academicYearId`/`studentId` context here, same as
 * the engine's own documented skip behaviour) — it tests count/group/
 * required/exclusive rules, which is what this screen exists to let an
 * administrator sanity-check before a learner ever sees the result.
 */
#[Title('Subject selection rules')]
#[Layout('layouts.app')]
final class SelectionRules extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $frameworkId = null;

    public ?int $gradeLevelId = null;

    public string $pathway = '';

    public string $ruleType = 'max_total';

    public string $severity = 'block';

    public string $message = '';

    public ?int $subjectGroupId = null;

    public string $minCount = '';

    public string $maxCount = '';

    public string $sourceReference = '';

    public bool $requiresConfirmation = false;

    // Live tester
    public ?int $testerGradeLevelId = null;

    public string $testerPathway = '';

    /** @var array<int, int> */
    public array $testerSubjectIds = [];

    /** @var array{isValid: bool, warnings: array<int, string>, blocks: array<int, string>}|null */
    public ?array $testResult = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('academic.curriculum.view');

        $this->frameworkId = CurriculumFramework::where('school_id', $school->id)->orderByDesc('effective_from')->value('id');
    }

    public function create(): void
    {
        $this->authorizePermission('academic.selection_rules.manage');

        $this->validate([
            'frameworkId' => ['required', 'integer'],
            'ruleType' => ['required', 'in:min_total,max_total,min_from_group,max_from_group,required_subject,mutually_exclusive,min_compulsory,one_per_option_block,prerequisite'],
            'severity' => ['required', 'in:block,warn'],
            'message' => ['required', 'string', 'max:255'],
        ]);

        app(CreateSubjectSelectionRuleAction::class)->execute(new CreateSubjectSelectionRuleData(
            schoolId: $this->school->id,
            frameworkId: (int) $this->frameworkId,
            ruleType: $this->ruleType,
            severity: $this->severity,
            message: $this->message,
            gradeLevelId: $this->gradeLevelId,
            pathway: $this->pathway !== '' ? $this->pathway : null,
            subjectGroupId: $this->subjectGroupId,
            minCount: $this->minCount !== '' ? (int) $this->minCount : null,
            maxCount: $this->maxCount !== '' ? (int) $this->maxCount : null,
            sourceReference: $this->sourceReference !== '' ? $this->sourceReference : null,
            requiresConfirmation: $this->requiresConfirmation,
        ));

        $this->reset(['gradeLevelId', 'pathway', 'message', 'subjectGroupId', 'minCount', 'maxCount', 'sourceReference', 'requiresConfirmation']);
        $this->toast(__('Selection rule created.'));
    }

    public function runTest(): void
    {
        if ($this->frameworkId === null || $this->testerSubjectIds === []) {
            $this->testResult = null;

            return;
        }

        $result = app(SubjectSelectionRuleEngine::class)->validate(
            collect($this->testerSubjectIds)->map(fn ($id): int => (int) $id),
            (int) $this->frameworkId,
            $this->testerGradeLevelId,
            $this->testerPathway !== '' ? $this->testerPathway : null,
            $this->school->id,
        );

        $this->testResult = [
            'isValid' => $result->isValid,
            'warnings' => $result->warnings->pluck('message')->all(),
            'blocks' => $result->blocks->pluck('message')->all(),
        ];
    }

    public function render(): View
    {
        return view('academic::curriculum.selection-rules', [
            'rules' => SubjectSelectionRule::where('school_id', $this->school->id)->with('subjectGroup')->orderByDesc('id')->get(),
            'frameworks' => CurriculumFramework::where('school_id', $this->school->id)->orderByDesc('effective_from')->get(),
            'gradeLevels' => GradeLevel::where('school_id', $this->school->id)->orderBy('ordinal')->get(),
            'groups' => SubjectGroup::where('school_id', $this->school->id)->orderBy('name')->get(),
            'subjects' => Subject::where('school_id', $this->school->id)->orderBy('name')->get(),
            'pathways' => Pathway::where('school_id', $this->school->id)->orderBy('name')->get(),
        ]);
    }
}
