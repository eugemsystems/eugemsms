<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Projects;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\ModerateProjectAction;
use Modules\Academic\Domain\DataObjects\ModerateProjectData;
use Modules\Academic\Models\LearnerProject;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Models\Staff;

/**
 * `Projects\Moderate` (Book E ACA-06 §5/§6/§7/BR-ACA-06-012,
 * `academic.projects.moderate`). The sample set (every `marked`
 * project), marker vs. moderator mark side by side. No distribution
 * chart — a purely presentational addition no Action backs; the
 * marker's and moderator's marks are both shown per the rule's own
 * wording, which is the substantive requirement.
 */
#[Title('Moderate projects')]
#[Layout('layouts.app')]
final class Moderate extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    /** @var array<int, string> */
    public array $moderatedMarks = [];

    /** @var array<int, string> */
    public array $notes = [];

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('academic.projects.moderate');
    }

    public function moderate(int $learnerProjectId): void
    {
        $mark = $this->moderatedMarks[$learnerProjectId] ?? '';
        $note = trim($this->notes[$learnerProjectId] ?? '');

        if ($mark === '' || $note === '') {
            $this->toast(__('A moderated mark and a note are both required.'), 'danger');

            return;
        }

        $staffId = Staff::where('school_id', $this->school->id)->where('user_id', Auth::id())->value('id');

        if ($staffId === null) {
            $this->toast(__('Your account has no staff record linked at this school.'), 'danger');

            return;
        }

        try {
            app(ModerateProjectAction::class)->execute(new ModerateProjectData(
                learnerProjectId: $learnerProjectId,
                moderatorStaffId: $staffId,
                moderatedMark: (float) $mark,
                moderationNote: $note,
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        unset($this->moderatedMarks[$learnerProjectId], $this->notes[$learnerProjectId]);
        $this->toast(__('Moderation recorded.'));
    }

    public function render(): View
    {
        return view('academic::projects.moderate', [
            'projects' => LearnerProject::where('school_id', $this->school->id)
                ->where('status', 'marked')
                ->with('student', 'brief')
                ->orderByDesc('marked_at')
                ->get(),
        ]);
    }
}
