<?php

declare(strict_types=1);

namespace Modules\Welfare\Livewire\Leadership;

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
use Modules\Welfare\Domain\Actions\AppointStudentLeadershipAction;
use Modules\Welfare\Domain\Actions\RevokeStudentLeadershipAction;
use Modules\Welfare\Domain\DataObjects\AppointStudentLeadershipData;
use Modules\Welfare\Models\StudentLeadership;

/**
 * `Leadership\Index` (Book G BRD-07 §5, `behaviour.leadership.manage`).
 * Appoint and revoke, explicit and limited (BR-BRD-07-020).
 */
#[Title('Student leadership')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $studentId = null;

    public string $roleTitle = '';

    public ?string $scopeType = null;

    public ?int $scopeId = null;

    public string $startsOn = '';

    public ?int $revokingLeadershipId = null;

    public string $revocationReason = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('behaviour.leadership.manage');

        $this->startsOn = now()->toDateString();
    }

    public function appoint(): void
    {
        $this->validate([
            'studentId' => ['required', 'integer'],
            'roleTitle' => ['required', 'string'],
            'startsOn' => ['required', 'date'],
        ]);

        $academicYear = $this->school->currentAcademicYear();

        if ($academicYear === null) {
            $this->toast(__('No current academic year is set.'), 'danger');

            return;
        }

        app(AppointStudentLeadershipAction::class)->execute(new AppointStudentLeadershipData(
            schoolId: $this->school->id,
            academicYearId: $academicYear->id,
            studentId: (int) $this->studentId,
            roleTitle: $this->roleTitle,
            startsOn: Carbon::parse($this->startsOn),
            appointedByUserId: (int) Auth::id(),
            scopeType: $this->scopeType,
            scopeId: $this->scopeId,
        ));

        $this->reset(['roleTitle', 'scopeType', 'scopeId']);
        $this->toast(__('Leadership role appointed.'));
    }

    public function revoke(int $leadershipId): void
    {
        if (trim($this->revocationReason) === '') {
            $this->toast(__('A revocation reason is required.'), 'danger');

            return;
        }

        app(RevokeStudentLeadershipAction::class)->execute($leadershipId, $this->revocationReason);

        $this->reset(['revokingLeadershipId', 'revocationReason']);
        $this->toast(__('Role revoked.'));
    }

    public function render(): View
    {
        return view('welfare::leadership.index', [
            'students' => Student::where('school_id', $this->school->id)->orderBy('first_name')->limit(300)->get(['id', 'first_name', 'last_name']),
            'leaderships' => StudentLeadership::where('school_id', $this->school->id)->with('student:id,first_name,last_name')->orderByDesc('id')->limit(100)->get(),
        ]);
    }
}
