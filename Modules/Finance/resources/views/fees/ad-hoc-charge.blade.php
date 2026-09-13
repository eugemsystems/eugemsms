<div>
    <div class="mb-4">
        <h4 class="mb-1">{{ __('Ad hoc charge') }}</h4>
        <p class="text-body-secondary mb-0">{{ __('A one-off charge outside the fee structure — a library fine, a damage bill, a uniform sale.') }}</p>
    </div>

    <ul class="nav nav-tabs mb-3">
        <li class="nav-item">
            <button type="button" class="nav-link {{ $mode === 'individual' ? 'active' : '' }}" wire:click="$set('mode', 'individual')">{{ __('Individual') }}</button>
        </li>
        <li class="nav-item">
            <button type="button" class="nav-link {{ $mode === 'bulk' ? 'active' : '' }}" wire:click="$set('mode', 'bulk')">{{ __('Bulk to class') }}</button>
        </li>
    </ul>

    <div class="card">
        <div class="card-body">
            <form wire:submit="raise">
                @if ($mode === 'individual')
                    <div class="mb-3">
                        <label class="form-label" for="studentSearch">{{ __('Learner') }}</label>
                        @if ($selectedStudentId !== null)
                            <div class="input-group">
                                <input type="text" class="form-control" value="{{ $selectedStudentLabel }}" disabled>
                                <button type="button" class="btn btn-outline-secondary" wire:click="$set('selectedStudentId', null)">{{ __('Change') }}</button>
                            </div>
                        @else
                            <input type="text" class="form-control @error('selectedStudentId') is-invalid @enderror" id="studentSearch" wire:model.live.debounce.300ms="studentSearch" placeholder="{{ __('Search by admission number or name…') }}">
                            @error('selectedStudentId') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                            @if ($studentSearch !== '')
                                <div class="list-group mt-1">
                                    @forelse ($this->studentResults() as $result)
                                        <button type="button" wire:key="result-{{ $result->id }}" class="list-group-item list-group-item-action" wire:click="selectStudent({{ $result->id }})">
                                            {{ $result->admission_number }} — {{ $result->fullName() }}
                                        </button>
                                    @empty
                                        <div class="list-group-item text-body-secondary">{{ __('No matches.') }}</div>
                                    @endforelse
                                </div>
                            @endif
                        @endif
                    </div>
                @else
                    <div class="mb-3">
                        <div class="form-floating form-floating-outline">
                            <select class="form-select" id="classId" wire:model="classId">
                                <option value="">{{ __('Select a class') }}</option>
                                @foreach ($classes as $class)
                                    <option value="{{ $class->id }}">{{ $class->name }}</option>
                                @endforeach
                            </select>
                            <label for="classId">{{ __('Class') }}</label>
                        </div>
                    </div>
                @endif

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <div class="form-floating form-floating-outline">
                            <select class="form-select @error('componentId') is-invalid @enderror" id="componentId" wire:model="componentId">
                                <option value="">{{ __('Select a component') }}</option>
                                @foreach ($components as $component)
                                    <option value="{{ $component->id }}">{{ $component->code }} — {{ $component->name }}</option>
                                @endforeach
                            </select>
                            <label for="componentId">{{ __('Component') }}</label>
                            @error('componentId') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-floating form-floating-outline">
                            <input type="text" class="form-control @error('description') is-invalid @enderror" id="description" wire:model="description" placeholder=" ">
                            <label for="description">{{ __('Description') }}</label>
                            @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-floating form-floating-outline">
                            <input type="number" step="0.01" min="0.01" class="form-control @error('quantity') is-invalid @enderror" id="quantity" wire:model="quantity" placeholder=" ">
                            <label for="quantity">{{ __('Quantity') }}</label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-floating form-floating-outline">
                            <input type="text" inputmode="decimal" class="form-control @error('unitRate') is-invalid @enderror" id="unitRate" wire:model="unitRate" placeholder=" ">
                            <label for="unitRate">{{ __('Unit rate') }}</label>
                            @error('unitRate') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-floating form-floating-outline">
                            <select class="form-select" id="currency" wire:model="currency">
                                <option value="USD">USD</option>
                                <option value="ZWG">ZWG</option>
                            </select>
                            <label for="currency">{{ __('Currency') }}</label>
                        </div>
                    </div>
                </div>

                @if ($this->canSelfApprove())
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" role="switch" id="selfApprove" wire:model="selfApprove">
                        <label class="form-check-label" for="selfApprove">{{ __('I am approving this charge (needed above the approval threshold)') }}</label>
                    </div>
                @endif

                <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">{{ __('Raise charge') }}</button>
            </form>
        </div>
    </div>
</div>
