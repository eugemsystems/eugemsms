<div>
    <h4 class="mb-1">🇿🇼 {{ __('Tax clearance monitor') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Validity is assessed on the invoice date, not the payment date.') }}</p>

    @if ($expiring->isNotEmpty())
        <div class="alert alert-warning">{{ __(':n certificate(s) crossing an alert window today.', ['n' => $expiring->count()]) }}</div>
    @endif

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Supplier') }}</th><th>{{ __('Certificate') }}</th><th>{{ __('Issued') }}</th><th>{{ __('Expires') }}</th><th>{{ __('Status') }}</th></tr></thead>
                        <tbody>
                            @forelse ($clearances as $clearance)
                                <tr wire:key="clearance-{{ $clearance->id }}">
                                    <td>{{ $clearance->supplier->name }}</td>
                                    <td>{{ $clearance->certificate_number }}</td>
                                    <td>{{ $clearance->issued_on->format('Y-m-d') }}</td>
                                    <td>{{ $clearance->expires_on->format('Y-m-d') }}</td>
                                    <td><span class="badge text-bg-{{ $clearance->status === 'valid' ? 'success' : 'secondary' }}">{{ $clearance->status }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('None recorded.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">{{ __('Record a certificate') }}</div>
                <div class="card-body">
                    <select class="form-select mb-2" wire:model="supplierId">
                        <option value="">{{ __('Supplier') }}</option>
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                        @endforeach
                    </select>
                    <input type="text" class="form-control mb-2" wire:model="certificateNumber" placeholder="{{ __('ITF263 certificate number') }}">
                    <input type="date" class="form-control mb-2" wire:model="issuedOn">
                    <input type="date" class="form-control mb-2" wire:model="expiresOn">
                    <button type="button" class="btn btn-primary btn-sm" wire:click="record">{{ __('Record') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
