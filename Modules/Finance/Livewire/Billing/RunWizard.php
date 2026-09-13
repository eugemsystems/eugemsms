<?php

declare(strict_types=1);

namespace Modules\Finance\Livewire\Billing;

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
use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\School;
use Modules\Core\Models\SchoolClass;
use Modules\Core\Models\SchoolSection;
use Modules\Finance\Domain\Actions\ComputeBillingRunAction;
use Modules\Finance\Domain\DataObjects\ComputeBillingRunData;

/**
 * `Finance\Billing\RunWizard` (Book B FIN-02 §7, `finance.billing.run`)
 * — scope → compute. The rest of the pipeline (preview → approve →
 * commit, BR-FIN-02-013 ⭐) continues on `Billing\Preview` for the run
 * this creates, once it exists — approve/commit each need their own,
 * narrower permission (`finance.billing.approve`/`.commit`), which this
 * screen never checks.
 */
#[Title('New billing run')]
#[Layout('layouts.app')]
final class RunWizard extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;

    public ?int $sectionId = null;

    public ?int $gradeLevelId = null;

    public ?int $classId = null;

    public string $enrolmentType = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
        $this->authorizePermission('finance.billing.run');
    }

    public function compute(): void
    {
        $yearId = SessionContext::yearId();
        $termId = SessionContext::termId();

        if ($yearId === null || $termId === null) {
            $this->addError('sectionId', __('No active academic year/term is set for this school.'));

            return;
        }

        $scopeFilter = array_filter([
            'section_id' => $this->sectionId,
            'grade_level_id' => $this->gradeLevelId,
            'class_id' => $this->classId,
            'enrolment_type' => $this->enrolmentType !== '' ? $this->enrolmentType : null,
        ], fn ($value): bool => $value !== null);

        try {
            $run = app(ComputeBillingRunAction::class)->execute(new ComputeBillingRunData(
                schoolId: $this->school->id,
                academicYearId: $yearId,
                termId: $termId,
                computedByUserId: (int) Auth::id(),
                scopeFilter: $scopeFilter !== [] ? $scopeFilter : null,
            ));
        } catch (DomainException $e) {
            $this->addError('sectionId', $e->getMessage());

            return;
        }

        $this->redirectRoute('finance.billing.preview', ['school' => $this->school, 'billingRun' => $run], navigate: true);
    }

    public function render(): View
    {
        return view('finance::billing.run-wizard', [
            'sections' => SchoolSection::orderBy('name')->get(),
            'gradeLevels' => GradeLevel::orderBy('ordinal')->get(),
            'classes' => SchoolClass::orderBy('name')->get(),
        ]);
    }
}
