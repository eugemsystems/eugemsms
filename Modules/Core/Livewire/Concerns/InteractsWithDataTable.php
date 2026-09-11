<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Concerns;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Session;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

/**
 * Shared search/filter/sort/pagination/column-visibility behaviour for
 * every admin "list" screen — one place to fix a bug or add a feature
 * instead of touching every module's own index screen individually.
 * Pairs with `resources/views/components/data-table.blade.php`.
 *
 * A component using this trait must implement `tableColumns()` and call
 * `$this->paginateDataTable($query, $this->tableColumns())` from its own
 * `render()`, passing the base (unfiltered, unsorted) Eloquent query for
 * that screen.
 *
 * Column config shape, keyed by a stable column key (not necessarily the
 * DB column name):
 * ```
 * [
 *     'status' => [
 *         'label' => 'Status',            // required — the <th> text
 *         'column' => 'status',           // optional — real DB column, defaults to the key
 *         'sortable' => true,              // optional, default false
 *         'searchable' => true,            // optional, default false — included in the global search box
 *         'filter' => 'select',            // optional — 'text' | 'select' | null (no per-column filter)
 *         'options' => ['active' => 'Active', 'inactive' => 'Inactive'], // required when filter is 'select'
 *     ],
 * ]
 * ```
 *
 * @phpstan-type ColumnConfig array{label: string, column?: string, sortable?: bool, searchable?: bool, filter?: string|null, options?: array<string, string>}
 */
trait InteractsWithDataTable
{
    use WithPagination;

    /** @var array<int, int> */
    public const array PER_PAGE_OPTIONS = [10, 15, 30, 50];

    #[Url(as: 'q', history: true)]
    public string $search = '';

    #[Url(as: 'sort', history: true)]
    public ?string $sortColumn = null;

    #[Url(as: 'dir', history: true)]
    public string $sortDirection = 'asc';

    #[Url(as: 'per_page', history: true)]
    public int $perPage = 10;

    /**
     * @var array<string, string>
     */
    #[Url(as: 'filter', history: true)]
    public array $columnFilters = [];

    /**
     * Which columns are hidden — remembered per browser session (not the
     * URL), since this is a display preference, not a shareable filter.
     *
     * @var array<int, string>
     */
    #[Session]
    public array $hiddenColumns = [];

    /**
     * @return array<string, array{label: string, column?: string, sortable?: bool, searchable?: bool, filter?: string|null, options?: array<string, string>}>
     */
    abstract protected function tableColumns(): array;

    /**
     * PHP does not allow a Blade view to read a trait constant directly
     * (`SomeTrait::CONST` is refused outside the trait itself) — this is
     * the accessor the component's own view calls instead.
     *
     * @return array<int, int>
     */
    public function perPageOptions(): array
    {
        return self::PER_PAGE_OPTIONS;
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingColumnFilters(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(mixed $value): void
    {
        // This must be the "updated" (after) hook, not "updating": Livewire
        // assigns the incoming value to $this->perPage right after the
        // "updating" hook returns, which would silently overwrite a
        // correction made there.
        if (! in_array((int) $value, self::PER_PAGE_OPTIONS, true)) {
            $this->perPage = 10;
        }

        $this->resetPage();
    }

    public function sortByColumn(string $key): void
    {
        if ($this->sortColumn === $key) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortColumn = $key;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
    }

    public function toggleColumnVisibility(string $key): void
    {
        if (in_array($key, $this->hiddenColumns, true)) {
            $this->hiddenColumns = array_values(array_diff($this->hiddenColumns, [$key]));

            return;
        }

        $this->hiddenColumns[] = $key;
    }

    public function columnVisible(string $key): bool
    {
        return ! in_array($key, $this->hiddenColumns, true);
    }

    public function resetTableFilters(): void
    {
        $this->reset(['search', 'columnFilters', 'sortColumn', 'sortDirection']);
        $this->resetPage();
    }

    /**
     * @param  Builder<*>  $query
     * @param  array<string, array{label: string, column?: string, sortable?: bool, searchable?: bool, filter?: string|null, options?: array<string, string>}>  $columns
     * @return LengthAwarePaginator<int, *>
     */
    protected function paginateDataTable(Builder $query, array $columns): LengthAwarePaginator
    {
        $this->applySearch($query, $columns);
        $this->applyColumnFilters($query, $columns);
        $this->applySort($query, $columns);

        return $query->paginate($this->perPage, pageName: 'page');
    }

    /**
     * @param  Builder<*>  $query
     * @param  array<string, array{label: string, column?: string, sortable?: bool, searchable?: bool, filter?: string|null, options?: array<string, string>}>  $columns
     */
    private function applySearch(Builder $query, array $columns): void
    {
        $term = trim($this->search);

        if ($term === '') {
            return;
        }

        $searchableColumns = [];

        foreach ($columns as $key => $config) {
            if ($config['searchable'] ?? false) {
                $searchableColumns[] = $config['column'] ?? $key;
            }
        }

        if ($searchableColumns === []) {
            return;
        }

        $query->where(function (Builder $q) use ($searchableColumns, $term): void {
            foreach ($searchableColumns as $column) {
                $q->orWhere($column, 'like', "%{$term}%");
            }
        });
    }

    /**
     * @param  Builder<*>  $query
     * @param  array<string, array{label: string, column?: string, sortable?: bool, searchable?: bool, filter?: string|null, options?: array<string, string>}>  $columns
     */
    private function applyColumnFilters(Builder $query, array $columns): void
    {
        foreach ($this->columnFilters as $key => $value) {
            if ($value === '') {
                continue;
            }

            $config = $columns[$key] ?? null;

            if ($config === null) {
                continue;
            }

            $column = $config['column'] ?? $key;
            $type = $config['filter'] ?? 'text';

            if ($type === 'select') {
                $query->where($column, $value);
            } else {
                $query->where($column, 'like', "%{$value}%");
            }
        }
    }

    /**
     * @param  Builder<*>  $query
     * @param  array<string, array{label: string, column?: string, sortable?: bool, searchable?: bool, filter?: string|null, options?: array<string, string>}>  $columns
     */
    private function applySort(Builder $query, array $columns): void
    {
        if ($this->sortColumn === null) {
            return;
        }

        $config = $columns[$this->sortColumn] ?? null;

        if ($config === null || ! ($config['sortable'] ?? false)) {
            return;
        }

        $column = $config['column'] ?? $this->sortColumn;
        $direction = $this->sortDirection === 'desc' ? 'desc' : 'asc';

        $query->orderBy($column, $direction);
    }
}
