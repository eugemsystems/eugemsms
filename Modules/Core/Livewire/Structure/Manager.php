<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Structure;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Actions\Schools\CreateClassAction;
use Modules\Core\Domain\Actions\Schools\CreateGradeLevelAction;
use Modules\Core\Domain\Actions\Schools\CreateSectionAction;
use Modules\Core\Domain\Actions\Schools\UpdateClassAction;
use Modules\Core\Domain\Actions\Schools\UpdateGradeLevelAction;
use Modules\Core\Domain\Actions\Schools\UpdateSectionAction;
use Modules\Core\Domain\DataObjects\Schools\CreateClassData;
use Modules\Core\Domain\DataObjects\Schools\CreateGradeLevelData;
use Modules\Core\Domain\DataObjects\Schools\CreateSectionData;
use Modules\Core\Domain\DataObjects\Schools\UpdateClassData;
use Modules\Core\Domain\DataObjects\Schools\UpdateGradeLevelData;
use Modules\Core\Domain\DataObjects\Schools\UpdateSectionData;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\School;
use Modules\Core\Models\SchoolClass;
use Modules\Core\Models\SchoolSection;

/**
 * `Core\Structure\Manager` (Book A CORE-02 §5). Sections → grade levels →
 * classes. Classes are shown for the school's current academic year only
 * — BR-CORE-02-004: a class always belongs to exactly one year, created
 * fresh each year, so there is no single "all classes" list that would
 * mean anything. Section order is fixed at creation for now: reordering
 * needs its own action (a section's `sort_order` is purely cosmetic, but
 * no ACT-ReorderSections exists yet) — a deliberately smaller first cut
 * rather than wiring drag-and-drop against a write path that isn't there.
 */
#[Title('Academic structure')]
#[Layout('layouts.app')]
final class Manager extends Component
{
    use InteractsWithSchool;
    use Toasts;

    public bool $showSectionModal = false;

    public ?int $editingSectionId = null;

    public string $sectionCode = '';

    public string $sectionName = '';

    public string $sectionType = 'primary';

    public bool $showGradeLevelModal = false;

    public ?int $editingGradeLevelId = null;

    public ?int $gradeLevelSectionId = null;

    public string $gradeLevelCode = '';

    public string $gradeLevelName = '';

    public ?int $gradeLevelOrdinal = null;

    public bool $showClassModal = false;

    public ?int $editingClassId = null;

    public ?int $classGradeLevelId = null;

    public string $classCode = '';

    public string $className = '';

