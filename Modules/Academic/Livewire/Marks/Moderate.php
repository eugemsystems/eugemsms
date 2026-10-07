<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Marks;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\ModerateAssessmentAction;
use Modules\Academic\Domain\DataObjects\ModerateAssessmentData;
use Modules\Academic\Models\Assessment;
use Modules\Academic\Models\AssessmentMark;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Marks\Moderate` (Book D ACA-05 §6, `academic.result.moderate`). The
 * spec calls for "distribution chart, outliers, HOD sign-off" — the
 * distribution/outlier stats are computed inline here from
 * `AssessmentMark.percent`, the same "the screen computes its own
 * summary rather than a dedicated read Action" precedent
 * `Results\Compute`'s exception report already set for this module.
 * Sign-off is optional, not a forced gate: `ModerateAssessmentAction`
 * only sets `status = moderated`; `PublishAssessmentAction` still
 * accepts a straight `submitted -> published` path either way.
 */
#[Title('Moderate assessment')]
#[Layout('layouts.app')]
final class Moderate extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public Assessment $assessment;

    public string $moderationNote = '';

    public function mount(School $school, Assessment $assessment): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('academic.result.moderate');

        abort_unless($assessment->school_id === $school->id, 404);

        $this->assessment = $assessment;
        $this->moderationNote = (string) $assessment->moderation_note;
    }

    public function moderate(): void
    {
        try {
            app(ModerateAssessmentAction::class)->execute(new ModerateAssessmentData(
                assessmentId: $this->assessment->id,
                moderatedByUserId: (int) Auth::id(),
                moderationNote: $this->moderationNote !== '' ? $this->moderationNote : null,
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->assessment = $this->assessment->fresh();
        $this->toast(__('Assessment moderated.'));
    }

    /**
     * @return array{count: int, average: float|null, min: float|null, max: float|null, std_dev: float|null, outliers: array<int, array{student: string, percent: float}>}
     */
    private function distribution(): array
    {
        $marks = AssessmentMark::where('assessment_id', $this->assessment->id)
            ->where('is_absent', false)
            ->whereNotNull('percent')
            ->with('student')
            ->get();

        $percentages = $marks->pluck('percent')->map(fn (mixed $p): float => (float) $p);

        if ($percentages->isEmpty()) {
            return ['count' => 0, 'average' => null, 'min' => null, 'max' => null, 'std_dev' => null, 'outliers' => []];
        }

        $average = $percentages->avg();
        $variance = $percentages->map(fn (float $p): float => ($p - $average) ** 2)->avg();
        $stdDev = sqrt($variance);

        $outliers = $marks
            ->filter(fn (AssessmentMark $mark): bool => abs((float) $mark->percent - $average) > (2 * $stdDev) && $stdDev > 0)
            ->map(fn (AssessmentMark $mark): array => [
                'student' => $mark->student !== null ? "{$mark->student->first_name} {$mark->student->last_name}" : (string) $mark->student_id,
                'percent' => (float) $mark->percent,
            ])
            ->values()
            ->all();

        return [
            'count' => $percentages->count(),
            'average' => $average,
            'min' => $percentages->min(),
            'max' => $percentages->max(),
            'std_dev' => $stdDev,
            'outliers' => $outliers,
        ];
    }

    public function render(): View
    {
        return view('academic::marks.moderate', [
            'distribution' => $this->distribution(),
        ]);
    }
}
