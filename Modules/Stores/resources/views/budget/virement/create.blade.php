<div>
    <h4 class="mb-1">{{ __('Virement') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Requires approval; takes effect from the recorded date. A locked line refuses virement in either direction.') }}</p>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">{{ __('Request a transfer') }}</div>
                <div class="card-body">
                    <input type="number" class="form-control mb-2" wire:model="budgetId" placeholder="{{ __('Budget ID') }}">
                    <select class="form-select mb-2" wire:model="fromLineId">
                        <option value="">{{ __('From line') }}</option>
                        @foreach ($budgetLines as $line)
                            <option value="{{ $line->id }}">#{{ $line->id }} — {{ __('available') }} {{ number_format($line->available_minor / 100, 2) }}</option>
                        @endforeach
                    </select>
                    <select class="form-select mb-2" wire:model="toLineId">
                        <option value="">{{ __('To line') }}</option>
                        @foreach ($budgetLines as $line)
                            <option value="{{ $line->id }}">#{{ $line->id }}</option>
                        @endforeach
                    </select>
                    <input type="number" class="form-control mb-2" wire:model="amountMinor" placeholder="{{ __('Amount (minor units)') }}">
                    <input type="date" class="form-control mb-2" wire:model="effectiveFrom">
                    <textarea class="form-control mb-2" wire:model="reason" placeholder="{{ __('Reason') }}"></textarea>
                    <button type="button" class="btn btn-primary btn-sm" wire:click="request">{{ __('Request') }}</button>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">{{ __('Pending approval') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('From') }}</th><th>{{ __('To') }}</th><th>{{ __('Amount') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($pending as $virement)
                                <tr wire:key="vir-{{ $virement->id }}">
                                    <td>#{{ $virement->from_line_id }}</td>
                                    <td>#{{ $virement->to_line_id }}</td>
                                    <td>{{ number_format($virement->amount_minor / 100, 2) }}</td>
                                    <td><button type="button" class="btn btn-sm btn-primary" wire:click="approve({{ $virement->id }})">{{ __('Approve') }}</button></td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('None pending.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
