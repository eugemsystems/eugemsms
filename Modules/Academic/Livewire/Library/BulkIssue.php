<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Library;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\ProcessBulkTextbookIssueAction;
use Modules\Academic\Domain\Actions\ProcessBulkTextbookReturnAction;
use Modules\Academic\Domain\DataObjects\ProcessBulkTextbookIssueData;
use Modules\Academic\Domain\DataObjects\ProcessBulkTextbookReturnData;
use Modules\Academic\Models\BulkTextbookIssue;
use Modules\Academic\Models\ClassAllocation;
use Modules\Academic\Models\LibraryItem;
use Modules\Academic\Models\Loan;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Core\Models\SchoolClass;
use Modules\Core\Models\Term;
use Modules\Finance\Models\FeeComponent;
use Modules\People\Models\Student;

/**
 * `Academic\Library\BulkIssue` (Book K ACA-10 §3/§5, `library.bulk_issue`).
 * Issues a set of textbooks to a whole class, or collects them at term end,
 * as one operation: a learner with no available copy is recorded as an
 * exception and never blocks the rest (BR-ACA-10-007). On return, any
 * textbook flagged as not handed back is charged to the learner at the
 * title's replacement cost through FIN-02 and blocks their library clearance
 * (BR-ACA-10-008).
 */
#[Title('Bulk textbook issue')]
#[Layout('layouts.app')]
final class BulkIssue extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $classId = null;

    /** @var array<int, int|string> */
    public array $itemIds = [];

    public string $mode = 'issue';

    public ?int $feeComponentId = null;

    /** @var array<int, string> "studentId:itemId" keys flagged as NOT handed back */
    public array $notReturned = [];

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('library.bulk_issue');
    }

    public function updatedClassId(): void
    {
        $this->notReturned = [];
    }

    public function updatedItemIds(): void
    {
        $this->notReturned = [];
    }

    public function run(): void
    {
        $this->authorizePermission('library.bulk_issue');
        $this->resetErrorBag();

        $class = $this->classId === null ? null : SchoolClass::query()->find($this->classId);
        $term = Term::query()->where('starts_on', '<=', now())->orderByDesc('starts_on')->first();
        $itemIds = array_values(array_unique(array_map('intval', $this->itemIds)));

        if ($class === null || $term === null || $itemIds === []) {
            $this->addError('classId', __('Choose a class and at least one textbook.'));

            return;
        }

        try {
            if ($this->mode === 'issue') {
                app(ProcessBulkTextbookIssueAction::class)->execute(new ProcessBulkTextbookIssueData(
                    schoolId: $this->school->id, termId: $term->id, classId: $class->id, itemIds: $itemIds, issuedByUserId: (int) auth()->id(),
                ));
            } else {
                $component = $this->feeComponentId === null ? null : FeeComponent::query()->where('is_active', true)->whereKey($this->feeComponentId)->value('id');

                if ($component === null) {
                    $this->addError('feeComponentId', __('Choose the fee component unreturned books are charged to.'));

                    return;
                }

                app(ProcessBulkTextbookReturnAction::class)->execute(new ProcessBulkTextbookReturnData(
                    schoolId: $this->school->id, termId: $term->id, classId: $class->id, itemIds: $itemIds,
                    feeComponentId: (int) $component, chargedByUserId: (int) auth()->id(),
                    returnedItemIdsByStudent: $this->returnedMap($class, $itemIds),
                ));
            }
        } catch (DomainException|InvalidArgumentException $exception) {
            $this->addError('classId', $exception->getMessage());

            return;
        }

        $this->notReturned = [];
        $this->toast($this->mode === 'issue' ? __('Textbooks issued; see the report below.') : __('Collection processed; see the report below.'));
    }

    /**
     * The roster and loans are re-derived here; the client only ever
     * subtracts from "handed back".
     *
     * @param  array<int, int>  $itemIds
     * @return array<int, array<int, int>>
     */
    private function returnedMap(SchoolClass $class, array $itemIds): array
    {
        $map = [];

        foreach ($this->activeLoans($class, $itemIds) as $studentId => $items) {
            foreach ($items as $itemId) {
                if (! in_array("{$studentId}:{$itemId}", $this->notReturned, true)) {
                    $map[$studentId][] = $itemId;
                }
            }
        }

        return $map;
    }

    /**
     * @param  array<int, int>  $itemIds
     * @return array<int, array<int, int>> studentId => item ids currently on loan
     */
    private function activeLoans(SchoolClass $class, array $itemIds): array
    {
        $term = Term::query()->where('starts_on', '<=', now())->orderByDesc('starts_on')->first();
        $studentIds = ClassAllocation::query()->where('class_id', $class->id)->where('term_id', $term?->id)->where('status', 'confirmed')->pluck('student_id');

        $loans = Loan::query()->with('copy')->where('borrower_type', 'student')->whereIn('borrower_id', $studentIds)->where('status', 'active')
            ->whereHas('copy', fn ($q) => $q->whereIn('item_id', $itemIds))->get();

        $result = [];

        foreach ($loans as $loan) {
            $result[$loan->borrower_id][] = $loan->copy->item_id;
        }

        return $result;
    }

    public function render(): View
    {
        $class = $this->classId === null ? null : SchoolClass::query()->find($this->classId);
        $itemIds = array_values(array_unique(array_map('intval', $this->itemIds)));
        $outstanding = $this->mode === 'return' && $class !== null && $itemIds !== [] ? $this->activeLoans($class, $itemIds) : [];
        $history = BulkTextbookIssue::query()->with('schoolClass')->orderByDesc('id')->limit(8)->get();

        $studentIds = array_merge(array_keys($outstanding), collect($history)->flatMap(fn (BulkTextbookIssue $h) => array_column($h->exceptions ?? [], 'student_id'))->all());

        return view('academic::library.bulk-issue', [
            'classes' => SchoolClass::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'items' => LibraryItem::query()->where('is_active', true)->where('item_category', 'textbook')->orderBy('title')->get(['id', 'title']),
            'components' => FeeComponent::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'outstanding' => $outstanding,
            'students' => Student::query()->whereIn('id', $studentIds)->get()->keyBy('id'),
            'history' => $history,
            'titles' => LibraryItem::query()->pluck('title', 'id'),
        ]);
    }
}
