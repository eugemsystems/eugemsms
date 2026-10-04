<?php

declare(strict_types=1);

namespace Modules\People\Livewire\Students;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Domain\Actions\DeactivateStudentGuardianAction;
use Modules\People\Domain\Actions\LinkGuardianToStudentAction;
use Modules\People\Domain\DataObjects\DeactivateStudentGuardianData;
use Modules\People\Domain\DataObjects\LinkGuardianToStudentData;
use Modules\People\Models\Guardian;
use Modules\People\Models\Student;
use Modules\People\Models\StudentGuardian;

/**
 * `People\Students\Guardians` (Book C PPL-03 §8 ⭐/BR-PPL-03-005,
 * `people.guardians.manage_relationships`) — the relationship editor
 * the spec names, scoped per learner the same way Finance's own
 * `Finance\Liabilities\Editor` is. Links an existing guardian
 * (creating a new one is its own screen, `People\Guardians\Create`,
 * reached via the link below) and sets every right explicitly, since
 * `LinkGuardianToStudentAction` never infers one from `relationship`.
 */
#[Title('Manage guardians')]
#[Layout('layouts.app')]
final class Guardians extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public Student $student;

    public ?int $guardianId = null;

    public string $relationship = 'mother';

    public bool $isPrimaryContact = false;

    public bool $isEmergencyContact = false;

    public bool $isFeeResponsible = false;

    public bool $mayCollectLearner = false;

    public bool $mayViewFullBalance = false;

    public bool $hasCourtRestriction = false;

    public string $effectiveFrom = '';

    public function mount(School $school, Student $student): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('people.guardians.manage_relationships');

        abort_unless($student->school_id === $school->id, 404);

        $this->student = $student;
        $this->effectiveFrom = now()->toDateString();
    }

    public function link(): void
    {
        $this->validate([
            'guardianId' => ['required', 'integer'],
            'relationship' => ['required', 'string', 'max:40'],
            'effectiveFrom' => ['required', 'date'],
        ]);

        app(LinkGuardianToStudentAction::class)->execute(new LinkGuardianToStudentData(
            studentId: $this->student->id,
            guardianId: (int) $this->guardianId,
            relationship: $this->relationship,
            createdByUserId: (int) Auth::id(),
            isPrimaryContact: $this->isPrimaryContact,
            isEmergencyContact: $this->isEmergencyContact,
            isFeeResponsible: $this->isFeeResponsible,
            mayCollectLearner: $this->mayCollectLearner,
            mayViewFullBalance: $this->mayViewFullBalance,
            hasCourtRestriction: $this->hasCourtRestriction,
            effectiveFrom: Carbon::parse($this->effectiveFrom),
        ));

        $this->reset([
            'guardianId', 'isPrimaryContact', 'isEmergencyContact', 'isFeeResponsible',
            'mayCollectLearner', 'mayViewFullBalance', 'hasCourtRestriction',
        ]);
        $this->relationship = 'mother';
        $this->toast(__('Guardian linked.'));
    }

    public function deactivate(int $studentGuardianId): void
    {
        try {
            app(DeactivateStudentGuardianAction::class)->execute(new DeactivateStudentGuardianData(
                studentGuardianId: $studentGuardianId,
                deactivatedByUserId: (int) Auth::id(),
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Guardian relationship deactivated.'));
    }

    public function render(): View
    {
        return view('people::students.guardians', [
            'links' => StudentGuardian::where('student_id', $this->student->id)->with('guardian')->orderByDesc('status')->get(),
            'guardians' => $this->availableGuardians(),
        ]);
    }

    /**
     * @return Collection<int, Guardian>
     */
    private function availableGuardians(): Collection
    {
        return Guardian::where('school_id', $this->school->id)->orderBy('first_name')->get();
    }
}
