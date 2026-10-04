<?php

declare(strict_types=1);

namespace Modules\Welfare\Livewire\Health;

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
use Modules\Welfare\Domain\Actions\RecordHealthIncidentAction;
use Modules\Welfare\Domain\DataObjects\RecordHealthIncidentData;
use Modules\Welfare\Models\HealthIncident;

/**
 * `Health\Incidents` (Book G BRD-06 §5, `health.incident.report` to
 * report — Tier 2, `health.incident.review` to review — Tier 3).
 */
#[Title('Health incidents')]
#[Layout('layouts.app')]
final class Incidents extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $studentId = null;

    public string $incidentType = 'fall';

    public string $location = '';

    public string $description = '';

    public string $severity = 'minor';

    public ?string $activityAtTime = null;

    public ?string $firstAidGiven = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('health.incident.report');
    }

    public function report(): void
    {
        $this->validate([
            'studentId' => ['required', 'integer'],
            'incidentType' => ['required', 'string'],
            'location' => ['required', 'string'],
            'description' => ['required', 'string'],
            'severity' => ['required', 'string'],
        ]);

        $term = $this->school->currentAcademicYear()?->currentTerm();

        if ($term === null) {
            $this->toast(__('No current term is set.'), 'danger');

            return;
        }

        app(RecordHealthIncidentAction::class)->execute(new RecordHealthIncidentData(
            schoolId: $this->school->id,
            termId: $term->id,
            studentId: (int) $this->studentId,
            incidentType: $this->incidentType,
            occurredAt: Carbon::now(),
            location: $this->location,
            description: $this->description,
            severity: $this->severity,
            reportedByUserId: (int) Auth::id(),
            activityAtTime: $this->activityAtTime,
            firstAidGiven: $this->firstAidGiven,
        ));

        $this->reset(['location', 'description', 'activityAtTime', 'firstAidGiven']);
        $this->toast(__('Incident reported.'));
    }

    public function render(): View
    {
        return view('welfare::health.incidents', [
            'students' => Student::where('school_id', $this->school->id)->orderBy('first_name')->limit(300)->get(['id', 'first_name', 'last_name']),
            'incidents' => HealthIncident::where('school_id', $this->school->id)->with('student:id,first_name,last_name')->orderByDesc('occurred_at')->limit(100)->get(),
        ]);
    }
}
