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
use Modules\Core\Models\Document;
use Modules\Core\Models\School;
use Modules\People\Domain\Actions\GenerateStudentIdCardAction;
use Modules\People\Domain\DataObjects\GenerateStudentIdCardData;
use Modules\People\Models\Student;

/**
 * `People\Students\IdCards` (Book C PPL-01 §8, `people.students.id_card_issue`). Produces an ID card for a learner on roll from the school's template, and lists the cards already issued.
 */
#[Title('ID card')]
#[Layout('layouts.app')]
final class IdCards extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public Student $student;

    public function mount(School $school, Student $student): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('people.students.id_card_issue');

        abort_unless($student->school_id === $school->id, 404);

        $this->student = $student;
    }

    public function generate(): void
    {
        $this->authorizePermission('people.students.id_card_issue');

        try {
            app(GenerateStudentIdCardAction::class)->execute(new GenerateStudentIdCardData($this->student->id, (int) auth()->id()));
        } catch (DomainException|InvalidArgumentException $exception) {
            $this->toast($exception->getMessage(), 'danger');

            return;
        }

        $this->toast(__('ID card generated.'));
    }

    public function render(): View
    {
        return view('people::students.id-cards', [
            'cards' => Document::query()->where('document_type', GenerateStudentIdCardAction::TYPE)->where('documentable_type', $this->student->getMorphClass())->where('documentable_id', $this->student->id)->orderByDesc('id')->get(),
        ]);
    }
}
