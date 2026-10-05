<div>
    <h4 class="mb-1">{{ __('Keys and cards') }}</h4>

    <button type="button" class="btn btn-outline-warning btn-sm mb-3" wire:click="checkOverdue">{{ __('Check overdue keys') }}</button>
    @if ($overdueChecked)
        <div class="alert {{ $overdueCount > 0 ? 'alert-warning' : 'alert-success' }} py-2">{{ __('Overdue keys:') }} {{ $overdueCount }}</div>
    @endif

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card mb-3">
                <div class="card-header">{{ __('Register') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Identifier') }}</th><th>{{ __('Type') }}</th><th>{{ __('Master') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($keys as $key)
                                <tr wire:key="key-{{ $key->id }}">
                                    <td>{{ $key->identifier }}</td>
                                    <td>{{ $key->item_type }}</td>
                                    <td>{{ $key->is_master ? __('Yes') : __('No') }}</td>
                                    <td>{{ $key->status }}</td>
                                    <td>
                                        @if ($key->status === 'available')
                                            <button type="button" class="btn btn-outline-primary btn-sm" wire:click="selectForIssue({{ $key->id }})">{{ __('Issue') }}</button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No keys registered.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card">
                <div class="card-header">{{ __('Issued') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Key') }}</th><th>{{ __('To') }}</th><th>{{ __('Due back') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($issues as $issue)
                                <tr wire:key="issue-{{ $issue->id }}">
                                    <td>{{ $issue->key->identifier }}</td>
                                    <td>{{ $issue->issuedToStaff?->fullName() ?? '—' }}</td>
                                    <td>{{ $issue->due_back_on?->toDateString() ?? '—' }}</td>
                                    <td><span class="{{ $issue->status === 'overdue' ? 'badge bg-danger' : '' }}">{{ $issue->status }}</span></td>
                                    <td><button type="button" class="btn btn-outline-secondary btn-sm" wire:click="returnKey({{ $issue->id }})">{{ __('Return') }}</button></td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No keys issued.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card mb-3">
                <div class="card-header">{{ __('New key/card') }}</div>
                <div class="card-body">
                    <input type="text" class="form-control mb-2" wire:model="identifier" placeholder="{{ __('Identifier') }}">
                    <select class="form-select mb-2" wire:model="itemType">
                        @foreach (['key', 'access_card', 'fob', 'padlock'] as $type)
                            <option value="{{ $type }}">{{ $type }}</option>
                        @endforeach
                    </select>
                    <input type="text" class="form-control mb-2" wire:model="description" placeholder="{{ __('Description') }}">
                    <input type="text" class="form-control mb-2" wire:model="opensLocation" placeholder="{{ __('Opens location (optional)') }}">
                    <div class="form-check mb-2">
                        <input type="checkbox" class="form-check-input" id="isMaster" wire:model="isMaster">
                        <label class="form-check-label" for="isMaster">{{ __('Master key (requires higher authority to issue)') }}</label>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm" wire:click="createKey">{{ __('Register') }}</button>
                </div>
            </div>

            @if ($issuingKeyId !== null)
                <div class="card">
                    <div class="card-header">{{ __('Issue key #') }}{{ $issuingKeyId }}</div>
                    <div class="card-body">
                        <select class="form-select mb-2" wire:model="issuedToStaffId">
                            <option value="">{{ __('Issued to staff') }}</option>
                            @foreach ($staff as $member)
                                <option value="{{ $member->id }}">{{ $member->fullName() }}</option>
                            @endforeach
                        </select>
                        <input type="date" class="form-control mb-2" wire:model="dueBackOn" placeholder="{{ __('Due back (optional)') }}">
                        <div class="form-check mb-2">
                            <input type="checkbox" class="form-check-input" id="higherAuthorityConfirmed" wire:model="higherAuthorityConfirmed">
                            <label class="form-check-label" for="higherAuthorityConfirmed">{{ __('Higher authority confirmed (master keys only)') }}</label>
                        </div>
                        <button type="button" class="btn btn-primary btn-sm" wire:click="issue">{{ __('Issue') }}</button>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
