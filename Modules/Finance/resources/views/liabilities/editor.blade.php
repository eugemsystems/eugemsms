<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="{{ route('finance.accounts.learner-account', ['school' => $school, 'student' => $student]) }}" class="btn btn-icon btn-outline-secondary btn-sm" wire:navigate>
            <i class="ri ri-arrow-left-line"></i>
        </a>
        <div>
            <h4 class="mb-1">{{ __('Fee liabilities — :name', ['name' => $student->fullName()]) }}</h4>
            <p class="text-body-secondary mb-0">{{ __('Who pays what share of this learner\'s fees.') }}</p>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header"><h6 class="mb-0">{{ __('Active rules') }}</h6></div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('Guardian') }}</th>
                                <th>{{ __('Component') }}</th>
                                <th>{{ __('Share') }}</th>
                                <th>{{ __('Priority') }}</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($liabilities as $liability)
                                <tr wire:key="liability-{{ $liability->id }}">
                                    <td>{{ $liability->guardian->displayName() }}</td>
                                    <td>{{ $liability->component?->name ?? __('All other components') }}</td>
                                    <td>
                                        @if ($liability->share_type === 'percentage')
                                            {{ $liability->share_percent }}%
                                        @elseif ($liability->share_type === 'fixed')
                                            {{ number_format($liability->share_amount_minor / 100, 2) }} {{ $liability->currency }}
                                        @else
                                            {{ __('Full component') }}
                                        @endif
                                    </td>
                                    <td>{{ $liability->priority }}</td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-icon btn-sm btn-outline-danger" wire:click="deactivate({{ $liability->id }})" wire:confirm="{{ __('Deactivate this rule?') }}">
                                            <i class="icon-base ri ri-forbid-line icon-22px"></i>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-body-secondary py-4">{{ __('No liability rules yet — the full balance falls to the primary fee-responsible guardian.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($percentTotals !== [])
                    <div class="card-body border-top small">
                        <h6 class="small text-uppercase text-body-secondary">{{ __('Percentage totals') }}</h6>
                        @foreach ($percentTotals as $componentKey => $total)
                            <div>
                                {{ $componentKey === 'all_other' ? __('All other components') : \Illuminate\Support\Str::headline((string) $componentKey) }}:
                                <span class="{{ (float) $total > 100 ? 'text-danger fw-semibold' : '' }}">{{ $total }}%</span>
                                @if ((float) $total < 100)
                                    <span class="text-body-secondary">{{ __('— residue falls to the primary fee-responsible guardian') }}</span>
                                @elseif ((float) $total > 100)
                                    <span class="text-danger">{{ __('— exceeds 100%') }}</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card">
                <div class="card-header"><h6 class="mb-0">{{ __('Add a rule') }}</h6></div>
                <div class="card-body">
                    <form wire:submit="add">
                        <div class="mb-3">
                            <div class="form-floating form-floating-outline">
                                <select class="form-select @error('guardianId') is-invalid @enderror" id="guardianId" wire:model="guardianId">
                                    <option value="">{{ __('Select a guardian') }}</option>
                                    @foreach ($guardians as $guardian)
                                        <option value="{{ $guardian->id }}">{{ $guardian->displayName() }}</option>
                                    @endforeach
                                </select>
                                <label for="guardianId">{{ __('Guardian') }}</label>
                                @error('guardianId') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="form-floating form-floating-outline">
                                <select class="form-select" id="componentId" wire:model="componentId">
                                    <option value="">{{ __('All other components') }}</option>
                                    @foreach ($components as $component)
                                        <option value="{{ $component->id }}">{{ $component->code }} — {{ $component->name }}</option>
                                    @endforeach
                                </select>
                                <label for="componentId">{{ __('Component (optional)') }}</label>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="form-floating form-floating-outline">
                                <select class="form-select" id="shareType" wire:model.live="shareType">
                                    <option value="percentage">{{ __('Percentage') }}</option>
                                    <option value="fixed">{{ __('Fixed amount') }}</option>
                                    <option value="full_component">{{ __('Full component') }}</option>
                                </select>
                                <label for="shareType">{{ __('Share type') }}</label>
                            </div>
                        </div>
                        @if ($shareType === 'percentage')
                            <div class="mb-3">
                                <div class="form-floating form-floating-outline">
                                    <input type="number" step="0.01" min="0.01" max="100" class="form-control @error('sharePercent') is-invalid @enderror" id="sharePercent" wire:model="sharePercent" placeholder=" ">
                                    <label for="sharePercent">{{ __('Share percent') }}</label>
                                    @error('sharePercent') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                        @elseif ($shareType === 'fixed')
                            <div class="row g-3 mb-3">
                                <div class="col-8">
                                    <div class="form-floating form-floating-outline">
                                        <input type="text" inputmode="decimal" class="form-control @error('shareAmount') is-invalid @enderror" id="shareAmount" wire:model="shareAmount" placeholder=" ">
                                        <label for="shareAmount">{{ __('Fixed amount') }}</label>
                                        @error('shareAmount') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="form-floating form-floating-outline">
                                        <select class="form-select" id="currency" wire:model="currency">
                                            <option value="USD">USD</option>
                                            <option value="ZWG">ZWG</option>
                                        </select>
                                        <label for="currency">{{ __('Currency') }}</label>
                                    </div>
                                </div>
                            </div>
                        @endif
                        <div class="mb-3">
                            <div class="form-floating form-floating-outline">
                                <input type="number" min="1" class="form-control" id="priority" wire:model="priority" placeholder=" ">
                                <label for="priority">{{ __('Priority (lower resolves first)') }}</label>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100" wire:loading.attr="disabled">{{ __('Add rule') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
