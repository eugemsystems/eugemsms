<?php

declare(strict_types=1);

namespace Modules\Sport\Livewire\Activities;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Sport\Domain\Actions\CreateActivityAction;
use Modules\Sport\Domain\DataObjects\CreateActivityData;
use Modules\Sport\Models\Activity;

/**
 * `Activities\Index` (Book H2 OPS-07 §4, `activities.manage`). Sports,
 * clubs, societies register.
 */
#[Title('Activities')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $code = '';

    public string $name = '';

    public string $activityType = 'sport';

    public ?string $season = null;

    public string $genderScope = 'both';

    public bool $requiresMedicalClearance = false;

    public bool $requiresGuardianConsent = true;

    public ?int $maxParticipants = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('activities.manage');
    }

    public function create(): void
    {
        $this->validate([
            'code' => ['required', 'string'],
            'name' => ['required', 'string'],
            'activityType' => ['required', 'string'],
        ]);

        app(CreateActivityAction::class)->execute(new CreateActivityData(
            schoolId: $this->school->id,
            code: $this->code,
            name: $this->name,
            activityType: $this->activityType,
            season: $this->season !== '' ? $this->season : null,
            genderScope: $this->genderScope,
            requiresMedicalClearance: $this->requiresMedicalClearance,
            requiresGuardianConsent: $this->requiresGuardianConsent,
            maxParticipants: $this->maxParticipants,
        ));

        $this->reset(['code', 'name', 'season', 'requiresMedicalClearance', 'maxParticipants']);
        $this->requiresGuardianConsent = true;
        $this->toast(__('Activity created.'));
    }

    public function render(): View
    {
        return view('sport::activities.index', [
            'activities' => Activity::where('school_id', $this->school->id)->orderBy('name')->get(),
        ]);
    }
}
