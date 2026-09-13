<div>
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="mb-1">{{ __('Till sessions') }}</h4>
            <p class="text-body-secondary mb-0">{{ __('Session history with variance, by till and cashier.') }}</p>
        </div>
        <a href="{{ route('finance.till.banking', $school) }}" class="btn btn-outline-secondary btn-sm" wire:navigate>{{ __('Daily banking') }}</a>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="form-floating form-floating-outline">
                        <select class="form-select" wire:model.live="tillId">
                            <option value="">{{ __('All tills') }}</option>
                            @foreach ($tills as $till)
                                <option value="{{ $till->id }}">{{ $till->code }} — {{ $till->name }}</option>
                            @endforeach
                        </select>
                        <label>{{ __('Till') }}</label>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-floating form-floating-outline">
                        <select class="form-select" wire:model.live="cashierId">
                            <option value="">{{ __('All cashiers') }}</option>
                            @foreach ($cashiers as $cashier)
                                <option value="{{ $cashier->id }}">{{ $cashier->name }}</option>
                            @endforeach
                        </select>
                        <label>{{ __('Cashier') }}</label>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-floating form-floating-outline">
                        <select class="form-select" wire:model.live="status">
                            <option value="">{{ __('Any status') }}</option>
                            <option value="open">{{ __('Open') }}</option>
                            <option value="declaring">{{ __('Declaring') }}</option>
                            <option value="closed">{{ __('Closed') }}</option>
                            <option value="reconciled">{{ __('Reconciled') }}</option>
                            <option value="disputed">{{ __('Disputed') }}</option>
                        </select>
                        <label>{{ __('Status') }}</label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Session') }}</th>
                        <th>{{ __('Till') }}</th>
                        <th>{{ __('Cashier') }}</th>
                        <th>{{ __('Opened') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Receipts') }}</th>
                        <th>{{ __('Variance') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($sessions as $session)
                        <tr wire:key="session-{{ $session->id }}">
                            <td>{{ $session->session_number }}</td>
                            <td>{{ $session->till->code }}</td>
                            <td>{{ $session->cashier->name }}</td>
                            <td>{{ $session->opened_at->format('d M Y H:i') }}</td>
                            <td><span class="badge bg-label-secondary">{{ ucfirst($session->status) }}</span></td>
                            <td>{{ $session->receipt_count }}</td>
                            <td>
                                @if ($session->variance !== null)
                                    @foreach ($session->variance as $currency => $amount)
                                        <span class="badge {{ $amount === 0 ? 'bg-label-success' : 'bg-label-danger' }}">
                                            {{ $currency }} {{ number_format($amount / 100, 2) }}
                                        </span>
                                    @endforeach
                                @else
                                    <span class="text-body-secondary">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-body-secondary py-4">{{ __('No till sessions match these filters.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-body">
            {{ $sessions->links() }}
        </div>
    </div>
</div>
