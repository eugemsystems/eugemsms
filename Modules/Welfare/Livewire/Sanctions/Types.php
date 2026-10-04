<?php

declare(strict_types=1);

namespace Modules\Welfare\Livewire\Sanctions;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Welfare\Domain\Actions\CreateSanctionTypeAction;
use Modules\Welfare\Domain\DataObjects\CreateSanctionTypeData;
use Modules\Welfare\Models\SanctionType;

/**
 * `Sanctions\Types` (Book G BRD-07 §2, `behaviour.manage` ⚠). Create-
 * only catalogue, mirroring `Curriculum\Subjects`'s own precedent.
 */
#[Title('Sanction types')]
#[Layout('layouts.app')]
final class Types extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $code = '';

    public string $name = '';

    public int $severityLevel = 1;

    public bool $requiresGuardianMeeting = false;

    public bool $requiresCommittee = false;

    public bool $removesFromLessons = false;

    public bool $removesFromCampus = false;

    public ?int $maxDurationDays = null;

    public bool $appealable = true;

    public int $appealWindowDays = 5;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('behaviour.manage');
    }

    public function create(): void
    {
        $this->validate([
            'code' => ['required', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:120'],
            'severityLevel' => ['required', 'integer', 'min:1', 'max:6'],
        ]);

        app(CreateSanctionTypeAction::class)->execute(new CreateSanctionTypeData(
            schoolId: $this->school->id,
            code: $this->code,
            name: $this->name,
            severityLevel: $this->severityLevel,
            requiresGuardianMeeting: $this->requiresGuardianMeeting,
            requiresCommittee: $this->requiresCommittee,
            removesFromLessons: $this->removesFromLessons,
            removesFromCampus: $this->removesFromCampus,
            maxDurationDays: $this->maxDurationDays,
            appealable: $this->appealable,
            appealWindowDays: $this->appealWindowDays,
        ));

        $this->reset(['code', 'name', 'maxDurationDays', 'requiresGuardianMeeting', 'requiresCommittee', 'removesFromLessons', 'removesFromCampus']);
        $this->toast(__('Sanction type created.'));
    }

    public function render(): View
    {
        return view('welfare::sanctions.types', [
            'types' => SanctionType::where('school_id', $this->school->id)->orderBy('severity_level')->get(),
        ]);
    }
}
