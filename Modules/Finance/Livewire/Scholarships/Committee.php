<?php

declare(strict_types=1);

namespace Modules\Finance\Livewire\Scholarships;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Finance\Domain\Actions\DecideScholarshipApplicationAction;
use Modules\Finance\Domain\DataObjects\DecideScholarshipApplicationData;
use Modules\Finance\Models\DiscountScheme;
use Modules\Finance\Models\ScholarshipApplication;
use Modules\People\Models\Student;

/**
 * `Finance\Scholarships\Committee` (Book K FIN-07 §5,
 * `finance.scholarship.decide` ⚠). The panel's working list and decision
 * form. A decision is permanent and needs the committee's rationale
 * (BR-FIN-07-006); a rejection also needs its reason. Approving does not
 * grant an award — that is the separate, explicit Grant step.
 */
#[Title('Committee review')]
#[Layout('layouts.app')]
final class Committee extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $decidingId = null;

    public string $status = 'approved';

    public string $notes = '';

    public string $rejectionReason = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('finance.scholarship.decide');
    }

    public function begin(int $applicationId): void
    {
        $this->authorizePermission('finance.scholarship.decide');

        $this->decidingId = ScholarshipApplication::query()->whereNotIn('status', ['approved', 'rejected'])->findOrFail($applicationId)->id;
        $this->status = 'approved';
        $this->notes = '';
        $this->rejectionReason = '';
        $this->resetErrorBag();
    }

    public function decide(): void
    {
        $this->authorizePermission('finance.scholarship.decide');
        $this->resetErrorBag();

        $application = ScholarshipApplication::query()->findOrFail($this->decidingId);

        try {
            app(DecideScholarshipApplicationAction::class)->execute(new DecideScholarshipApplicationData(
                applicationId: $application->id, status: $this->status, decidedByUserId: (int) auth()->id(),
                committeeNotes: $this->notes === '' ? null : $this->notes,
                rejectionReason: $this->rejectionReason === '' ? null : $this->rejectionReason,
            ));
        } catch (InvalidArgumentException $exception) {
            $this->addError('notes', $exception->getMessage());

            return;
        }

        $this->decidingId = null;
        $this->toast(__('Decision recorded.'));
    }

    public function render(): View
    {
        $applications = ScholarshipApplication::query()->whereNotIn('status', ['approved', 'rejected', 'draft'])->orderBy('id')->limit(100)->get();

        return view('finance::scholarships.committee', [
            'applications' => $applications,
            'students' => Student::query()->whereIn('id', $applications->pluck('student_id'))->get()->keyBy('id'),
            'schemes' => DiscountScheme::query()->whereIn('id', $applications->pluck('scheme_id'))->get()->keyBy('id'),
        ]);
    }
}
