<?php

declare(strict_types=1);

namespace Modules\Welfare\Livewire\Behaviour;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Models\Student;
use Modules\Welfare\Domain\Actions\RecordBehaviourAction;
use Modules\Welfare\Domain\DataObjects\RecordBehaviourData;
use Modules\Welfare\Models\BehaviourCategory;

/**
 * `Behaviour\Record` (Book G BRD-07 §5, `behaviour.record` — mobile-
 * first, category picker with positive listed first). Records both
 * polarities through the same one action call — there is no separate
 * "record misconduct" path, matching BR-BRD-07-001.
 */
#[Title('Record behaviour')]
#[Layout('layouts.app')]
final class Record extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $studentId = null;

    public ?int $categoryId = null;

    public string $description = '';

    public ?string $location = null;

    public ?string $context = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('behaviour.record');
    }

    public function record(): void
    {
        $this->validate([
            'studentId' => ['required', 'integer'],
            'categoryId' => ['required', 'integer'],
            'description' => ['required', 'string'],
        ]);

        $term = $this->school->currentAcademicYear()?->currentTerm();

        if ($term === null) {
            $this->toast(__('No current term is set.'), 'danger');

            return;
        }

        app(RecordBehaviourAction::class)->execute(new RecordBehaviourData(
            schoolId: $this->school->id,
            academicYearId: (int) $term->academic_year_id,
            termId: $term->id,
            studentId: (int) $this->studentId,
            categoryId: (int) $this->categoryId,
            description: $this->description,
            occurredAt: Carbon::now(),
            reportedByUserId: (int) Auth::id(),
            location: $this->location,
            context: $this->context,
        ));

        $this->reset(['description', 'location', 'context']);
        $this->toast(__('Behaviour recorded.'));
    }

    public function render(): View
    {
        return view('welfare::behaviour.record', [
            'students' => Student::where('school_id', $this->school->id)->orderBy('first_name')->limit(300)->get(['id', 'first_name', 'last_name']),
            'positiveCategories' => BehaviourCategory::where('school_id', $this->school->id)->where('polarity', 'positive')->where('is_active', true)->orderBy('sort_order')->get(),
            'negativeCategories' => BehaviourCategory::where('school_id', $this->school->id)->where('polarity', 'negative')->where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }
}
