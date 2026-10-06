<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Library;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\CreateBorrowerCategoryAction;
use Modules\Academic\Domain\Actions\CreateLibraryCopyAction;
use Modules\Academic\Domain\Actions\CreateLibraryItemAction;
use Modules\Academic\Domain\DataObjects\CreateBorrowerCategoryData;
use Modules\Academic\Domain\DataObjects\CreateLibraryCopyData;
use Modules\Academic\Domain\DataObjects\CreateLibraryItemData;
use Modules\Academic\Livewire\Concerns\ChecksPermissions;
use Modules\Academic\Models\BorrowerCategory;
use Modules\Academic\Models\LibraryItem;
use Modules\Academic\Models\Subject;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Academic\Library\Catalogue` (Book K ACA-10 §5, `library.view`). Search and
 * availability for everyone with library access; cataloguing items, adding
 * accessioned copies and setting borrower-category loan limits needs
 * `library.catalogue.manage`. A digital link is only a pointer (BR-ACA-10-011).
 */
#[Title('Library catalogue')]
#[Layout('layouts.app')]
final class Catalogue extends Component
{
    use AuthorizesPermissions;
    use ChecksPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $search = '';

    public string $categoryFilter = '';

    public string $title = '';

    public string $author = '';

    public string $isbn = '';

    public string $itemCategory = 'textbook';

    public ?int $subjectId = null;

    public string $replacementCost = '';

    public string $digitalUrl = '';

    public ?int $copyItemId = null;

    public int $copyCount = 1;

    public string $copyCondition = 'new';

    public string $borrowerCategoryName = '';

    public int $maxLoans = 3;

    public int $loanDays = 14;

    public int $maxRenewals = 1;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('library.view');
    }

    public function addItem(): void
    {
        $this->authorizePermission('library.catalogue.manage');
        $this->resetErrorBag();
        $this->validate(['title' => ['required', 'string', 'max:300'], 'author' => ['nullable', 'string', 'max:200'], 'isbn' => ['nullable', 'string', 'max:20'], 'replacementCost' => ['nullable', 'numeric', 'gt:0']]);

        try {
            app(CreateLibraryItemAction::class)->execute(new CreateLibraryItemData(
                schoolId: $this->school->id, title: $this->title, itemCategory: $this->itemCategory,
                isbn: $this->isbn === '' ? null : $this->isbn, author: $this->author === '' ? null : $this->author,
                subjectId: $this->subjectId,
                replacementCostMinor: $this->replacementCost === '' ? null : (int) round((float) $this->replacementCost * 100),
                currency: $this->replacementCost === '' ? null : $this->school->base_currency,
                digitalResourceUrl: $this->digitalUrl === '' ? null : $this->digitalUrl,
            ));
        } catch (InvalidArgumentException $exception) {
            $this->addError('title', $exception->getMessage());

            return;
        }

        $this->reset('title', 'author', 'isbn', 'replacementCost', 'digitalUrl');
        $this->toast(__('Item catalogued.'));
    }

    public function addCopies(): void
    {
        $this->authorizePermission('library.catalogue.manage');
        $this->resetErrorBag();

        $item = $this->copyItemId === null ? null : LibraryItem::query()->find($this->copyItemId);

        if ($item === null || $this->copyCount < 1 || $this->copyCount > 200) {
            $this->addError('copyItemId', __('Choose an item and between 1 and 200 copies.'));

            return;
        }

        try {
            for ($i = 0; $i < $this->copyCount; $i++) {
                app(CreateLibraryCopyAction::class)->execute(new CreateLibraryCopyData(itemId: $item->id, allocatedByUserId: (int) auth()->id(), condition: $this->copyCondition));
            }
        } catch (InvalidArgumentException $exception) {
            $this->addError('copyItemId', $exception->getMessage());

            return;
        }

        $this->toast(trans_choice(':count copy added.|:count copies added.', $this->copyCount, ['count' => $this->copyCount]));
    }

    public function saveBorrowerCategory(): void
    {
        $this->authorizePermission('library.catalogue.manage');
        $this->resetErrorBag();

        try {
            app(CreateBorrowerCategoryAction::class)->execute(new CreateBorrowerCategoryData(
                schoolId: $this->school->id, category: $this->borrowerCategoryName, maxConcurrentLoans: $this->maxLoans,
                loanPeriodDays: $this->loanDays, maxRenewals: $this->maxRenewals,
            ));
        } catch (InvalidArgumentException $exception) {
            $this->addError('borrowerCategoryName', $exception->getMessage());

            return;
        }

        $this->reset('borrowerCategoryName');
        $this->toast(__('Borrower category saved.'));
    }

    public function render(): View
    {
        $term = trim($this->search);
        $like = '%'.addcslashes($term, '%_\\').'%';

        $items = LibraryItem::query()
            ->withCount(['copies as copies_total', 'copies as copies_available' => fn ($q) => $q->where('status', 'available')])
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->where('title', 'like', $like)->orWhere('author', 'like', $like)->orWhere('isbn', 'like', $like)))
            ->when($this->categoryFilter !== '', fn ($q) => $q->where('item_category', $this->categoryFilter))
            ->orderBy('title')->limit(100)->get();

        return view('academic::library.catalogue', [
            'items' => $items,
            'subjects' => Subject::query()->orderBy('name')->get(['id', 'name']),
            'categories' => BorrowerCategory::query()->orderBy('category')->get(),
            'canManage' => $this->holds('library.catalogue.manage'),
            'allItems' => $this->holds('library.catalogue.manage') ? LibraryItem::query()->where('is_active', true)->orderBy('title')->get(['id', 'title']) : collect(),
        ]);
    }
}
