<?php

declare(strict_types=1);

namespace Modules\People\Livewire\Admissions\Intakes;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\School;
use Modules\People\Domain\Actions\CreateIntakeAction;
use Modules\People\Domain\DataObjects\CreateIntakeData;
use Modules\People\Models\Intake;

/**
 * `Admissions\Intakes\Index` (Book C PPL-02 §5, `people.admissions.intake_manage`).
 * List + create — no `UpdateIntakeAction` exists in the domain layer,
 * matching the same create-only precedent as other screens this pass
 * (e.g. `People\Guardians\Create`).
 */
#[Title('Admissions intakes')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public int $academicYearId;

    public string $name = '';

    public ?int $gradeLevelId = null;

    public string $opensOn = '';

    public string $closesOn = '';

    public string $targetPlaces = '';

    public string $applicationFeeAmount = '';

    public string $applicationFeeCurrency = 'USD';

    public string $acceptanceDepositAmount = '';

    public string $acceptanceDepositCurrency = 'USD';

    public int $depositDeadlineDays = 14;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('people.admissions.intake_manage');

        $this->academicYearId = (int) (AcademicYear::where('school_id', $school->id)->where('is_current', true)->value('id')
            ?? AcademicYear::where('school_id', $school->id)->orderByDesc('starts_on')->value('id'));
        $this->opensOn = now()->toDateString();
        $this->closesOn = now()->addMonths(2)->toDateString();
    }

    public function create(): void
    {
        $this->validate([
            'academicYearId' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:150'],
            'gradeLevelId' => ['required', 'integer'],
            'opensOn' => ['required', 'date'],
            'closesOn' => ['required', 'date', 'after:opensOn'],
            'targetPlaces' => ['required', 'integer', 'min:1'],
            'applicationFeeAmount' => ['nullable', 'regex:/^\d+(\.\d{1,2})?$/'],
            'acceptanceDepositAmount' => ['nullable', 'regex:/^\d+(\.\d{1,2})?$/'],
            'depositDeadlineDays' => ['required', 'integer', 'min:1'],
        ]);

        app(CreateIntakeAction::class)->execute(new CreateIntakeData(
            schoolId: $this->school->id,
            academicYearId: $this->academicYearId,
            name: $this->name,
            gradeLevelId: (int) $this->gradeLevelId,
            opensOn: Carbon::parse($this->opensOn),
            closesOn: Carbon::parse($this->closesOn),
            targetPlaces: (int) $this->targetPlaces,
            createdByUserId: (int) Auth::id(),
            applicationFeeMinor: $this->applicationFeeAmount !== '' ? (int) round((float) $this->applicationFeeAmount * 100) : null,
            applicationFeeCurrency: $this->applicationFeeAmount !== '' ? $this->applicationFeeCurrency : null,
            acceptanceDepositMinor: $this->acceptanceDepositAmount !== '' ? (int) round((float) $this->acceptanceDepositAmount * 100) : null,
            acceptanceDepositCurrency: $this->acceptanceDepositAmount !== '' ? $this->acceptanceDepositCurrency : null,
            depositDeadlineDays: $this->depositDeadlineDays,
        ));

        $this->reset(['name', 'gradeLevelId', 'targetPlaces', 'applicationFeeAmount', 'acceptanceDepositAmount']);
        $this->toast(__('Intake created.'));
    }

    public function render(): View
    {
        return view('people::admissions.intakes.index', [
            'intakes' => Intake::where('school_id', $this->school->id)->with('gradeLevel', 'academicYear')->orderByDesc('opens_on')->get(),
            'academicYears' => AcademicYear::where('school_id', $this->school->id)->orderByDesc('starts_on')->get(),
            'gradeLevels' => GradeLevel::where('school_id', $this->school->id)->orderBy('ordinal')->get(),
        ]);
    }
}
