<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="{{ route('notifications.log', $school) }}" class="btn btn-icon btn-outline-secondary btn-sm" wire:navigate>
            <i class="ri ri-arrow-left-line"></i>
        </a>
        <div>
            <h4 class="mb-1">{{ __('Notification budget') }}</h4>
            <p class="text-body-secondary mb-0">{{ __('This month\'s spend against each channel\'s cap.') }}</p>
        </div>
    </div>

    <div class="row g-3 mb-3">
        @foreach ($rows as $row)
            @php $budget = $row['budget']; @endphp
            <div class="col-md-6 col-lg-4" wire:key="budget-{{ $row['channel'] }}">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h6 class="mb-0">{{ \Illuminate\Support\Str::headline($row['channel']) }}</h6>
                            <button type="button" class="btn btn-icon btn-sm btn-outline-secondary" wire:click="openEditModal('{{ $row['channel'] }}')" title="{{ __('Edit budget') }}" aria-label="{{ __('Edit budget') }}">
                                <i class="icon-base ri ri-settings-3-line icon-22px"></i>
                            </button>
                        </div>
                        @if ($budget)
                            <p class="mb-1">
                                {{ number_format($budget->spent_minor / 100, 2) }}
                                @if ($budget->cap_minor !== null)
                                    / {{ number_format($budget->cap_minor / 100, 2) }}
                                @endif
                                {{ $budget->currency }}
                            </p>
                            @if ($budget->cap_minor !== null)
                                @php $percent = $budget->cap_minor > 0 ? min(100, (int) round($budget->spent_minor / $budget->cap_minor * 100)) : 0; @endphp
                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar {{ $percent >= $budget->warn_at_percent ? 'bg-warning' : '' }}" style="width: {{ $percent }}%"></div>
                                </div>
                                <p class="small text-body-secondary mt-1 mb-0">
                                    {{ $budget->is_hard_stop ? __('Hard stop when reached') : __('Warning only') }}
                                </p>
                            @else
                                <p class="small text-body-secondary mb-0">{{ __('Uncapped') }}</p>
                            @endif
                        @else
                            <p class="small text-body-secondary mb-0">{{ __('No budget set for this channel.') }}</p>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card">
        <div class="card-header"><h6 class="mb-0">{{ __('Spend by notification key, this month') }}</h6></div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Key') }}</th>
                        <th>{{ __('Sent') }}</th>
                        <th>{{ __('Total cost') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($spendByKey as $row)
                        <tr>
                            <td>{{ $row->notification_key }}</td>
                            <td>{{ $row->total_count }}</td>
                            <td>{{ number_format($row->total_minor / 100, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-center text-body-secondary py-4">{{ __('No costed sends this month.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($showEditModal)
        <div class="modal show d-block" tabindex="-1" style="background: rgba(0, 0, 0, .5);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form wire:submit="save">
                        <div class="modal-header">
                            <h5 class="modal-title">{{ __('Budget for :channel', ['channel' => \Illuminate\Support\Str::headline($editingChannel)]) }}</h5>
                            <button type="button" class="btn-close" wire:click="$set('showEditModal', false)" aria-label="{{ __('Close') }}"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="form-floating form-floating-outline">
                                        <input type="number" step="0.01" min="0" class="form-control @error('capMinor') is-invalid @enderror" id="capMinor" wire:model="capMinor" placeholder=" ">
                                        <label for="capMinor">{{ __('Monthly cap (blank = uncapped)') }}</label>
                                        @error('capMinor') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating form-floating-outline">
                                        <input type="text" class="form-control @error('currency') is-invalid @enderror" id="currency" wire:model="currency" placeholder=" ">
                                        <label for="currency">{{ __('Currency') }}</label>
                                        @error('currency') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating form-floating-outline">
                                        <input type="number" min="1" max="100" class="form-control @error('warnAtPercent') is-invalid @enderror" id="warnAtPercent" wire:model="warnAtPercent" placeholder=" ">
                                        <label for="warnAtPercent">{{ __('Warn at %') }}</label>
                                        @error('warnAtPercent') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                </div>
                                <div class="col-md-6 d-flex align-items-center">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" role="switch" id="isHardStop" wire:model="isHardStop">
                                        <label class="form-check-label" for="isHardStop">{{ __('Hard stop at cap') }}</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" wire:click="$set('showEditModal', false)">{{ __('Cancel') }}</button>
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">{{ __('Save') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
