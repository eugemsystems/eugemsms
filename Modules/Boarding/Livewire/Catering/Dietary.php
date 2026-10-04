<?php

declare(strict_types=1);

namespace Modules\Boarding\Livewire\Catering;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Boarding\Domain\Actions\RecordDietaryRequirementAction;
use Modules\Boarding\Domain\Actions\VerifyDietaryRequirementAction;
use Modules\Boarding\Domain\DataObjects\RecordDietaryRequirementData;
use Modules\Boarding\Domain\DataObjects\VerifyDietaryRequirementData;
use Modules\Boarding\Models\DietaryRequirement;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Models\Student;

/**
 * `Catering\Dietary` (Book F BRD-04 §6, `catering.dietary.manage`).
 * By severity, with nurse-verification status. A requirement sourced
 * from a medical record requires nurse verification before it is
 * treated as clinical (BR-BRD-04-010) — this screen's "Verify" button
 * is that explicit step; recording one never marks it verified by
 * itself.
 */
#[Title('Dietary register')]
#[Layout('layouts.app')]
final class Dietary extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $studentId = null;

    public string $requirementType = 'allergy';

    public string $severity = 'moderate';

    public string $description = '';

    public bool $requiresEpipen = false;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('boarding.catering.dietary.view');
    }

    public function record(): void
    {
        $this->authorizePermission('boarding.catering.dietary.manage');

        if ($this->studentId === null || trim($this->description) === '') {
            $this->toast(__('A learner and description are required.'), 'danger');

            return;
        }

        app(RecordDietaryRequirementAction::class)->execute(new RecordDietaryRequirementData(
            schoolId: $this->school->id,
            studentId: $this->studentId,
            requirementType: $this->requirementType,
            severity: $this->severity,
            description: $this->description,
            effectiveFrom: Carbon::now(),
            requiresEpipen: $this->requiresEpipen,
        ));

        $this->reset(['studentId', 'description', 'requiresEpipen']);
        $this->toast(__('Dietary requirement recorded.'));
    }

    public function verify(int $requirementId): void
    {
        $this->authorizePermission('boarding.catering.dietary.manage');

        app(VerifyDietaryRequirementAction::class)->execute(new VerifyDietaryRequirementData(
            dietaryRequirementId: $requirementId,
            verifiedByNurseUserId: (int) Auth::id(),
        ));

        $this->toast(__('Verified.'));
    }

    public function render(): View
    {
        return view('boarding::catering.dietary', [
            'requirements' => DietaryRequirement::where('school_id', $this->school->id)->where('is_active', true)->with('student')->orderByDesc('severity')->get(),
            'students' => Student::where('school_id', $this->school->id)->orderBy('first_name')->limit(300)->get(),
        ]);
    }
}
