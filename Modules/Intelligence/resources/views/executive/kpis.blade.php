<div>
    <h4 class="mb-1">{{ __('KPI targets') }}</h4>
    <p class="text-body-secondary small">{{ __('Targets for :year. Where you set nothing, the system default applies. The warning threshold is on the indicator’s own scale: for a 92% target, a threshold of 90 shows 87% red and 91% amber. Set it deliberately for indicators that are not percentages.', ['year' => $year?->name ?? __('the current year')]) }}</p>

    @if (! $year) <div class="alert alert-warning small">{{ __('There is no current academic year, so targets cannot be set.') }}</div> @endif

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Indicator') }}</th><th>{{ __('Unit') }}</th><th>{{ __('Default') }}</th><th style="width: 9rem">{{ __('Target') }}</th><th style="width: 9rem">{{ __('Warning at') }}</th><th></th></tr></thead>
                <tbody>
                    @foreach ($kpis as $kpi)
                        <tr wire:key="kpi-{{ $kpi->key }}">
                            <td>{{ $kpi->label }} <span class="small text-body-secondary">({{ $kpi->higherIsBetter ? __('higher is better') : __('lower is better') }})</span></td>
                            <td>{{ $kpi->unit }}</td>
                            <td>{{ $kpi->defaultTargetValue ?? '—' }}</td>
                            <td><input type="text" inputmode="decimal" class="form-control form-control-sm" wire:model="rows.{{ $kpi->key }}.target" @disabled(! $year)></td>
                            <td><input type="text" inputmode="decimal" class="form-control form-control-sm" wire:model="rows.{{ $kpi->key }}.warning" @disabled(! $year)></td>
                            <td class="text-end">
                                @if ($overrides->has($kpi->key)) <span class="badge bg-label-primary">{{ __('overridden') }}</span> @endif
                                <button type="button" class="btn btn-xs btn-outline-primary" wire:click="save('{{ $kpi->key }}')" @disabled(! $year)>{{ __('Save') }}</button>
                            </td>
                        </tr>
                        @error("rows.{$kpi->key}.target") <tr><td colspan="6" class="text-danger small">{{ $message }}</td></tr> @enderror
                        @error("rows.{$kpi->key}.warning") <tr><td colspan="6" class="text-danger small">{{ $message }}</td></tr> @enderror
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
