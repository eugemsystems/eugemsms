<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="{{ route('dashboard') }}" class="btn btn-icon btn-outline-secondary btn-sm" wire:navigate>
            <i class="ri ri-arrow-left-line"></i>
        </a>
        <div class="flex-grow-1">
            <h4 class="mb-1">{{ __('Approval delegations') }}</h4>
            <p class="text-body-secondary mb-0">
                {{ $canManageOthers ? __('Every delegation in this school.') : __('Delegations you have created.') }}
            </p>
        </div>
        <button type="button" class="btn btn-primary" wire:click="openCreateModal">
            <i class="ri ri-add-line me-1"></i>{{ __('New delegation') }}
        </button>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Delegator') }}</th>
                        <th>{{ __('Delegate') }}</th>
                        <th>{{ __('Type') }}</th>
                        <th>{{ __('Window') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="text-end">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($delegations as $delegation)
                        <tr wire:key="delegation-{{ $delegation->id }}">
                            <td>{{ $delegation->delegator?->name }}</td>
                            <td>{{ $delegation->delegate?->name }}</td>
                            <td>{{ $delegation->approvable_type ? \Illuminate\Support\Str::headline($delegation->approvable_type) : __('All types') }}</td>
                            <td>{{ $delegation->starts_at->format('d M Y') }} – {{ $delegation->ends_at->format('d M Y') }}</td>
                            <td>
                                @if ($delegation->is_active)
                                    <span class="badge text-bg-success">{{ __('Active') }}</span>
                                @else
                                    <span class="badge text-bg-secondary">{{ __('Revoked') }}</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @if ($delegation->is_active)
                                    <button type="button" class="btn btn-icon btn-sm btn-outline-danger" wire:click="revoke({{ $delegation->id }})" wire:confirm="{{ __('Revoke this delegation?') }}" title="{{ __('Revoke') }}" aria-label="{{ __('Revoke') }}">
                                        <i class="icon-base ri ri-close-line icon-22px"></i>
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-body-secondary py-4">{{ __('No delegations yet.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($showCreateModal)
        <div class="modal show d-block" tabindex="-1" style="background: rgba(0, 0, 0, .5);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form wire:submit="create">
                        <div class="modal-header">
                            <h5 class="modal-title">{{ __('New delegation') }}</h5>
                            <button type="button" class="btn-close" wire:click="$set('showCreateModal', false)" aria-label="{{ __('Close') }}"></button>
                        </div>
                        <div class="modal-body">
                            <div class="form-floating form-floating-outline mb-3">
                                <select class="form-select @error('delegateId') is-invalid @enderror" id="delegateId" wire:model="delegateId">
                                    <option value="">{{ __('Select a user') }}</option>
                                    @foreach ($availableUsers as $availableUser)
                                        <option value="{{ $availableUser->id }}">{{ $availableUser->name }}</option>
                                    @endforeach
                                </select>
                                <label for="delegateId">{{ __('Delegate to') }}</label>
                                @error('delegateId') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="form-floating form-floating-outline mb-3">
                                <input type="text" class="form-control @error('approvableType') is-invalid @enderror" id="approvableType" wire:model="approvableType" placeholder=" ">
                                <label for="approvableType">{{ __('Approvable type (optional — blank means all types)') }}</label>
                                @error('approvableType') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="row g-3 mb-3">
                                <div class="col-6">
                                    <div class="form-floating form-floating-outline">
                                        <input type="datetime-local" class="form-control @error('startsAt') is-invalid @enderror" id="startsAt" wire:model="startsAt" placeholder=" ">
                                        <label for="startsAt">{{ __('Starts') }}</label>
                                        @error('startsAt') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="form-floating form-floating-outline">
                                        <input type="datetime-local" class="form-control @error('endsAt') is-invalid @enderror" id="endsAt" wire:model="endsAt" placeholder=" ">
                                        <label for="endsAt">{{ __('Ends') }}</label>
                                        @error('endsAt') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                </div>
                            </div>
                            <div class="form-floating form-floating-outline">
                                <input type="text" class="form-control @error('reason') is-invalid @enderror" id="reason" wire:model="reason" placeholder=" ">
                                <label for="reason">{{ __('Reason (optional)') }}</label>
                                @error('reason') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" wire:click="$set('showCreateModal', false)">{{ __('Cancel') }}</button>
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">{{ __('Create delegation') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
