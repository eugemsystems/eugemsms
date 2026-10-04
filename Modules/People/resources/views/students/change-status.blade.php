<div>
    <h4 class="mb-1">{{ __('Change student status') }}</h4>
    <p class="text-body-secondary mb-4">{{ __(':name — currently :status', ['name' => $student->fullName(), 'status' => ucfirst($student->status)]) }}</p>

    <div class="card">
        <div class="card-body">
            @if ($allowedStatuses === [])
                <p class="text-body-secondary mb-0">{{ __('No further status transitions are available from this status.') }}</p>
            @else
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="form-floating form-floating-outline">
                            <select class="form-select @error('newStatus') is-invalid @enderror" wire:model.live="newStatus">
                                <option value="">{{ __('Select') }}</option>
                                @foreach ($allowedStatuses as $status)
                                    <option value="{{ $status }}">{{ ucfirst($status) }}</option>
                                @endforeach
                            </select>
                            <label>{{ __('New status') }}</label>
                            @error('newStatus') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    @if ($newStatus === 'withdrawn')
                        <div class="col-md-4">
                            <div class="form-floating form-floating-outline">
                                <input type="date" class="form-control @error('exitedOn') is-invalid @enderror" wire:model="exitedOn">
                                <label>{{ __('Exit date') }}</label>
                                @error('exitedOn') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    @endif
                    <div class="col-12">
                        <div class="form-floating form-floating-outline">
                            <textarea class="form-control" wire:model="reason" style="height: 80px" placeholder=" "></textarea>
                            <label>{{ __('Reason (optional)') }}</label>
                        </div>
                    </div>
                </div>

                @if ($newStatus === 'suspended')
                    <div class="alert alert-warning mt-3 mb-0">{{ __('Suspension does not stop billing (BR-PPL-01-012). Waiving fees during a suspension is a separate, approved decision in Finance.') }}</div>
                @endif

                <div class="mt-4 d-flex gap-2">
                    <a href="{{ route('people.students.show', [$school, $student]) }}" class="btn btn-outline-secondary" wire:navigate>{{ __('Cancel') }}</a>
                    <button type="button" class="btn btn-primary" wire:click="save" wire:loading.attr="disabled" wire:confirm="{{ __('Confirm this status change?') }}">
                        {{ __('Confirm change') }}
                    </button>
                </div>
            @endif
        </div>
    </div>
</div>
