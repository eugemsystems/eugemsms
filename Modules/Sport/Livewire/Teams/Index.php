<?php

declare(strict_types=1);

namespace Modules\Sport\Livewire\Teams;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\School;
use Modules\Sport\Domain\Actions\CreateTeamAction;
use Modules\Sport\Domain\DataObjects\CreateTeamData;
use Modules\Sport\Models\Activity;
use Modules\Sport\Models\Team;

/**
 * `Teams\Index` (Book H2 OPS-07 §4, `activities.team.manage`).
 */
#[Title('Teams')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;
    use Toasts;

    public ?int $activityId = null;

    public string $name = '';

    public ?string $ageGroup = null;

    public ?string $level = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
        $this->authorizePermission('activities.team.manage');
    }

    public function create(): void
    {
        $this->validate([
            'activityId' => ['required', 'integer'],
            'name' => ['required', 'string'],
        ]);

        app(CreateTeamAction::class)->execute(new CreateTeamData(
            schoolId: $this->school->id,
            academicYearId: (int) SessionContext::yearId(),
            activityId: (int) $this->activityId,
            name: $this->name,
            ageGroup: $this->ageGroup,
            level: $this->level,
        ));

        $this->reset(['name', 'ageGroup', 'level']);
        $this->toast(__('Team created.'));
    }

    public function render(): View
    {
        return view('sport::teams.index', [
            'teams' => Team::with('activity')->where('school_id', $this->school->id)->orderBy('name')->get(),
            'activities' => Activity::where('school_id', $this->school->id)->where('is_active', true)->orderBy('name')->get(),
        ]);
    }
}
