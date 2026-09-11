{{--
    Shared admin list-screen table shell — search box, per-column filters,
    column-visibility toggle, page-size selector, sortable headers, and
    pagination footer. Pairs with
    Modules\Core\Livewire\Concerns\InteractsWithDataTable, which owns all
    the actual state/query logic on the Livewire component side — this
    view only renders it. Edit here once; every module's list screen that
    uses this component picks up the fix/feature automatically.

    Usage: the calling Livewire view supplies $columns/$rows (and the
    trait's public properties) and fills the default slot with its own
    <tr> row markup (each row MUST carry its own wire:key). Column
    visibility must be checked per cell in that row markup too, e.g.
    @if ($columnVisible('status')) <td>...</td> @endif — hiding a column
    here only hides the header, not any cell the caller still renders.
--}}
@props([
    'columns' => [],
    'rows' => null,
    'search' => '',
    'sortColumn' => null,
    'sortDirection' => 'asc',
    'perPage' => 10,
    'columnFilters' => [],
    'hiddenColumns' => [],
    'perPageOptions' => [10, 15, 30, 50],
])

@php
    // A hidden column's filter is hidden too — filtering by something you
    // can't see the values of on-screen would be confusing, and the
    // column stays reachable again via the "Columns" toggle below.
    $filterableColumns = collect($columns)->filter(
        fn (array $c, string $key) => ($c['filter'] ?? null) !== null && ! in_array($key, $hiddenColumns, true)
    );
@endphp

<div {{ $attributes->class('card') }}>
    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div class="d-flex flex-wrap align-items-center gap-2">
            <div class="input-group input-group-merge" style="max-width: 16rem;">
                <span class="input-group-text"><i class="ri ri-search-line"></i></span>
                <input
                    type="search"
                    class="form-control"
                    placeholder="{{ __('Search…') }}"
                    wire:model.live.debounce.400ms="search"
                    aria-label="{{ __('Search') }}"
                >
            </div>

            @if ($filterableColumns->isNotEmpty())
                <div class="dropdown">
                    <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="ri ri-filter-3-line me-1"></i>{{ __('Filters') }}
                    </button>
                    <div class="dropdown-menu p-3" style="min-width: 16rem;">
                        @foreach ($filterableColumns as $key => $config)
                            <div class="mb-2" wire:key="table-filter-{{ $key }}">
                                <label class="form-label small mb-1" for="table-filter-{{ $key }}">{{ $config['label'] }}</label>
                                @if ($config['filter'] === 'select')
                                    <select id="table-filter-{{ $key }}" class="form-select form-select-sm" wire:model.live="columnFilters.{{ $key }}">
                                        <option value="">{{ __('All') }}</option>
                                        @foreach ($config['options'] ?? [] as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <input
                                        type="text"
                                        id="table-filter-{{ $key }}"
                                        class="form-control form-control-sm"
                                        wire:model.live.debounce.400ms="columnFilters.{{ $key }}"
                                        placeholder="{{ __('Any') }}"
                                    >
                                @endif
                            </div>
                        @endforeach
                        <button type="button" class="btn btn-sm btn-link p-0" wire:click="resetTableFilters">
                            {{ __('Clear all filters') }}
                        </button>
                    </div>
                </div>
            @endif
        </div>

        <div class="d-flex align-items-center gap-2">
            <div class="dropdown">
                <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="ri ri-layout-column-line me-1"></i>{{ __('Columns') }}
                </button>
                <div class="dropdown-menu dropdown-menu-end p-2" style="min-width: 14rem;">
                    @foreach ($columns as $key => $config)
                        <div class="form-check" wire:key="table-column-toggle-{{ $key }}">
                            <input
                                class="form-check-input"
                                type="checkbox"
                                id="table-col-{{ $key }}"
                                @checked(! in_array($key, $hiddenColumns, true))
                                wire:click="toggleColumnVisibility('{{ $key }}')"
                            >
                            <label class="form-check-label" for="table-col-{{ $key }}">{{ $config['label'] }}</label>
                        </div>
                    @endforeach
                </div>
            </div>

            <select class="form-select form-select-sm" style="width: auto;" wire:model.live="perPage" aria-label="{{ __('Rows per page') }}">
                @foreach ($perPageOptions as $option)
                    <option value="{{ $option }}">{{ __(':count / page', ['count' => $option]) }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    @foreach ($columns as $key => $config)
                        @if (! in_array($key, $hiddenColumns, true))
                            <th wire:key="table-header-{{ $key }}" @if ($config['sortable'] ?? false) role="button" wire:click="sortByColumn('{{ $key }}')" @endif>
                                {{ $config['label'] }}
                                @if (($config['sortable'] ?? false) && $sortColumn === $key)
                                    <i class="ri {{ $sortDirection === 'asc' ? 'ri-arrow-up-s-line' : 'ri-arrow-down-s-line' }}"></i>
                                @endif
                            </th>
                        @endif
                    @endforeach
                </tr>
            </thead>
            <tbody>
                {{ $slot }}
            </tbody>
        </table>
    </div>

    @if ($rows !== null && method_exists($rows, 'total') && $rows->total() > 0)
        <div class="card-footer d-flex flex-wrap align-items-center justify-content-between gap-2">
            <span class="text-body-secondary small">
                {{ __('Showing :from to :to of :total', ['from' => $rows->firstItem(), 'to' => $rows->lastItem(), 'total' => $rows->total()]) }}
            </span>
            {{ $rows->links() }}
        </div>
    @endif
</div>
