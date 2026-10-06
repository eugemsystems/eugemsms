<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Cbt;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\ComputeItemAnalysisAction;
use Modules\Academic\Domain\DataObjects\ComputeItemAnalysisData;
use Modules\Academic\Models\CbtTest;
use Modules\Academic\Models\QuestionBankItem;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Academic\Cbt\ItemAnalysis` (Book K ACA-09 §5, `cbt.bank.manage`).
 * Difficulty (the share of candidates who got an item right) and
 * discrimination (how much better the top 27% did than the bottom 27%),
 * computed from a CLOSED test's actual results and written back to the
 * bank (BR-ACA-09-009). A poorly discriminating item is flagged for
 * review, not removed — retiring one is a person's decision in the bank.
 */
#[Title('Item analysis')]
#[Layout('layouts.app')]
final class ItemAnalysis extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $testId = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('cbt.bank.manage');

        $this->testId = CbtTest::query()->whereIn('status', ['closed', 'results_released'])->orderByDesc('id')->value('id');
    }

    public function compute(): void
    {
        $this->authorizePermission('cbt.bank.manage');

        $test = CbtTest::query()->findOrFail($this->testId);

        try {
            $results = app(ComputeItemAnalysisAction::class)->execute(new ComputeItemAnalysisData($test->id));
        } catch (DomainException $exception) {
            $this->toast($exception->getMessage(), 'danger');

            return;
        }

        $this->toast($results === [] ? __('Not enough submitted attempts to analyse yet.') : __(':count item(s) analysed.', ['count' => count($results)]));
    }

    public function render(): View
    {
        $test = $this->testId === null ? null : CbtTest::query()->find($this->testId);

        return view('academic::cbt.item-analysis', [
            'tests' => CbtTest::query()->whereIn('status', ['closed', 'results_released'])->orderByDesc('id')->limit(30)->get(['id', 'title', 'status']),
            'test' => $test,
            'items' => $test === null ? collect() : QuestionBankItem::query()->whereIn('id', $test->question_ids ?? [])->where('is_auto_markable', true)->orderBy('id')->get(),
        ]);
    }
}
