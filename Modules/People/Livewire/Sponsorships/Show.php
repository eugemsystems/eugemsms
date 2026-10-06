<?php

declare(strict_types=1);

namespace Modules\People\Livewire\Sponsorships;

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
use Modules\People\Domain\Actions\AddSponsorshipBeneficiaryAction;
use Modules\People\Domain\Actions\ChangeSponsorshipStatusAction;
use Modules\People\Domain\Actions\EndSponsorshipBeneficiaryAction;
use Modules\People\Domain\Actions\RecordBeneficiaryConditionAction;
use Modules\People\Domain\DataObjects\AddSponsorshipBeneficiaryData;
use Modules\People\Domain\DataObjects\ChangeSponsorshipStatusData;
use Modules\People\Domain\DataObjects\EndSponsorshipBeneficiaryData;
use Modules\People\Domain\DataObjects\RecordBeneficiaryConditionData;
use Modules\People\Models\Guardian;
use Modules\People\Models\Sponsorship;
use Modules\People\Models\SponsorshipBeneficiary;
use Modules\People\Models\Student;

/**
 * `People\Sponsorships\Show` (Book C PPL-03 §7, `people.sponsorships.manage`).
 * Beneficiaries, budget use and performance conditions. The budget envelope
 * refuses a commitment past its cap; a failed condition only flags a learner
 * for review — ending support is a separate, human step (BR-PPL-03-019/020).
 */
#[Title('Sponsorship')]
#[Layout('layouts.app')]
final class Show extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public Sponsorship $sponsorship;

    public string $search = '';

    public ?int $studentId = null;

    public string $commitment = '';

    public string $condition = '';

    public function mount(School $school, Sponsorship $sponsorship): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('people.sponsorships.manage');

        abort_unless($sponsorship->school_id === $school->id, 404);

        $this->sponsorship = $sponsorship;
    }

    public function select(int $id): void
    {
        $this->studentId = Student::query()->whereKey($id)->value('id');
        $this->search = '';
    }

    public function addBeneficiary(): void
    {
        $this->authorizePermission('people.sponsorships.manage');
        $this->resetErrorBag();
        $this->validate(['commitment' => ['nullable', 'numeric', 'min:0']]);

        if ($this->studentId === null) {
            $this->addError('studentId', __('Choose a learner.'));

            return;
        }

        try {
            app(AddSponsorshipBeneficiaryAction::class)->execute(new AddSponsorshipBeneficiaryData(
                $this->sponsorship->id, $this->studentId, (int) auth()->id(), $this->commitment === '' ? 0 : (int) round((float) $this->commitment * 100),
                performanceCondition: $this->condition === '' ? null : $this->condition,
            ));
        } catch (DomainException|InvalidArgumentException $exception) {
            $this->addError('studentId', $exception->getMessage());

            return;
        }

        $this->reset('studentId', 'commitment', 'condition');
        $this->toast(__('Beneficiary added; the sponsor is now billed for them.'));
    }

    public function setCondition(int $beneficiaryId, bool $met): void
    {
        $this->authorizePermission('people.sponsorships.manage');
        $this->guard(fn () => app(RecordBeneficiaryConditionAction::class)->execute(new RecordBeneficiaryConditionData($this->owned($beneficiaryId), $met)), __('Recorded.'));
    }

    public function end(int $beneficiaryId): void
    {
        $this->authorizePermission('people.sponsorships.manage');
        $this->guard(fn () => app(EndSponsorshipBeneficiaryAction::class)->execute(new EndSponsorshipBeneficiaryData($this->owned($beneficiaryId))), __('Support ended.'));
    }

    public function changeStatus(string $status): void
    {
        $this->authorizePermission('people.sponsorships.manage');
        $this->guard(fn () => app(ChangeSponsorshipStatusAction::class)->execute(new ChangeSponsorshipStatusData($this->sponsorship->id, $status)), __('Status updated.'));
        $this->sponsorship = $this->sponsorship->fresh();
    }

    private function owned(int $beneficiaryId): int
    {
        return (int) SponsorshipBeneficiary::query()->where('sponsorship_id', $this->sponsorship->id)->findOrFail($beneficiaryId)->id;
    }

    private function guard(callable $callback, string $success): void
    {
        try {
            $callback();
        } catch (DomainException|InvalidArgumentException $exception) {
            $this->toast($exception->getMessage(), 'danger');

            return;
        }

        $this->toast($success);
    }

    public function render(): View
    {
        $term = trim($this->search);
        $like = '%'.addcslashes($term, '%_\\').'%';
        $beneficiaries = SponsorshipBeneficiary::query()->where('sponsorship_id', $this->sponsorship->id)->orderBy('status')->get();

        return view('people::sponsorships.show', [
            'beneficiaries' => $beneficiaries,
            'students' => Student::query()->whereIn('id', $beneficiaries->pluck('student_id'))->get()->keyBy('id'),
            'sponsor' => Guardian::query()->find($this->sponsorship->guardian_id),
            'matches' => mb_strlen($term) < 2 ? collect() : Student::query()->where(fn ($q) => $q->where('admission_number', 'like', $like)->orWhere('first_name', 'like', $like)->orWhere('last_name', 'like', $like))->limit(8)->get(),
            'picked' => $this->studentId === null ? null : Student::query()->find($this->studentId),
        ]);
    }
}
