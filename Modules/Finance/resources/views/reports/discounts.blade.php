<div>
    <div class="mb-4">
        <h4 class="mb-0">{{ __('Cost of generosity') }}</h4>
        <p class="text-body-secondary small mb-0">{{ __('Gross billed, discount granted and net billed — separately, per scheme. The headline income figure is never reduced by a discount.') }}</p>
    </div>
    <div class="mb-3"><select class="form-select form-select-sm w-auto" wire:model.live="termId">@foreach ($terms as $term) <option value="{{ $term->id }}">{{ $term->name }}</option> @endforeach</select></div>
    <div class="card"><div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead><tr><th>{{ __('Scheme') }}</th><th class="text-end">{{ __('Gross billed') }}</th><th class="text-end">{{ __('Discount granted') }}</th><th class="text-end">{{ __('Net billed') }}</th><th>{{ __('Currency') }}</th></tr></thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr wire:key="cg-{{ $row->schemeId }}"><td>{{ $row->schemeCode }} — {{ $row->schemeName }}</td><td class="text-end">{{ number_format($row->grossMinor / 100, 2) }}</td><td class="text-end">{{ number_format($row->discountMinor / 100, 2) }}</td><td class="text-end">{{ number_format($row->netMinor / 100, 2) }}</td><td>{{ $row->currency }}</td></tr>
                @empty
                    <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No discounts were posted in this term.') }}</td></tr>
                @endforelse
            </tbody>
            @if ($totals)
                <tfoot>@foreach ($totals as $currency => $total)<tr class="fw-semibold"><td>{{ __('All schemes') }}</td><td class="text-end">{{ number_format($total['gross'] / 100, 2) }}</td><td class="text-end">{{ number_format($total['discount'] / 100, 2) }}</td><td class="text-end">{{ number_format($total['net'] / 100, 2) }}</td><td>{{ $currency }}</td></tr>@endforeach</tfoot>
            @endif
        </table>
    </div></div>
</div>
