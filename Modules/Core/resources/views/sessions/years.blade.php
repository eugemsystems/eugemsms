<div>
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div>
            <h4 class="mb-1">{{ __('Academic years') }}</h4>
            <p class="text-body-secondary mb-0">{{ __('Years and terms for :school.', ['school' => $school->name]) }}</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('sessions.calendar', $school) }}" class="btn btn-outline-secondary" wire:navigate>
                <i class="ri ri-calendar-line me-1"></i>{{ __('Calendar') }}
            </a>
            <a href="{{ route('sessions.rollover', $school) }}" class="btn btn-outline-secondary" wire:navigate>
                <i class="ri ri-refresh-line me-1"></i>{{ __('Rollover') }}
            </a>
            <a href="{{ route('sessions.snapshots', $school) }}" class="btn btn-outline-secondary" wire:navigate>
                <i class="ri ri-camera-line me-1"></i>{{ __('Snapshots') }}
            </a>
            <a href="{{ route('sessions.transitions', $school) }}" class="btn btn-outline-secondary" wire:navigate>
                <i class="ri ri-file-list-3-line me-1"></i>{{ __('Transition log') }}
            </a>
            <a href="{{ route('sessions.years.create', $school) }}" class="btn btn-primary" wire:navigate>
                <i class="ri ri-add-line me-1"></i>{{ __('New academic year') }}
            </a>
        </div>
    </div>

    <x-data-table
        :columns="$this->tableColumns()"
        :rows="$years"
        :search="$search"
        :sort-column="$sortColumn"
        :sort-direction="$sortDirection"
        :per-page="$perPage"
        :column-filters="$columnFilters"
        :hidden-columns="$hiddenColumns"
        :per-page-options="$this->perPageOptions()"
        :with-actions="false"
        class="mb-4"
    >
        @forelse ($years as $year)
            <tr wire:key="year-{{ $year->id }}" role="button" wire:click="selectYear({{ $year->id }})" class="{{ $selectedYearId === $year->id ? 'table-active' : '' }}">
                @if ($this->columnVisible('name'))
                    <td><span class="fw-medium">{{ $year->name }}</span></td>
                @endif
                @if ($this->columnVisible('starts_on'))
                    <td>{{ $year->starts_on->format('d M Y') }}</td>
                @endif
                @if ($this->columnVisible('ends_on'))
                    <td>{{ $year->ends_on->format('d M Y') }}</td>
                @endif
                @if ($this->columnVisible('is_current'))
                    <td>
                        @if ($year->is_current)
                            <span class="badge text-bg-success">{{ __('Current') }}</span>
                        @endif
                    </td>
                @endif
                @if ($this->columnVisible('academic_state'))
                    <td><span class="badge text-bg-light text-capitalize">{{ str_replace('_', ' ', $year->academic_state->value) }}</span></td>
                @endif
                @if ($this->columnVisible('financial_state'))
                    <td><span class="badge text-bg-light text-capitalize">{{ str_replace('_', ' ', $year->financial_state->value) }}</span></td>
                @endif
            </tr>
        @empty
            <tr>
                <td colspan="6" class="text-center text-body-secondary py-4">
                    {{ __('No academic years yet.') }}
                </td>
            </tr>
        @endforelse
    </x-data-table>

    @if ($selectedYearId !== null)
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h6 class="mb-0">{{ __('Terms') }}</h6>
                <button type="button" class="btn btn-sm btn-outline-primary" wire:click="openTermModal">
                    <i class="ri ri-add-line me-1"></i>{{ __('New term') }}
                </button>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('#') }}</th>
                            <th>{{ __('Name') }}</th>
                            <th>{{ __('Starts') }}</th>
                            <th>{{ __('Ends') }}</th>
                            <th>{{ __('Teaching days') }}</th>
                            <th>{{ __('Academic') }}</th>
                            <th>{{ __('Financial') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($terms as $term)
                            <tr wire:key="term-{{ $term->id }}">
                                <td>{{ $term->number }}</td>
                                <td>{{ $term->name }}</td>
                                <td>{{ $term->starts_on->format('d M Y') }}</td>
                                <td>{{ $term->ends_on->format('d M Y') }}</td>
                                <td>{{ $term->teaching_days ?? '—' }}</td>
                                <td><span class="badge text-bg-light text-capitalize">{{ str_replace('_', ' ', $term->academic_state->value) }}</span></td>
                                <td><span class="badge text-bg-light text-capitalize">{{ str_replace('_', ' ', $term->financial_state->value) }}</span></td>
                                <td class="text-end">
                                    <div class="d-flex justify-content-end gap-1">
                                        <a href="{{ route('sessions.terms.show', [$school, $term]) }}" class="btn btn-icon btn-sm btn-outline-primary" wire:navigate title="{{ __('Manage') }}" aria-label="{{ __('Manage') }}">
                                            <i class="icon-base ri ri-arrow-right-line icon-22px"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-body-secondary py-4">
                                    {{ __('No terms yet.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if ($showTermModal)
        <div class="modal show d-block" tabindex="-1" style="background: rgba(0, 0, 0, .5);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form wire:submit="createTerm">
                        <div class="modal-header">
                            <h5 class="modal-title">{{ __('New term') }}</h5>
                            <button type="button" class="btn-close" wire:click="$set('showTermModal', false)" aria-label="{{ __('Close') }}"></button>
                        </div>
                        <div class="modal-body">
                            <div class="form-floating form-floating-outline mb-3">
                                <input type="number" min="1" max="127" class="form-control @error('number') is-invalid @enderror" id="term-number" wire:model="termNumber" placeholder=" ">
                                <label for="term-number">{{ __('Term number') }}</label>
                                @error('number')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-floating form-floating-outline mb-3">
                                <input type="text" class="form-control @error('name') is-invalid @enderror" id="term-name" wire:model="termName" placeholder=" ">
                                <label for="term-name">{{ __('Name') }}</label>
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="row g-3">
                                <div class="col">
                                    <div class="form-floating form-floating-outline">
                                        <input type="date" class="form-control @error('starts_on') is-invalid @enderror" id="term-starts-on" wire:model="termStartsOn" placeholder=" ">
                                        <label for="term-starts-on">{{ __('Starts on') }}</label>
                                        @error('starts_on')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="form-floating form-floating-outline">
                                        <input type="date" class="form-control @error('ends_on') is-invalid @enderror" id="term-ends-on" wire:model="termEndsOn" placeholder=" ">
                                        <label for="term-ends-on">{{ __('Ends on') }}</label>
                                        @error('ends_on')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" wire:click="$set('showTermModal', false)">{{ __('Cancel') }}</button>
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">{{ __('Create') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
