<div>
    <div class="mb-4">
        <h4 class="mb-0">{{ __('Circulation desk') }}</h4>
        <p class="text-body-secondary small mb-0">{{ __('Scan a copy to issue or return it. A late return or a lost copy is charged to the learner\'s fee account.') }}</p>
    </div>
    <div class="row g-4">
        <div class="col-xl-5">
            <div class="card mb-3"><div class="card-header">{{ __('Issue') }}</div><div class="card-body">
                <div class="btn-group btn-group-sm mb-2" role="group">
                    <input type="radio" class="btn-check" id="bt-student" value="student" wire:model.live="borrowerType"><label class="btn btn-outline-secondary" for="bt-student">{{ __('Learner') }}</label>
                    <input type="radio" class="btn-check" id="bt-staff" value="staff" wire:model.live="borrowerType"><label class="btn btn-outline-secondary" for="bt-staff">{{ __('Staff') }}</label>
                </div>
                @if ($borrowerLabel !== '')
                    <div class="mb-2"><strong>{{ $borrowerLabel }}</strong></div>
                @else
                    <input type="search" class="form-control form-control-sm mb-1" wire:model.live.debounce.300ms="borrowerSearch" placeholder="{{ __('Find the borrower') }}">
                    @error('borrowerSearch') <div class="text-danger small mb-1">{{ $message }}</div> @enderror
                    @foreach ($results as $id => $label) <button type="button" class="list-group-item list-group-item-action small border rounded mb-1" wire:key="b-{{ $id }}" wire:click="selectBorrower({{ $id }})">{{ $label }}</button> @endforeach
                @endif
                <select class="form-select form-select-sm my-2" wire:model="borrowerCategory"><option value="">{{ __('Borrower category…') }}</option>@foreach ($categories as $category) <option value="{{ $category->category }}">{{ $category->category }}</option> @endforeach</select>
                @error('borrowerCategory') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                <input type="text" class="form-control form-control-sm mb-2" wire:model="copyCode" wire:keydown.enter="issue" placeholder="{{ __('Scan accession number or barcode') }}" autofocus>
                @error('copyCode') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                <button type="button" class="btn btn-primary btn-sm" wire:click="issue">{{ __('Issue') }}</button>
            </div></div>
            <div class="card"><div class="card-header">{{ __('Return') }}</div><div class="card-body">
                <input type="text" class="form-control form-control-sm mb-2" wire:model="returnCode" wire:keydown.enter="returnCopy" placeholder="{{ __('Scan the returned copy') }}">
                <div class="row g-2 mb-2">
                    <div class="col-5"><select class="form-select form-select-sm" wire:model="returnCondition">@foreach (['new', 'good', 'fair', 'poor', 'damaged'] as $c) <option value="{{ $c }}">{{ __(ucfirst($c)) }}</option> @endforeach</select></div>
                    <div class="col-7"><select class="form-select form-select-sm" wire:model="feeComponentId"><option value="">{{ __('Fee component for fines…') }}</option>@foreach ($components as $component) <option value="{{ $component->id }}">{{ $component->name }}</option> @endforeach</select></div>
                </div>
                @error('returnCode') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                <button type="button" class="btn btn-primary btn-sm" wire:click="returnCopy">{{ __('Return') }}</button>
            </div></div>
        </div>
        <div class="col-xl-7">
            <div class="card"><div class="card-header">{{ $borrowerLabel !== '' ? __('On loan to :name', ['name' => $borrowerLabel]) : __('Choose a borrower to see their loans') }}</div>
                <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                    <thead><tr><th>{{ __('Title') }}</th><th>{{ __('Due') }}</th><th class="text-end">{{ __('Renewals') }}</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($loans as $loan)
                            <tr wire:key="loan-{{ $loan->id }}" class="{{ $loan->isOverdue() ? 'table-warning' : '' }}">
                                <td>{{ $loan->copy->item->title }}<div class="small text-body-secondary">{{ $loan->copy->accession_number }}</div></td>
                                <td>{{ $loan->due_on->format('d M Y') }}</td><td class="text-end">{{ $loan->renewal_count }}</td>
                                <td class="text-end text-nowrap">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="renew({{ $loan->id }})">{{ __('Renew') }}</button>
                                    <button type="button" class="btn btn-sm btn-outline-danger" wire:click="markLost({{ $loan->id }})" wire:confirm="{{ __('Mark this copy lost and charge the borrower?') }}">{{ __('Lost') }}</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('No active loans.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table></div>
            </div>
        </div>
    </div>
</div>
