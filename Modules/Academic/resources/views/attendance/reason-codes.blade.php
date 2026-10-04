<div>
    <h4 class="mb-1">{{ __('Attendance reason codes') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('The configuration every attendance screen depends on.') }}</p>

    <div class="row g-4">
        <div class="col-md-7">
            <div class="card">
                <div class="card-header">{{ __('Reason codes') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Code') }}</th><th>{{ __('Name') }}</th><th>{{ __('Counts as present') }}</th><th>{{ __('Suppresses notification') }}</th><th>{{ __('Welfare flag') }}</th></tr></thead>
                        <tbody>
                            @forelse ($reasonCodes as $reasonCode)
                                <tr wire:key="reason-{{ $reasonCode->id }}">
                                    <td>{{ $reasonCode->code }}</td>
                                    <td>{{ $reasonCode->name }}</td>
                                    <td>{{ $reasonCode->counts_as_present ? __('Yes') : __('No') }}</td>
                                    <td>{{ $reasonCode->suppresses_notification ? __('Yes') : __('No') }}</td>
                                    <td>{{ $reasonCode->triggers_welfare_flag ? __('Yes') : __('No') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-body-secondary py-4">{{ __('No reason codes yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-5">
            <div class="card">
                <div class="card-header">{{ __('New reason code') }}</div>
                <div class="card-body">
                    <form wire:submit="create">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <input type="text" class="form-control @error('code') is-invalid @enderror" wire:model="code" placeholder=" ">
                                    <label>{{ __('Code') }}</label>
                                    @error('code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <input type="text" class="form-control @error('name') is-invalid @enderror" wire:model="name" placeholder=" ">
                                    <label>{{ __('Name') }}</label>
                                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" wire:model="countsAsPresent">
                                    <label class="form-check-label">{{ __('Counts as present') }}</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" wire:model="countsTowardPercentage">
                                    <label class="form-check-label">{{ __('Counts toward percentage') }}</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" wire:model="isAuthorised">
                                    <label class="form-check-label">{{ __('Authorised') }}</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" wire:model="requiresDocument">
                                    <label class="form-check-label">{{ __('Requires document') }}</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" wire:model="suppressesNotification">
                                    <label class="form-check-label">{{ __('Suppresses notification') }}</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" wire:model="triggersWelfareFlag">
                                    <label class="form-check-label">{{ __('Triggers welfare flag') }}</label>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary mt-3" wire:loading.attr="disabled">{{ __('Create reason code') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
