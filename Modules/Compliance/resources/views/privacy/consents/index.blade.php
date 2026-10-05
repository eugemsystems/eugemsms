<div>
    <h4 class="mb-1">{{ __('Consent register') }} 🇿🇼</h4>
    <p class="text-body-secondary small">{{ __('Append-only. Withdrawing a consent sets state on the same record — it is never deleted or replaced.') }}</p>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Type') }}</th><th>{{ __('Subject') }}</th><th>{{ __('Granted') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($consents as $consent)
                                <tr wire:key="consent-{{ $consent->id }}">
                                    <td>{{ $consent->consentType?->name }}</td>
                                    <td>{{ $consent->subject_type }} #{{ $consent->subject_id }}</td>
                                    <td>{{ $consent->granted ? __('Yes') : __('No') }}</td>
                                    <td>
                                        @if ($consent->withdrawn_at)
                                            <span class="badge bg-secondary">{{ __('Withdrawn') }}</span>
                                        @else
                                            <span class="badge bg-success">{{ __('Active') }}</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        @if (! $consent->withdrawn_at && $consent->consentType?->is_withdrawable)
                                            <button type="button" class="btn btn-outline-danger btn-sm" wire:click="startWithdraw({{ $consent->id }})">
                                                {{ __('Withdraw') }}
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                                @if ($withdrawingConsentId === $consent->id)
                                    <tr>
                                        <td colspan="5">
                                            <div class="input-group input-group-sm">
                                                <input type="text" class="form-control" wire:model="withdrawalReason" placeholder="{{ __('Withdrawal reason') }}">
                                                <button type="button" class="btn btn-danger" wire:click="withdraw">{{ __('Confirm withdrawal') }}</button>
                                            </div>
                                        </td>
                                    </tr>
                                @endif
                            @empty
                                <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No consents recorded yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">{{ __('Record consent') }}</div>
                <div class="card-body">
                    <select class="form-select mb-2" wire:model="consentTypeId">
                        <option value="0">{{ __('Select consent type') }}</option>
                        @foreach ($consentTypes as $type)
                            <option value="{{ $type->id }}">{{ $type->name }}</option>
                        @endforeach
                    </select>
                    <select class="form-select mb-2" wire:model="subjectType">
                        <option value="student">{{ __('Student') }}</option>
                        <option value="guardian">{{ __('Guardian') }}</option>
                        <option value="staff">{{ __('Staff') }}</option>
                    </select>
                    <input type="number" class="form-control mb-2" wire:model="subjectId" placeholder="{{ __('Subject id') }}">
                    <select class="form-select mb-2" wire:model="grantedByType">
                        <option value="guardian">{{ __('Guardian') }}</option>
                        <option value="self">{{ __('Self') }}</option>
                    </select>
                    <input type="number" class="form-control mb-2" wire:model="grantedById" placeholder="{{ __('Granted by id') }}">
                    <select class="form-select mb-2" wire:model="method">
                        <option value="portal">{{ __('Portal') }}</option>
                        <option value="paper_form">{{ __('Paper form') }}</option>
                        <option value="verbal_witnessed">{{ __('Verbal, witnessed') }}</option>
                    </select>
                    <div class="form-check mb-2">
                        <input type="checkbox" class="form-check-input" wire:model="granted" id="consentGranted">
                        <label class="form-check-label small" for="consentGranted">{{ __('Granted') }}</label>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm" wire:click="record">{{ __('Record') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
