<div>
    <div class="mb-4">
        <h4 class="mb-0">{{ __('Library catalogue') }}</h4>
        <p class="text-body-secondary small mb-0">{{ __('Search titles and see how many copies are on the shelf.') }}</p>
    </div>
    <div class="row g-4">
        <div class="{{ $canManage ? 'col-xl-8' : 'col-12' }}">
            <div class="d-flex flex-wrap gap-2 mb-3">
                <input type="search" class="form-control form-control-sm w-auto" wire:model.live.debounce.300ms="search" placeholder="{{ __('Title, author or ISBN') }}">
                <select class="form-select form-select-sm w-auto" wire:model.live="categoryFilter"><option value="">{{ __('All categories') }}</option>@foreach (['textbook', 'reference', 'fiction', 'non_fiction', 'periodical', 'digital'] as $c) <option value="{{ $c }}">{{ __(ucfirst(str_replace('_', ' ', $c))) }}</option> @endforeach</select>
            </div>
            <div class="card"><div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>{{ __('Title') }}</th><th>{{ __('Category') }}</th><th class="text-end">{{ __('On shelf') }}</th><th class="text-end">{{ __('Copies') }}</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($items as $item)
                            <tr wire:key="item-{{ $item->id }}">
                                <td>{{ $item->title }}<div class="small text-body-secondary">{{ $item->author }} {{ $item->isbn ? '· '.$item->isbn : '' }}</div></td>
                                <td class="small">{{ str_replace('_', ' ', $item->item_category) }}</td>
                                <td class="text-end">{{ $item->copies_available }}</td><td class="text-end">{{ $item->copies_total }}</td>
                                <td class="text-end">@if ($item->digital_resource_url) <a href="{{ $item->digital_resource_url }}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-secondary">{{ __('Open') }}</a> @endif</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('Nothing matches.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div></div>
        </div>
        @if ($canManage)
            <div class="col-xl-4">
                <div class="card mb-3"><div class="card-header">{{ __('Catalogue a title') }}</div><div class="card-body">
                    <input type="text" class="form-control form-control-sm mb-2" wire:model="title" placeholder="{{ __('Title') }}">
                    @error('title') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                    <input type="text" class="form-control form-control-sm mb-2" wire:model="author" placeholder="{{ __('Author') }}">
                    <input type="text" class="form-control form-control-sm mb-2" wire:model="isbn" placeholder="{{ __('ISBN') }}">
                    <select class="form-select form-select-sm mb-2" wire:model="itemCategory">@foreach (['textbook', 'reference', 'fiction', 'non_fiction', 'periodical', 'digital'] as $c) <option value="{{ $c }}">{{ __(ucfirst(str_replace('_', ' ', $c))) }}</option> @endforeach</select>
                    <select class="form-select form-select-sm mb-2" wire:model="subjectId"><option value="">{{ __('Subject (optional)') }}</option>@foreach ($subjects as $subject) <option value="{{ $subject->id }}">{{ $subject->name }}</option> @endforeach</select>
                    <input type="number" step="0.01" min="0" class="form-control form-control-sm mb-2" wire:model="replacementCost" placeholder="{{ __('Replacement cost') }}">
                    <input type="url" class="form-control form-control-sm mb-2" wire:model="digitalUrl" placeholder="{{ __('Digital link (optional)') }}">
                    <button type="button" class="btn btn-primary btn-sm" wire:click="addItem">{{ __('Add title') }}</button>
                </div></div>
                <div class="card mb-3"><div class="card-header">{{ __('Add copies') }}</div><div class="card-body">
                    <select class="form-select form-select-sm mb-2" wire:model="copyItemId"><option value="">{{ __('Title…') }}</option>@foreach ($allItems as $item) <option value="{{ $item->id }}">{{ $item->title }}</option> @endforeach</select>
                    @error('copyItemId') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                    <div class="row g-2 mb-2"><div class="col-6"><input type="number" min="1" max="200" class="form-control form-control-sm" wire:model="copyCount"></div>
                        <div class="col-6"><select class="form-select form-select-sm" wire:model="copyCondition">@foreach (['new', 'good', 'fair', 'poor'] as $c) <option value="{{ $c }}">{{ __(ucfirst($c)) }}</option> @endforeach</select></div></div>
                    <button type="button" class="btn btn-primary btn-sm" wire:click="addCopies">{{ __('Accession copies') }}</button>
                </div></div>
                <div class="card"><div class="card-header">{{ __('Loan limits by borrower category') }}</div><div class="card-body">
                    <ul class="list-unstyled small mb-3">@foreach ($categories as $category) <li>{{ $category->category }} — {{ $category->max_concurrent_loans }} {{ __('loans') }}, {{ $category->loan_period_days }} {{ __('days') }}, {{ $category->max_renewals }} {{ __('renewals') }}</li> @endforeach</ul>
                    <input type="text" class="form-control form-control-sm mb-2" wire:model="borrowerCategoryName" placeholder="{{ __('Category (e.g. secondary, staff)') }}">
                    @error('borrowerCategoryName') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                    <div class="row g-2 mb-2"><div class="col-4"><input type="number" min="1" class="form-control form-control-sm" wire:model="maxLoans" title="{{ __('Loans') }}"></div><div class="col-4"><input type="number" min="1" class="form-control form-control-sm" wire:model="loanDays" title="{{ __('Days') }}"></div><div class="col-4"><input type="number" min="0" class="form-control form-control-sm" wire:model="maxRenewals" title="{{ __('Renewals') }}"></div></div>
                    <button type="button" class="btn btn-primary btn-sm" wire:click="saveBorrowerCategory">{{ __('Save category') }}</button>
                </div></div>
            </div>
        @endif
    </div>
</div>
