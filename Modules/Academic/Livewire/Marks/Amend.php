<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Marks;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\AmendMarkAction;
use Modules\Academic\Domain\DataObjects\AmendMarkData;
use Modules\Academic\Models\Assessment;
use Modules\Academic\Models\AssessmentMark;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Marks\Amend` (Book D ACA-05 §4 ⭐/BR-ACA-05-009/010/011,
 * `academic.result.amend` ⚠; amending an already-`published` mark
 * additionally requires `academic.result.amend_published` ⚠⚠, the
 * caller's proof CORE-07 approval has run per `AmendMarkAction`'s own
 * docblock — this screen has no approval workflow wired up, so an
 * unpublished amendment proceeds directly and a published one is
 * refused outright rather than faked through. No "whose positions
 * change" diff preview — no Action computes one; the cascade itself
 * (whole class and level recompute) is real, just not previewed
 * before running.
 */
#[Title('Amend mark')]
#[Layout('layouts.app')]
final class Amend extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public Assessment $assessment;

    public ?int $studentId = null;

    public string $rawMark = '';

    public bool $isAbsent = false;

    public string $changeReason = '';

    public function mount(School $school, Assessment $assessment): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('academic.result.amend');

        abort_unless($assessment->school_id === $school->id, 404);

        $this->assessment = $assessment;
    }

    public function amend(): void
    {
        $this->validate([
            'studentId' => ['required', 'integer'],
            'changeReason' => ['required', 'string', 'min:15', 'max:255'],
        ]);

        $approved = true;

        if ($this->assessment->status === 'published') {
            $this->authorizePermission('academic.result.amend_published');
        }

        try {
            app(AmendMarkAction::class)->execute(new AmendMarkData(
                assessmentId: $this->assessment->id,
                studentId: (int) $this->studentId,
                changedByUserId: (int) Auth::id(),
                changeReason: $this->changeReason,
                rawMark: ! $this->isAbsent && $this->rawMark !== '' ? (float) $this->rawMark : null,
                isAbsent: $this->isAbsent,
                approved: $approved,
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->reset(['studentId', 'rawMark', 'isAbsent', 'changeReason']);
        $this->toast(__('Mark amended — class and level positions recomputed.'));
    }

    public function render(): View
    {
        return view('academic::marks.amend', [
            'marks' => AssessmentMark::where('assessment_id', $this->assessment->id)->with('student')->get(),
        ]);
    }
}
