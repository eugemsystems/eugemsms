<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Allocation;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\BulkAllocateClassAction;
use Modules\Academic\Domain\DataObjects\BulkAllocateClassData;
use Modules\Academic\Models\ClassAllocation;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\House;
use Modules\Core\Models\School;
use Modules\Core\Models\SchoolClass;
use Modules\People\Domain\Actions\AllocateStudentsToHouseAction;
use Modules\People\Domain\DataObjects\AllocateStudentsToHouseData;
use Modules\People\Models\Student;

/**
 * `Allocation\Bulk` (Book D ACA-02 §5 and Book C PPL-01 §2, `academic.allocation.manage`). Pick a
 * grade level, tick learners (optionally only those with no class yet this term) and place them in
 * a class and/or a house in one step. Each result reports who moved and who was skipped with the
 * reason — wrong grade level, class full, already there — rather than failing the whole batch.
 */
#[Title('Bulk allocation')]
#[Layout('layouts.app')]
final class Bulk extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;
    use Toasts;

    public ?int $gradeLevelId = null;

    public bool $onlyUnallocated = true;

    /** @var array<int, int> */
    public array $selected = [];

    public ?int $classId = null;

    public ?int $houseId = null;

    /** @var array<int, array{student_id: int, reason: string}> */
    public array $skipped = [];

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
        $this->authorizePermission('academic.allocation.manage');
    }

    public function updatedGradeLevelId(): void
    {
        $this->selected = [];
        $this->skipped = [];
    }

    public function selectAll(): void
    {
        $this->selected = $this->learners()->pluck('id')->all();
    }

    public function applyClass(): void
    {
        $this->authorizePermission('academic.allocation.manage');
        $this->resetErrorBag();
        $termId = SessionContext::termId();

        if ($termId === null || $this->classId === null) {
            $this->addError('classId', __('Choose a class (and make sure a term is current).'));

            return;
        }

        try {
            $result = app(BulkAllocateClassAction::class)->execute(new BulkAllocateClassData(
                array_map('intval', $this->selected), SchoolClass::query()->where('school_id', $this->school->id)->findOrFail($this->classId)->id, $termId, (int) Auth::id(), now(),
            ));
        } catch (ValidationException $e) {
            $this->setErrorBag($e->errors());

            return;
        }

        $this->finish($result, __('placed in the class'));
    }

    public function applyHouse(): void
    {
        $this->authorizePermission('academic.allocation.manage');
        $this->resetErrorBag();

        if ($this->houseId === null) {
            $this->addError('houseId', __('Choose a house.'));

            return;
        }

        try {
            $result = app(AllocateStudentsToHouseAction::class)->execute(new AllocateStudentsToHouseData(
                array_map('intval', $this->selected), House::query()->where('school_id', $this->school->id)->findOrFail($this->houseId)->id, (int) Auth::id(),
            ));
        } catch (ValidationException $e) {
            $this->setErrorBag($e->errors());

            return;
        }

        $this->finish($result, __('placed in the house'));
    }

    /**
     * @param  array{allocated: array<int, int>, skipped: array<int, array{student_id: int, reason: string}>}  $result
     */
    private function finish(array $result, string $what): void
    {
        $this->skipped = $result['skipped'];
        $this->selected = [];
        $this->toast(__(':done learner(s) :what; :skipped skipped.', ['done' => count($result['allocated']), 'what' => $what, 'skipped' => count($result['skipped'])]));
    }

    /**
     * @return Collection<int, Student>
     */
    private function learners(): Collection
    {
        if ($this->gradeLevelId === null) {
            return collect();
        }

        $termId = SessionContext::termId();
        $allocated = $termId !== null ? ClassAllocation::query()->where('term_id', $termId)->where('status', 'confirmed')->pluck('student_id') : collect();

        return Student::query()->where('school_id', $this->school->id)->where('grade_level_id', $this->gradeLevelId)->whereIn('status', ['enrolled', 'active'])
            ->when($this->onlyUnallocated, fn ($q) => $q->whereNotIn('id', $allocated))
            ->orderBy('last_name')->orderBy('first_name')->limit(300)->get();
    }

    public function render(): View
    {
        $learners = $this->learners();

        return view('academic::allocation.bulk', [
            'gradeLevels' => GradeLevel::query()->where('school_id', $this->school->id)->orderBy('ordinal')->get(['id', 'name']),
            'classes' => SchoolClass::query()->where('school_id', $this->school->id)->where('is_active', true)->when($this->gradeLevelId !== null, fn ($q) => $q->where('grade_level_id', $this->gradeLevelId))->orderBy('name')->get(['id', 'name', 'capacity']),
            'houses' => House::query()->where('school_id', $this->school->id)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'learners' => $learners,
            'names' => $learners->mapWithKeys(fn (Student $s): array => [$s->id => $s->last_name.', '.$s->first_name])->all(),
        ]);
    }
}
