<?php

declare(strict_types=1);

namespace Modules\People\Livewire\Students;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Domain\Actions\LinkSiblingsAction;
use Modules\People\Domain\Actions\UnlinkSiblingsAction;
use Modules\People\Domain\DataObjects\LinkSiblingsData;
use Modules\People\Domain\DataObjects\UnlinkSiblingsData;
use Modules\People\Models\Student;
use Modules\People\Models\StudentSibling;

/**
 * `People\Students\Siblings` (Book C PPL-01 §8, `people.students.update`). Sibling links are symmetric and feed sibling discounts (BR-PPL-01-018). The sibling is picked by search and re-resolved server-side.
 */
#[Title('Siblings')]
#[Layout('layouts.app')]
final class Siblings extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public Student $student;

    public function mount(School $school, Student $student): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('people.students.update');

        abort_unless($student->school_id === $school->id, 404);

        $this->student = $student;
    }

    public string $search = '';

    public ?int $siblingId = null;

    public string $relationship = 'full';

    public function select(int $studentId): void
    {
        $this->authorizePermission('people.students.update');
        $this->siblingId = Student::query()->whereKey($studentId)->where('id', '!=', $this->student->id)->value('id');
        $this->search = '';
    }

    public function link(): void
    {
        $this->authorizePermission('people.students.update');
        $this->resetErrorBag();

        if ($this->siblingId === null) {
            $this->addError('siblingId', __('Choose a learner.'));

            return;
        }

        try {
            app(LinkSiblingsAction::class)->execute(new LinkSiblingsData($this->student->id, $this->siblingId, $this->relationship, (int) auth()->id()));
        } catch (DomainException|InvalidArgumentException $exception) {
            $this->addError('siblingId', $exception->getMessage());

            return;
        }

        $this->reset('siblingId');
        $this->toast(__('Siblings linked.'));
    }

    public function unlink(int $siblingId): void
    {
        $this->authorizePermission('people.students.update');

        try {
            app(UnlinkSiblingsAction::class)->execute(new UnlinkSiblingsData($this->student->id, $siblingId));
        } catch (DomainException|InvalidArgumentException $exception) {
            $this->toast($exception->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Link removed.'));
    }

    public function render(): View
    {
        $term = trim($this->search);
        $like = '%'.addcslashes($term, '%_\\').'%';
        $links = StudentSibling::query()->where('student_id', $this->student->id)->get();

        return view('people::students.siblings', [
            'links' => $links,
            'siblings' => Student::query()->whereIn('id', $links->pluck('sibling_student_id'))->get()->keyBy('id'),
            'matches' => mb_strlen($term) < 2 ? collect() : Student::query()->where('id', '!=', $this->student->id)->where(fn ($q) => $q->where('admission_number', 'like', $like)->orWhere('first_name', 'like', $like)->orWhere('last_name', 'like', $like))->limit(8)->get(),
            'picked' => $this->siblingId === null ? null : Student::query()->find($this->siblingId),
        ]);
    }
}
