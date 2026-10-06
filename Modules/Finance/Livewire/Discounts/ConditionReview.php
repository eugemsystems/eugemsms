<?php

declare(strict_types=1);

namespace Modules\Finance\Livewire\Discounts;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Finance\Domain\Actions\ReinstateAwardAction;
use Modules\Finance\Domain\Actions\ReviewAwardConditionAction;
use Modules\Finance\Models\DiscountAward;
use Modules\People\Models\Student;

/**
 * `Finance\Awards\ConditionReview` (Book K FIN-07 §5, `finance.award.review`).
 * Renewal-point checks on conditional awards against the term's recorded
 * results — never a self-reported figure (BR-FIN-07-007). A failed
 * condition suspends the award for a person to decide; it never revokes by
 * itself (BR-FIN-07-011). From here the reviewer either reinstates it, with
 * a reason, or revokes it from the Awards screen.
 */
#[Title('Condition review')]
#[Layout('layouts.app')]
final class ConditionReview extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $termId = null;

    public ?int $reinstatingId = null;

    public string $reason = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('finance.award.review');

        $this->termId = Term::query()->where('starts_on', '<=', now())->orderByDesc('starts_on')->value('id');
    }

    public function review(int $awardId): void
    {
        $this->authorizePermission('finance.award.review');

        $award = $this->conditionalAwards()->findOrFail($awardId);
        $term = Term::query()->where('academic_year_id', $award->academic_year_id)->find($this->termId);

        if ($term === null) {
            $this->toast(__('Choose a term in the award’s own academic year.'), 'danger');

            return;
        }

        try {
            $result = app(ReviewAwardConditionAction::class)->execute($award->id, $term->id);
        } catch (InvalidArgumentException $exception) {
            $this->toast($exception->getMessage(), 'danger');

            return;
        }

        $this->toast($result->condition_met ? __('Condition met.') : __('Condition not met — the award is suspended pending your decision.'), $result->condition_met ? 'success' : 'warning');
    }

    public function beginReinstate(int $awardId): void
    {
        $this->authorizePermission('finance.award.review');

        $this->reinstatingId = $this->conditionalAwards()->where('status', 'suspended')->findOrFail($awardId)->id;
        $this->reason = '';
        $this->resetErrorBag();
    }

    public function reinstate(): void
    {
        $this->authorizePermission('finance.award.review');
        $this->resetErrorBag();

        $award = $this->conditionalAwards()->findOrFail($this->reinstatingId);

        try {
            app(ReinstateAwardAction::class)->execute($award->id, $this->reason);
        } catch (InvalidArgumentException $exception) {
            $this->addError('reason', $exception->getMessage());

            return;
        }

        $this->reinstatingId = null;
        $this->toast(__('Award reinstated.'));
    }

    /**
     * @return Builder<DiscountAward>
     */
    private function conditionalAwards(): Builder
    {
        return DiscountAward::query()->whereIn('status', ['active', 'suspended'])
            ->whereHas('scheme', fn ($q) => $q->where('requires_academic_threshold', true));
    }

    public function render(): View
    {
        $awards = $this->conditionalAwards()->with('scheme')->orderByDesc('id')->limit(200)->get();

        return view('finance::discounts.condition-review', [
            'awards' => $awards,
            'students' => Student::query()->whereIn('id', $awards->pluck('student_id'))->get()->keyBy('id'),
            'terms' => Term::query()->orderByDesc('starts_on')->limit(12)->get(['id', 'name']),
        ]);
    }
}
