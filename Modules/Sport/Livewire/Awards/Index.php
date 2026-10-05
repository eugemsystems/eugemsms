<?php

declare(strict_types=1);

namespace Modules\Sport\Livewire\Awards;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\School;
use Modules\People\Models\Student;
use Modules\Sport\Domain\Actions\CreateAwardAction;
use Modules\Sport\Domain\DataObjects\CreateAwardData;
use Modules\Sport\Models\Activity;
use Modules\Sport\Models\Award;

/**
 * `Awards\Index` (Book H2 OPS-07 §4/BR-OPS-07-010, `activities.award.manage`).
 * "Carries into the alumni record on graduation" stays a documented
 * Book K deferral — `CreateAwardAction`'s own docblock.
 */
#[Title('Awards')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;
    use Toasts;

    public ?int $studentId = null;

    public string $awardType = 'half_colours';

    public string $title = '';

    public ?string $citation = null;

    public ?int $activityId = null;

    public bool $appearsOnReportCard = true;

    public bool $appearsOnTranscript = true;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
        $this->authorizePermission('activities.award.manage');
    }

    public function create(): void
    {
        $this->validate([
            'studentId' => ['required', 'integer'],
            'awardType' => ['required', 'string'],
            'title' => ['required', 'string'],
        ]);

        app(CreateAwardAction::class)->execute(new CreateAwardData(
            schoolId: $this->school->id,
            academicYearId: (int) SessionContext::yearId(),
            studentId: (int) $this->studentId,
            awardType: $this->awardType,
            title: $this->title,
            awardedOn: Carbon::now(),
            awardedByUserId: (int) auth()->id(),
            activityId: $this->activityId,
            citation: $this->citation,
            appearsOnReportCard: $this->appearsOnReportCard,
            appearsOnTranscript: $this->appearsOnTranscript,
        ));

        $this->reset(['title', 'citation', 'activityId']);
        $this->toast(__('Award recorded.'));
    }

    public function render(): View
    {
        return view('sport::awards.index', [
            'awards' => Award::with('student')->where('school_id', $this->school->id)->orderByDesc('awarded_on')->limit(50)->get(),
            'students' => Student::where('school_id', $this->school->id)->orderBy('last_name')->limit(300)->get(),
            'activities' => Activity::where('school_id', $this->school->id)->orderBy('name')->get(),
        ]);
    }
}
