<?php

declare(strict_types=1);

namespace Modules\Intelligence\Livewire\EarlyWarning;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Intelligence\Domain\Actions\ComputeLearnerRiskScoreAction;
use Modules\Intelligence\Domain\Actions\ReviewWithdrawalRiskFlagAction;
use Modules\Intelligence\Livewire\Concerns\ResolvesEarlyWarningTerm;
use Modules\Intelligence\Models\LearnerRiskScore;
use Modules\Intelligence\Models\WithdrawalRiskFlag;
use Modules\People\Models\Student;
use Modules\People\Models\StudentEnrolment;

/**
 * `Intelligence\EarlyWarning\Queue` (Book J INT-03 §5, `risk.review`).
 * The banded at-risk queue plus the open withdrawal risk flags. Scores
 * are advisory (BR-INT-03-004): nothing here contacts a guardian, and
 * a flag can only be closed with an intervention note
 * (BR-INT-03-008) — the Action refuses otherwise, this screen just
 * shows that refusal.
 */
#[Title('At-risk review queue')]
#[Layout('layouts.app')]
final class Queue extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use ResolvesEarlyWarningTerm;
    use Toasts;

    /** @var array<int, string> */
    public const array BANDS = ['critical', 'high', 'medium', 'low'];

    /** @var array<int, string> */
    public array $bands = ['critical', 'high'];

    public string $sort = 'score';

    public ?int $reviewingFlagId = null;

    public string $reviewStatus = 'intervention_logged';

    public string $interventionNote = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('risk.review');
        $this->termId = $this->defaultTermId();
    }

    public function recomputeTerm(): void
    {
        $this->authorizePermission('risk.review');

        $term = $this->selectedTerm();

        if ($term === null) {
            $this->toast(__('Choose a term first.'), 'danger');

            return;
        }

        $studentIds = StudentEnrolment::where('school_id', $this->school->id)
            ->where('term_id', $term->id)->where('status', 'active')->pluck('student_id')->unique();

        foreach ($studentIds as $studentId) {
            app(ComputeLearnerRiskScoreAction::class)->execute($this->school->id, (int) $studentId, $term->id);
        }

        $this->toast(__(':count learner(s) recomputed from source data.', ['count' => $studentIds->count()]));
    }

    public function startReview(int $flagId): void
    {
        $this->authorizePermission('risk.review');
        $this->resetErrorBag();

        $flag = WithdrawalRiskFlag::where('school_id', $this->school->id)->where('status', 'open')->findOrFail($flagId);

        $this->reviewingFlagId = $flag->id;
        $this->reviewStatus = 'intervention_logged';
        $this->interventionNote = '';
    }

    public function cancelReview(): void
    {
        $this->reviewingFlagId = null;
        $this->resetErrorBag();
    }

    public function closeFlag(): void
    {
        $this->authorizePermission('risk.review');
        $this->resetErrorBag();

        abort_unless($this->reviewingFlagId !== null, 422);

        $flag = WithdrawalRiskFlag::where('school_id', $this->school->id)->findOrFail($this->reviewingFlagId);

        try {
            app(ReviewWithdrawalRiskFlagAction::class)->execute($flag->id, $this->reviewStatus, (int) auth()->id(), $this->interventionNote);
        } catch (InvalidArgumentException $exception) {
            $this->addError('interventionNote', $exception->getMessage());

            return;
        }

        $this->reviewingFlagId = null;
        $this->toast(__('Flag updated.'));
    }

    public function render(): View
    {
        $term = $this->selectedTerm();
        $bands = array_values(array_intersect($this->bands, self::BANDS));

        $scores = $term === null ? collect() : LearnerRiskScore::where('school_id', $this->school->id)
            ->where('term_id', $term->id)->whereIn('risk_band', $bands)
            ->when($this->sort === 'name', fn ($q) => $q->orderBy('student_id'), fn ($q) => $q->orderByDesc('composite_score'))
            ->limit(200)->get();

        $flags = WithdrawalRiskFlag::where('school_id', $this->school->id)->where('status', 'open')->orderBy('flagged_at')->limit(100)->get();

        return view('intelligence::early-warning.queue', [
            'terms' => $this->termChoices(),
            'scores' => $scores,
            'flags' => $flags,
            'students' => Student::where('school_id', $this->school->id)->whereIn('id', $scores->pluck('student_id')->merge($flags->pluck('student_id')))->get()->keyBy('id'),
            'allBands' => self::BANDS,
        ]);
    }
}
