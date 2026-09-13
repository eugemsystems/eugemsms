<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <div class="flex-grow-1">
            <h4 class="mb-1">{{ __('Reminder schedules') }}</h4>
            <p class="text-body-secondary mb-0">{{ __('Each rung fires at most once per invoice. Suppressed while an active, non-breached payment plan covers the learner.') }}</p>
        </div>
        <button type="button" class="btn btn-primary" wire:click="openCreateModal">
            <i class="ri ri-add-line me-1"></i>{{ __('New schedule') }}
        </button>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead>
                    <tr>
                        <th>{{ __('Name') }}</th>
                        <th>{{ __('Days after due') }}</th>
                        <th>{{ __('Channels') }}</th>
                        <th>{{ __('Audience') }}</th>
                        <th>{{ __('Active') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($schedules as $schedule)
                        <tr wire:key="schedule-{{ $schedule->id }}">
                            <td>{{ $schedule->name }}</td>
                            <td>{{ $schedule->days_after_due }}</td>
                            <td>{{ collect($schedule->channels)->map(fn ($c) => \Illuminate\Support\Str::upper($c))->join(', ') }}</td>
                            <td>{{ \Illuminate\Support\Str::headline($schedule->audience) }}</td>
                            <td>
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" role="switch" @checked($schedule->is_active) wire:click="toggleActive({{ $schedule->id }}, {{ $schedule->is_active ? 'false' : 'true' }})">
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-body-secondary py-4">{{ __('No reminder schedules defined yet.') }}</td>
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
                            <h5 class="modal-title">{{ __('New reminder schedule') }}</h5>
                            <button type="button" class="btn-close" wire:click="$set('showCreateModal', false)" aria-label="{{ __('Close') }}"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <div class="form-floating form-floating-outline">
                                    <input type="text" class="form-control @error('name') is-invalid @enderror" id="rs-name" wire:model="name" placeholder=" ">
                                    <label for="rs-name">{{ __('Name') }}</label>
                                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                            <div class="row g-3 mb-3">
                                <div class="col-6">
                                    <div class="form-floating form-floating-outline">
                                        <input type="number" min="0" class="form-control" id="rs-days" wire:model="daysAfterDue" placeholder=" ">
                                        <label for="rs-days">{{ __('Days after due') }}</label>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="form-floating form-floating-outline">
                                        <input type="number" min="0" class="form-control" id="rs-min" wire:model="minimumBalanceMinor" placeholder=" ">
                                        <label for="rs-min">{{ __('Minimum balance (minor units)') }}</label>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">{{ __('Channels') }}</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="sms" wire:model="channels" id="rs-sms">
                                    <label class="form-check-label" for="rs-sms">{{ __('SMS') }}</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="email" wire:model="channels" id="rs-email">
                                    <label class="form-check-label" for="rs-email">{{ __('Email') }}</label>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="form-floating form-floating-outline">
                                    <select class="form-select" id="rs-audience" wire:model="audience">
                                        <option value="fee_responsible">{{ __('Fee-responsible guardian') }}</option>
                                        <option value="all_guardians">{{ __('All guardians') }}</option>
                                    </select>
                                    <label for="rs-audience">{{ __('Audience') }}</label>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" wire:click="$set('showCreateModal', false)">{{ __('Cancel') }}</button>
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">{{ __('Create schedule') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
