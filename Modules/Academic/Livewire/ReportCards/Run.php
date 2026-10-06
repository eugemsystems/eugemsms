<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\ReportCards;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\GenerateReportCardsAction;
use Modules\Academic\Domain\DataObjects\GenerateReportCardsData;
use Modules\Academic\Models\ReportCardRun;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\DocumentTemplate;
use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\School;
use Modules\Core\Models\SchoolClass;

/**
 * `ReportCards\Run` (Book D ACA-05 §6, `academic.report_card.generate`).
 * Generates a stored report card for every approved result in a class or
 * level. Where the fee gate applies, a card is still generated and stored but
 * flagged withheld (BR-ACA-05-014/015); the run's counts say how many.
 */
#[Title('Generate report cards')]
#[Layout('layouts.app')]
final class Run extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;
    use Toasts;

    public ?int $classId = null;

    public ?int $gradeLevelId = null;

    public ?int $templateId = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
        $this->authorizePermission('academic.report_card.generate');
    }

    public function updatedClassId(): void
    {
        $this->gradeLevelId = null;
    }

    public function updatedGradeLevelId(): void
    {
        $this->classId = null;
    }

    public function generate(): void
    {
        $this->authorizePermission('academic.report_card.generate');
        $this->resetErrorBag();

        $termId = SessionContext::termId();

        if ($termId === null || ($this->classId === null && $this->gradeLevelId === null)) {
            $this->addError('classId', __('Choose a class or a grade level.'));

            return;
        }

        try {
            $run = app(GenerateReportCardsAction::class)->execute(new GenerateReportCardsData(
                schoolId: $this->school->id, termId: $termId, requestedByUserId: (int) auth()->id(),
                classId: $this->classId, gradeLevelId: $this->gradeLevelId, templateId: $this->templateId,
            ));
        } catch (DomainException|InvalidArgumentException $exception) {
            $this->addError('classId', $exception->getMessage());

            return;
        }

        $this->toast(__(':generated generated, :withheld withheld, :failed failed.', ['generated' => $run->generated_count, 'withheld' => $run->withheld_count, 'failed' => $run->failed_count]), $run->failed_count > 0 ? 'warning' : 'success');
    }

    public function render(): View
    {
        return view('academic::report-cards.run', [
            'classes' => SchoolClass::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'gradeLevels' => GradeLevel::query()->orderBy('ordinal')->get(['id', 'name']),
            'templates' => DocumentTemplate::query()->where('template_type', 'report_card')->where('is_active', true)->get(['id', 'name', 'version']),
            'runs' => ReportCardRun::query()->orderByDesc('id')->limit(10)->get(),
        ]);
    }
}
