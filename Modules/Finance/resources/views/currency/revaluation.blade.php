<div>
    <div class="mb-4">
        <h4 class="mb-1">{{ __('FX revaluation') }}</h4>
        <p class="text-body-secondary mb-0">{{ __('Restates every revaluable foreign-currency balance to the closing rate — mandatory before a period can lock.') }}</p>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form wire:submit="run" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <div class="form-floating form-floating-outline">
                        <input type="date" class="form-control @error('revaluationDate') is-invalid @enderror" id="revaluationDate" wire:model="revaluationDate">
                        <label for="revaluationDate">{{ __('Revaluation date') }}</label>
                        @error('revaluationDate') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:confirm="{{ __('Run the FX revaluation for this date?') }}">
                        {{ __('Run revaluation') }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h6 class="mb-0">{{ __('History') }}</h6></div>
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead>
                    <tr>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('Term') }}</th>
                        <th class="text-end">{{ __('Gain') }}</th>
                        <th class="text-end">{{ __('Loss') }}</th>
                        <th class="text-end">{{ __('Net') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($revaluations as $revaluation)
                        <tr wire:key="revaluation-{{ $revaluation->id }}">
                            <td>{{ $revaluation->revaluation_date->format('d M Y') }}</td>
                            <td>{{ $revaluation->term?->name }}</td>
                            <td class="text-end">{{ number_format($revaluation->gain_minor / 100, 2) }}</td>
                            <td class="text-end">{{ number_format($revaluation->loss_minor / 100, 2) }}</td>
                            <td class="text-end">{{ number_format($revaluation->netMinor() / 100, 2) }}</td>
                            <td>
                                <span class="badge {{ match ($revaluation->status) { 'posted' => 'text-bg-success', 'reversed' => 'text-bg-secondary', default => 'text-bg-warning' } }}">
                                    {{ \Illuminate\Support\Str::headline($revaluation->status) }}
                                </span>
                            </td>
                            <td class="text-end">
                                @if ($revaluation->status === 'posted' && $revaluation->journal_id !== null)
                                    <button type="button" class="btn btn-sm btn-outline-danger" wire:click="openReverse({{ $revaluation->id }})">{{ __('Reverse') }}</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-body-secondary py-4">{{ __('No revaluation has been run yet.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($reversingRevaluationId !== null)
        <div class="modal show d-block" tabindex="-1" style="background: rgba(0, 0, 0, .5);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form wire:submit="reverse">
                        <div class="modal-header">
                            <h5 class="modal-title">{{ __('Reverse revaluation') }}</h5>
                            <button type="button" class="btn-close" wire:click="$set('reversingRevaluationId', null)" aria-label="{{ __('Close') }}"></button>
                        </div>
                        <div class="modal-body">
                            <label class="form-label" for="reverseReason">{{ __('Reason') }}</label>
                            <textarea class="form-control @error('reverseReason') is-invalid @enderror" id="reverseReason" wire:model="reverseReason" rows="3" placeholder="{{ __('At least 15 characters.') }}"></textarea>
                            @error('reverseReason') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" wire:click="$set('reversingRevaluationId', null)">{{ __('Cancel') }}</button>
                            <button type="submit" class="btn btn-danger" wire:loading.attr="disabled">{{ __('Reverse') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
