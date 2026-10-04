<div>
    <h4 class="mb-1">{{ __('Staff exit') }}</h4>
    <p class="text-body-secondary mb-4">{{ $staff->fullName() }} — {{ $staff->staff_number }} · <span class="badge text-bg-secondary">{{ str_replace('_', ' ', ucfirst($staff->status)) }}</span></p>

    @if (! $checklist)
        <div class="card">
            <div class="card-body">
                <p>{{ __('No exit clearance has been started for this staff member yet.') }}</p>
                <button type="button" class="btn btn-primary" wire:click="initiate">{{ __('Initiate exit clearance') }}</button>
            </div>
        </div>
    @else
        <div class="row g-4">
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">{{ __('Clearance checklist') }}</div>
                    <ul class="list-group list-group-flush">
                        @foreach ($checklist->items as $item)
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                {{ $item['label'] }}
                                @if ($item['is_cleared'])
                                    <span class="badge text-bg-success">{{ __('Cleared') }}</span>
                                @else
                                    <button type="button" class="btn btn-sm btn-outline-primary" wire:click="clearItem({{ $checklist->id }}, '{{ $item['code'] }}')">{{ __('Clear') }}</button>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div class="card">
                    <div class="card-header">{{ __('Final pay') }}</div>
                    <div class="card-body">
                        @if ($checklist->final_pay_released_at)
                            <p class="text-success mb-0">{{ __('Released on :date.', ['date' => $checklist->final_pay_released_at->format('d M Y')]) }}</p>
                        @else
                            <p class="text-body-secondary small">{{ __('Blocked until every checklist item above is cleared (AC-PPL-04-008).') }}</p>
                            <button type="button" class="btn btn-primary" wire:click="releaseFinalPay({{ $checklist->id }})">{{ __('Release final pay') }}</button>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">{{ __('Process exit') }}</div>
                    <div class="card-body">
                        <p class="text-body-secondary small">{{ __('Deactivates the linked user account and revokes all tokens immediately — independent of the checklist above (AC-PPL-04-007).') }}</p>
                        @if ($staff->status === 'exited')
                            <p class="text-success mb-0">{{ __('Exited on :date.', ['date' => $staff->exited_on?->format('d M Y')]) }}</p>
                        @else
                            <div class="row g-2">
                                <div class="col-6">
                                    <input type="date" class="form-control form-control-sm @error('exitedOn') is-invalid @enderror" wire:model="exitedOn">
                                </div>
                                <div class="col-6">
                                    <input type="text" class="form-control form-control-sm @error('exitReason') is-invalid @enderror" wire:model="exitReason" placeholder="{{ __('Reason') }}">
                                </div>
                            </div>
                            <button type="button" class="btn btn-danger btn-sm mt-3" wire:click="processExit" wire:confirm="{{ __('Process this exit? The account will be deactivated immediately.') }}">{{ __('Process exit') }}</button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
