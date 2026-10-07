<?php

declare(strict_types=1);

namespace Modules\People\Livewire\Appraisal;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
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
 * `Admissions\Applications\Show`. When the appraisal was created against a
 * `StaffAppraisalRubric` (`People\Appraisal\Index`'s own rubric picker), both
 * assessments score every one of the rubric's criteria against its own named
 * levels, validated by `AppraisalRubricScorer` — mirroring
 * `Academic\Supervision\Observe`'s own criterion-by-criterion form. An
 * appraisal with no rubric keeps the original single free-text note under a
 * `notes` key, for backward compatibility with a school that hasn't
 * configured one.
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

    /** @var array<int, string> criterion position => chosen level */
    public array $selfScores = [];

    /** @var array<int, string> criterion position => chosen level */
    public array $appraiserScores = [];

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
        $assessment = $this->buildAssessment($this->selfScores, $this->selfAssessmentNotes);

        if ($assessment === null) {
            $this->addError('selfAssessmentNotes', __('Enter a self-assessment.'));

            return;
        }

        try {
            $this->appraisal = app(SubmitSelfAssessmentAction::class)->execute(new SubmitSelfAssessmentData(
                appraisalId: $this->appraisal->id,
                selfAssessment: $assessment,
            ));
        } catch (DomainException|InvalidArgumentException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Self-assessment submitted.'));
    }

    public function submitAppraiserAssessment(): void
    {
        $assessment = $this->buildAssessment($this->appraiserScores, $this->appraiserAssessmentNotes);

        if ($assessment === null) {
            $this->addError('appraiserAssessmentNotes', __('Enter an appraiser assessment.'));

            return;
        }

        try {
            $this->appraisal = app(SubmitAppraiserAssessmentAction::class)->execute(new SubmitAppraiserAssessmentData(
                appraisalId: $this->appraisal->id,
                appraiserAssessment: $assessment,
                overallRating: $this->overallRating !== '' ? $this->overallRating : null,
                developmentPlan: $this->developmentPlan !== '' ? $this->developmentPlan : null,
            ));
        } catch (DomainException|InvalidArgumentException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Appraiser assessment submitted.'));
    }

    /**
     * @param  array<int, string>  $scores  criterion position => chosen level
     * @return array<string, mixed>|null
     */
    private function buildAssessment(array $scores, string $notes): ?array
    {
        $rubric = $this->appraisal->rubric;

        if ($rubric === null) {
            return trim($notes) === '' ? null : ['notes' => $notes];
        }

        $byCriterion = [];

        foreach (array_values($rubric->criteria) as $position => $criterion) {
            $byCriterion[(string) $criterion['criterion']] = (string) ($scores[$position] ?? '');
        }

        if (in_array('', $byCriterion, true)) {
            return null;
        }

        return ['scores' => $byCriterion, 'comments' => trim($notes) === '' ? null : $notes];
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
