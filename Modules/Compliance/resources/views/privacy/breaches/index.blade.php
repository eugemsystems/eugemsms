<div>
    <h4 class="mb-1">{{ __('Data breach register') }} 🇿🇼</h4>
    <p class="text-body-secondary small">{{ __('A breach involving minors\' data escalates to the head and the safeguarding lead automatically on recording.') }}</p>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Type') }}</th><th>{{ __('Severity') }}</th><th>{{ __('Minors') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($breaches as $breach)
                                <tr wire:key="breach-{{ $breach->id }}">
                                    <td>{{ str_replace('_', ' ', $breach->breach_type) }}</td>
                                    <td><span class="badge {{ match ($breach->severity) { 'critical' => 'bg-danger', 'high' => 'bg-danger', 'medium' => 'bg-warning text-dark', default => 'bg-light text-dark border' } }}">{{ $breach->severity }}</span></td>
                                    <td>{{ $breach->includes_minors ? __('Yes ⭐') : __('No') }}</td>
                                    <td><span class="badge bg-light text-dark border">{{ $breach->status }}</span></td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="startDecision({{ $breach->id }})">{{ __('Notification decision') }}</button>
                                    </td>
                                </tr>
                                @if ($decidingBreachId === $breach->id)
                                    <tr>
                                        <td colspan="5">
                                            <div class="row g-2 align-items-center">
                                                <div class="col-auto form-check">
                                                    <input type="checkbox" class="form-check-input" wire:model="authorityNotified" id="authNotified">
                                                    <label class="form-check-label small" for="authNotified">{{ __('Authority notified') }}</label>
                                                </div>
                                                <div class="col-auto form-check">
                                                    <input type="checkbox" class="form-check-input" wire:model="subjectsNotified" id="subjNotified">
                                                    <label class="form-check-label small" for="subjNotified">{{ __('Subjects notified') }}</label>
                                                </div>
                                                <div class="col-auto">
                                                    <button type="button" class="btn btn-primary btn-sm" wire:click="recordDecision">{{ __('Save decision') }}</button>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @endif
                            @empty
                                <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No breaches recorded.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">{{ __('Record breach') }}</div>
                <div class="card-body">
                    <select class="form-select mb-2" wire:model="breachType">
                        <option value="unauthorised_access">{{ __('Unauthorised access') }}</option>
                        <option value="disclosure">{{ __('Disclosure') }}</option>
                        <option value="loss">{{ __('Loss') }}</option>
                        <option value="theft">{{ __('Theft') }}</option>
                        <option value="system_compromise">{{ __('System compromise') }}</option>
                        <option value="misdirected_communication">{{ __('Misdirected communication') }}</option>
                    </select>
                    <textarea class="form-control mb-2" wire:model="description" placeholder="{{ __('Description') }}"></textarea>
                    <input type="text" class="form-control mb-2" wire:model="dataCategoriesText" placeholder="{{ __('Data categories, comma-separated') }}">
                    <select class="form-select mb-2" wire:model="severity">
                        <option value="low">{{ __('Low') }}</option>
                        <option value="medium">{{ __('Medium') }}</option>
                        <option value="high">{{ __('High') }}</option>
                        <option value="critical">{{ __('Critical') }}</option>
                    </select>
                    <input type="number" class="form-control mb-2" wire:model="recordsAffected" placeholder="{{ __('Records affected (optional)') }}">
                    <input type="number" class="form-control mb-2" wire:model="subjectsAffected" placeholder="{{ __('Subjects affected (optional)') }}">
                    <div class="form-check mb-2">
                        <input type="checkbox" class="form-check-input" wire:model="includesMinors" id="breachMinors">
                        <label class="form-check-label small" for="breachMinors">{{ __('Includes minors\' data — escalates automatically') }}</label>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm" wire:click="record">{{ __('Record breach') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
