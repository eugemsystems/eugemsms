<?php

declare(strict_types=1);

namespace Modules\People\Livewire\Appraisal;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Domain\Actions\RecordAppraisalMeetingAction;
use Modules\People\Domain\Actions\SignOffAppraisalAction;
use Modules\People\Domain\Actions\SubmitAppraiserAssessmentAction;
use Modules\People\Domain\Actions\SubmitSelfAssessmentAction;
use Modules\People\Domain\DataObjects\SignOffAppraisalData;
use Modules\People\Domain\DataObjects\SubmitAppraiserAssessmentData;
use Modules\People\Domain\DataObjects\SubmitSelfAssessmentData;
use Modules\People\Models\StaffAppraisal;

/**
 * `People\Appraisal\Show` (Book C PPL-04 §5, `people.staff.appraisal_manage`).
 * One screen hosts the whole draft → self_assessment → appraiser_review
 * → meeting_held → signed_off lifecycle's action bar, same shape as
 * `Admissions\Applications\Show`. Assessment content is a single free
 * text note under a `notes` key rather than a structured rubric — the
 * spec's own JSON columns (`self_assessment`/`appraiser_assessment`/
 * `objectives`) accept arbitrary shape and no rubric/criteria list
 * exists anywhere in this backend to render a structured form against.
 */
#[Title('Appraisal')]
#[Layout('layouts.app')]
final class Show extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public StaffAppraisal $appraisal;

    public string $selfAssessmentNotes = '';

    public string $appraiserAssessmentNotes = '';

    public string $overallRating = '';

    public string $developmentPlan = '';

    public string $staffComments = '';

    public function mount(School $school, StaffAppraisal $appraisal): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('people.staff.appraisal_manage');

        abort_unless($appraisal->school_id === $school->id, 404);

        $this->appraisal = $appraisal;
    }

    public function submitSelfAssessment(): void
    {
        $this->validate(['selfAssessmentNotes' => ['required', 'string']]);

        try {
            $this->appraisal = app(SubmitSelfAssessmentAction::class)->execute(new SubmitSelfAssessmentData(
                appraisalId: $this->appraisal->id,
                selfAssessment: ['notes' => $this->selfAssessmentNotes],
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Self-assessment submitted.'));
    }

    public function submitAppraiserAssessment(): void
    {
        $this->validate(['appraiserAssessmentNotes' => ['required', 'string']]);

        try {
            $this->appraisal = app(SubmitAppraiserAssessmentAction::class)->execute(new SubmitAppraiserAssessmentData(
                appraisalId: $this->appraisal->id,
                appraiserAssessment: ['notes' => $this->appraiserAssessmentNotes],
                overallRating: $this->overallRating !== '' ? $this->overallRating : null,
                developmentPlan: $this->developmentPlan !== '' ? $this->developmentPlan : null,
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Appraiser assessment submitted.'));
    }

    public function recordMeeting(): void
    {
        try {
            $this->appraisal = app(RecordAppraisalMeetingAction::class)->execute($this->appraisal->id);
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Appraisal meeting recorded.'));
    }

    public function signOff(): void
    {
        try {
            $this->appraisal = app(SignOffAppraisalAction::class)->execute(new SignOffAppraisalData(
                appraisalId: $this->appraisal->id,
                staffComments: $this->staffComments !== '' ? $this->staffComments : null,
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Appraisal signed off.'));
    }

    public function render(): View
    {
        return view('people::appraisal.show');
    }
}
