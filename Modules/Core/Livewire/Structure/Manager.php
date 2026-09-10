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
use Modules\Core\Domain\DataObjects\Schools\CreateClassData;
use Modules\Core\Domain\DataObjects\Schools\CreateGradeLevelData;
use Modules\Core\Domain\DataObjects\Schools\CreateSectionData;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Core\Models\SchoolClass;

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

    public string $sectionCode = '';

    public string $sectionName = '';

    public string $sectionType = 'primary';

    public bool $showGradeLevelModal = false;

    public ?int $gradeLevelSectionId = null;

    public string $gradeLevelCode = '';

    public string $gradeLevelName = '';

    public ?int $gradeLevelOrdinal = null;

    public bool $showClassModal = false;

    public ?int $classGradeLevelId = null;

    public string $classCode = '';

    public string $className = '';

    public int $classCapacity = 40;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
    }

    public function openGradeLevelModal(int $sectionId): void
    {
        $this->gradeLevelSectionId = $sectionId;
        $this->showGradeLevelModal = true;
    }

    public function openClassModal(int $gradeLevelId): void
    {
        $this->classGradeLevelId = $gradeLevelId;
        $this->showClassModal = true;
    }

    public function createSection(): void
    {
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
