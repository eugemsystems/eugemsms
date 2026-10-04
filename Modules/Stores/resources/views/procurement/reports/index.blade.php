<div>
    <h4 class="mb-1">{{ __('Procurement reports') }}</h4>

    <ul class="nav nav-tabs mb-3">
        <li class="nav-item"><button type="button" class="nav-link {{ $tab === 'aging' ? 'active' : '' }}" wire:click="$set('tab', 'aging')">{{ __('Aging') }}</button></li>
        <li class="nav-item"><button type="button" class="nav-link {{ $tab === 'vat' ? 'active' : '' }}" wire:click="$set('tab', 'vat')">🇿🇼 {{ __('Unclaimable VAT') }}</button></li>
        <li class="nav-item"><button type="button" class="nav-link {{ $tab === 'withholding' ? 'active' : '' }}" wire:click="$set('tab', 'withholding')">🇿🇼 {{ __('Withholding') }}</button></li>
        <li class="nav-item"><button type="button" class="nav-link {{ $tab === 'spend' ? 'active' : '' }}" wire:click="$set('tab', 'spend')">{{ __('Spend') }}</button></li>
    </ul>

    @if ($tab === 'aging')
        <select class="form-select mb-3" style="max-width: 24rem" wire:model.live="agingSupplierId">
            <option value="">{{ __('Select a supplier') }}</option>
            @foreach ($suppliers as $supplier)
                <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
            @endforeach
        </select>
        <div class="card"><div class="table-responsive"><table class="table table-sm mb-0">
            <thead><tr><th>{{ __('Currency') }}</th><th>{{ __('Current') }}</th><th>1-30</th><th>31-60</th><th>61-90</th><th>90+</th></tr></thead>
            <tbody>
                @forelse ($aging as $row)
                    <tr><td>{{ $row['currency'] }}</td><td>{{ number_format($row['current'] / 100, 2) }}</td><td>{{ number_format($row['days_1_30'] / 100, 2) }}</td><td>{{ number_format($row['days_31_60'] / 100, 2) }}</td><td>{{ number_format($row['days_61_90'] / 100, 2) }}</td><td>{{ number_format($row['over_90'] / 100, 2) }}</td></tr>
                @empty
                    <tr><td colspan="6" class="text-center text-body-secondary py-3">{{ __('Select a supplier.') }}</td></tr>
                @endforelse
            </tbody>
        </table></div></div>
    @elseif ($tab === 'vat')
        <div class="card"><div class="table-responsive"><table class="table table-sm mb-0">
            <thead><tr><th>{{ __('Supplier') }}</th><th>{{ __('Invoice') }}</th><th>{{ __('Date') }}</th><th>{{ __('Total') }}</th></tr></thead>
            <tbody>
                @forelse ($unclaimableVat as $invoice)
                    <tr><td>{{ $invoice->supplier->name }}</td><td>{{ $invoice->invoice_number }}</td><td>{{ $invoice->invoice_date->format('Y-m-d') }}</td><td>{{ number_format($invoice->total_minor / 100, 2) }}</td></tr>
                @empty
                    <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('No exposure.') }}</td></tr>
                @endforelse
            </tbody>
        </table></div></div>
    @elseif ($tab === 'withholding')
        <div class="card"><div class="table-responsive"><table class="table table-sm mb-0">
            <thead><tr><th>{{ __('Supplier') }}</th><th>{{ __('Invoice') }}</th><th>{{ __('Date') }}</th><th>{{ __('Withheld') }}</th></tr></thead>
            <tbody>
                @forelse ($withholding as $invoice)
                    <tr><td>{{ $invoice->supplier->name }}</td><td>{{ $invoice->invoice_number }}</td><td>{{ $invoice->invoice_date->format('Y-m-d') }}</td><td>{{ number_format($invoice->withholding_minor / 100, 2) }}</td></tr>
                @empty
                    <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('No withholding applied.') }}</td></tr>
                @endforelse
            </tbody>
        </table></div></div>
    @else
        <div class="card"><div class="table-responsive"><table class="table table-sm mb-0">
            <thead><tr><th>{{ __('Supplier') }}</th><th>{{ __('Invoices') }}</th><th>{{ __('Total spend') }}</th></tr></thead>
            <tbody>
                @forelse ($spend as $row)
                    <tr><td>{{ $row->supplier_name }}</td><td>{{ $row->invoice_count }}</td><td>{{ number_format($row->total_minor / 100, 2) }}</td></tr>
                @empty
                    <tr><td colspan="3" class="text-center text-body-secondary py-3">{{ __('No spend recorded.') }}</td></tr>
                @endforelse
            </tbody>
        </table></div></div>
    @endif
</div>