    public int $classCapacity = 40;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
    }

    public function openSectionModal(): void
    {
        $this->reset(['editingSectionId', 'sectionCode', 'sectionName']);
        $this->sectionType = 'primary';
        $this->showSectionModal = true;
    }

    public function openEditSectionModal(int $sectionId): void
    {
        $section = SchoolSection::where('school_id', $this->school->id)->findOrFail($sectionId);

        $this->editingSectionId = $section->id;
        $this->sectionCode = $section->code;
        $this->sectionName = $section->name;
        $this->sectionType = $section->type;
        $this->showSectionModal = true;
    }

    public function openGradeLevelModal(int $sectionId): void
    {
        $this->reset(['editingGradeLevelId', 'gradeLevelCode', 'gradeLevelName', 'gradeLevelOrdinal']);
        $this->gradeLevelSectionId = $sectionId;
        $this->showGradeLevelModal = true;
    }

    public function openEditGradeLevelModal(int $gradeLevelId): void
    {
        $gradeLevel = GradeLevel::where('school_id', $this->school->id)->findOrFail($gradeLevelId);

        $this->editingGradeLevelId = $gradeLevel->id;
        $this->gradeLevelSectionId = $gradeLevel->section_id;
        $this->gradeLevelCode = $gradeLevel->code;
        $this->gradeLevelName = $gradeLevel->name;
        $this->gradeLevelOrdinal = $gradeLevel->ordinal;
        $this->showGradeLevelModal = true;
    }

    public function openClassModal(int $gradeLevelId): void
    {
        $this->reset(['editingClassId', 'classCode', 'className']);
        $this->classCapacity = 40;
        $this->classGradeLevelId = $gradeLevelId;
        $this->showClassModal = true;
    }

    public function openEditClassModal(int $classId): void
    {
        $class = SchoolClass::where('school_id', $this->school->id)->findOrFail($classId);

        $this->editingClassId = $class->id;
        $this->classGradeLevelId = $class->grade_level_id;
        $this->classCode = $class->code;
        $this->className = $class->name;
        $this->classCapacity = $class->capacity;
        $this->showClassModal = true;
    }

    public function createSection(): void
    {
        if ($this->editingSectionId !== null) {
            app(UpdateSectionAction::class)->execute(new UpdateSectionData(
                schoolId: $this->school->id,
                sectionId: $this->editingSectionId,
                code: $this->sectionCode,
                name: $this->sectionName,
                type: $this->sectionType,
            ));

            $this->reset(['sectionCode', 'sectionName', 'editingSectionId', 'showSectionModal']);
            $this->sectionType = 'primary';

            $this->toast(__('Section updated.'));

            return;
        }

        app(CreateSectionAction::class)->execute(new CreateSectionData(
            schoolId: $this->school->id,
            code: $this->sectionCode,
            name: $this->sectionName,
            type: $this->sectionType,
        ));

        $this->reset(['sectionCode', 'sectionName', 'showSectionModal']);
        $this->sectionType = 'primary';

        $this->toast(__('Section created.'));
    }

    public function createGradeLevel(): void
    {
        if ($this->gradeLevelSectionId === null) {
            return;
        }

        if ($this->editingGradeLevelId !== null) {
            app(UpdateGradeLevelAction::class)->execute(new UpdateGradeLevelData(
                schoolId: $this->school->id,
                gradeLevelId: $this->editingGradeLevelId,
                code: $this->gradeLevelCode,
                name: $this->gradeLevelName,
                ordinal: $this->gradeLevelOrdinal ?? 0,
            ));

            $this->reset(['gradeLevelCode', 'gradeLevelName', 'gradeLevelOrdinal', 'editingGradeLevelId', 'showGradeLevelModal']);

            $this->toast(__('Grade level updated.'));

            return;
        }

        app(CreateGradeLevelAction::class)->execute(new CreateGradeLevelData(
            schoolId: $this->school->id,
            sectionId: $this->gradeLevelSectionId,
            code: $this->gradeLevelCode,
            name: $this->gradeLevelName,
            ordinal: $this->gradeLevelOrdinal ?? 0,
        ));

        $this->reset(['gradeLevelCode', 'gradeLevelName', 'gradeLevelOrdinal', 'showGradeLevelModal']);

        $this->toast(__('Grade level created.'));
    }

    public function createClass(): void
    {
        if ($this->editingClassId !== null) {
            app(UpdateClassAction::class)->execute(new UpdateClassData(
                schoolId: $this->school->id,
                classId: $this->editingClassId,
                code: $this->classCode,
                name: $this->className,
                capacity: $this->classCapacity,
            ));

            $this->reset(['classCode', 'className', 'editingClassId', 'showClassModal']);
            $this->classCapacity = 40;

            $this->toast(__('Class updated.'));

            return;
        }

        $year = $this->school->currentAcademicYear();

        if ($year === null) {
            $this->addError('className', __('This school has no current academic year yet.'));

            return;
        }

        if ($this->classGradeLevelId === null) {
            return;
        }

        app(CreateClassAction::class)->execute(new CreateClassData(
            schoolId: $this->school->id,
            academicYearId: $year->id,
            gradeLevelId: $this->classGradeLevelId,
            code: $this->classCode,
            name: $this->className,
            capacity: $this->classCapacity,
        ));

        $this->reset(['classCode', 'className', 'showClassModal']);
        $this->classCapacity = 40;

        $this->toast(__('Class created.'));
    }

    public function render(): View
    {
        $year = $this->school->currentAcademicYear();

        $sections = $this->school->sections()
            ->with(['gradeLevels' => fn ($query) => $query->orderBy('ordinal')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $classesByGradeLevel = $year !== null
            ? SchoolClass::query()
                ->where('school_id', $this->school->id)
                ->where('academic_year_id', $year->id)
                ->orderBy('name')
                ->get()
                ->groupBy('grade_level_id')
            : collect();

        return view('core::structure.manager', [
            'sections' => $sections,
            'currentYear' => $year,
            'classesByGradeLevel' => $classesByGradeLevel,
        ]);
    }
}
