<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\ReportCards;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\PublishReportCardsAction;
use Modules\Academic\Domain\DataObjects\PublishReportCardsData;
use Modules\Academic\Domain\DataObjects\PublishReportCardsResult;
use Modules\Academic\Models\TermResult;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\School;
use Modules\Core\Models\SchoolClass;

/**
 * `ReportCards\Publish` (Book D ACA-05 §6, `academic.report_card.publish`).
 * Publishing is deliberate, per class or level, after review (BR-ACA-05-017);
 * marks becoming available publishes nothing. Withheld learners stay withheld
 * until their balance clears or they are overridden.
 */
#[Title('Publish report cards')]
#[Layout('layouts.app')]
final class Publish extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;
    use Toasts;

    public ?int $classId = null;

    public ?int $gradeLevelId = null;

    /** @var array<string, int>|null */
    public ?array $lastResult = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
        $this->authorizePermission('academic.report_card.publish');
    }

    public function updatedClassId(): void
    {
        $this->gradeLevelId = null;
        $this->lastResult = null;
    }

    public function updatedGradeLevelId(): void
    {
        $this->classId = null;
        $this->lastResult = null;
    }

    public function publish(): void
    {
        $this->authorizePermission('academic.report_card.publish');
        $this->resetErrorBag();

        $termId = SessionContext::termId();

        if ($termId === null || ($this->classId === null && $this->gradeLevelId === null)) {
            $this->addError('classId', __('Choose a class or a grade level.'));

            return;
        }

        try {
            /** @var PublishReportCardsResult $result */
            $result = app(PublishReportCardsAction::class)->execute(new PublishReportCardsData($this->school->id, $termId, (int) auth()->id(), $this->classId, $this->gradeLevelId));
        } catch (DomainException|InvalidArgumentException $exception) {
            $this->addError('classId', $exception->getMessage());

            return;
        }

        $this->lastResult = ['published' => $result->published, 'released' => $result->releasedFromWithheld, 'withheld' => $result->stillWithheld, 'notGenerated' => $result->notGenerated];
        $this->toast(trans_choice(':count report card published.|:count report cards published.', $result->published, ['count' => $result->published]));
    }

    public function render(): View
    {
        $termId = SessionContext::termId();
        $classIds = $this->gradeLevelId === null ? null : SchoolClass::query()->where('grade_level_id', $this->gradeLevelId)->pluck('id');

        $counts = $termId === null || ($this->classId === null && $classIds === null) ? [] : TermResult::query()->where('term_id', $termId)
            ->when($this->classId !== null, fn ($q) => $q->where('class_id', $this->classId))
            ->when($classIds !== null, fn ($q) => $q->whereIn('class_id', $classIds))
            ->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status')->all();

        return view('academic::report-cards.publish', [
            'classes' => SchoolClass::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'gradeLevels' => GradeLevel::query()->orderBy('ordinal')->get(['id', 'name']),
            'counts' => $counts,
        ]);
    }
}
