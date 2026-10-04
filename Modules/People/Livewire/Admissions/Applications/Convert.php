<?php

declare(strict_types=1);

namespace Modules\People\Livewire\Admissions\Applications;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\School;
use Modules\Finance\Livewire\Concerns\ResolvesSystemAccounts;
use Modules\People\Domain\Actions\ConvertApplicationToStudentAction;
use Modules\People\Domain\DataObjects\ConvertApplicationToStudentData;
use Modules\People\Models\Application;
use Modules\People\Models\Guardian;

/**
 * `Admissions\Applications\Convert` (Book C PPL-02 §5 ⭐, `people.admissions.convert`)
 * — preflight checks, a guardian-match preview, and a deposit-credit
 * preview before the one real transaction
 * (`ConvertApplicationToStudentAction`). The guardian-match preview
 * re-runs that action's own phone-match query read-only, rather than
 * adding a second Action — it's a single indexed lookup, not
 * pricing-engine logic worth sharing a class for.
 */
#[Title('Convert application')]
#[Layout('layouts.app')]
final class Convert extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;
    use ResolvesSystemAccounts;

    public Application $application;

    public bool $overrideCapacity = false;

    public string $overrideReason = '';

    public function mount(School $school, Application $application): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
        $this->authorizePermission('people.admissions.convert');

        abort_unless($application->school_id === $school->id, 404);

        $this->application = $application->load('guardians', 'intake');
    }

    public function convert(): void
    {
        if ($this->overrideCapacity) {
            $this->validate(['overrideReason' => ['required', 'string', 'max:255']]);
        }

        $termId = SessionContext::termId();

        if ($termId === null) {
            $this->addError('overrideReason', __('No active term is set for this school.'));

            return;
        }

        try {
            $student = app(ConvertApplicationToStudentAction::class)->execute(new ConvertApplicationToStudentData(
                applicationId: $this->application->id,
                termId: $termId,
                convertedByUserId: (int) Auth::id(),
                creditBalanceAccountId: $this->requireSystemAccount('credit_balance', __('Learner credit balances')),
                overrideCapacity: $this->overrideCapacity,
                overrideReason: $this->overrideCapacity ? $this->overrideReason : null,
            ));
        } catch (DomainException $e) {
            $this->addError('overrideReason', $e->getMessage());

            return;
        }

        $this->redirectRoute('people.students.show', ['school' => $this->school, 'student' => $student], navigate: true);
    }

    public function render(): View
    {
        return view('people::admissions.applications.convert', [
            'guardianMatches' => $this->guardianMatchPreview(),
            'hasCapacity' => $this->application->intake->places_accepted <= $this->application->intake->target_places,
        ]);
    }

    /**
     * @return array<int, array{applicationGuardianId: int, name: string, matchedGuardianId: int|null}>
     */
    private function guardianMatchPreview(): array
    {
        return $this->application->guardians->map(function ($applicationGuardian): array {
            $matched = $applicationGuardian->existing_guardian_id;

            if ($matched === null && $applicationGuardian->primary_phone !== null) {
                $matched = Guardian::where('school_id', $this->school->id)
                    ->where('primary_phone', $applicationGuardian->primary_phone)
                    ->value('id');
            }

            return [
                'applicationGuardianId' => $applicationGuardian->id,
                'name' => $applicationGuardian->organisation_name ?? trim("{$applicationGuardian->first_name} {$applicationGuardian->last_name}"),
                'matchedGuardianId' => $matched,
            ];
        })->all();
    }
}
