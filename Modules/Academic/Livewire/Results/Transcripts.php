<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Results;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\GenerateTranscriptAction;
use Modules\Academic\Domain\DataObjects\GenerateTranscriptData;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\Document;
use Modules\Core\Models\School;
use Modules\People\Models\Student;

/**
 * `Results\Transcripts` (Book D ACA-05 §6, `academic.transcript.generate`).
 * A transcript is built from a learner's published results across every year
 * and carries a verification code. The learner is picked by search and
 * re-resolved server-side through the school-scoped model.
 */
#[Title('Transcripts')]
#[Layout('layouts.app')]
final class Transcripts extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $search = '';

    public ?int $studentId = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('academic.transcript.generate');
    }

    public function select(int $studentId): void
    {
        $this->authorizePermission('academic.transcript.generate');
        $this->studentId = Student::query()->whereKey($studentId)->value('id');
        $this->search = '';
    }

    public function generate(): void
    {
        $this->authorizePermission('academic.transcript.generate');
        $this->resetErrorBag();

        $student = $this->studentId === null ? null : Student::query()->find($this->studentId);

        if ($student === null) {
            $this->addError('studentId', __('Choose a learner.'));

            return;
        }

        try {
            app(GenerateTranscriptAction::class)->execute(new GenerateTranscriptData($student->id, (int) auth()->id()));
        } catch (DomainException|InvalidArgumentException $exception) {
            $this->addError('studentId', $exception->getMessage());

            return;
        }

        $this->toast(__('Transcript generated.'));
    }

    public function render(): View
    {
        $term = trim($this->search);
        $like = '%'.addcslashes($term, '%_\\').'%';

        $student = $this->studentId === null ? null : Student::query()->find($this->studentId);

        return view('academic::report-cards.transcripts', [
            'matches' => mb_strlen($term) < 2 ? collect() : Student::query()->where(fn ($q) => $q->where('admission_number', 'like', $like)->orWhere('first_name', 'like', $like)->orWhere('last_name', 'like', $like))->orderBy('last_name')->limit(8)->get(),
            'student' => $student,
            'documents' => $student === null ? collect() : Document::query()->where('document_type', 'transcript')->where('documentable_type', $student->getMorphClass())->where('documentable_id', $student->id)->orderByDesc('id')->get(),
        ]);
    }
}
