<div>
    <h4 class="mb-1">{{ __('Meter readings') }}</h4>

    @if ($lastResult !== null && $lastResult->is_anomaly)
        <div class="alert alert-warning py-2" style="max-width:40rem">{{ $lastResult->anomaly_note }}</div>
    @endif

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Meter') }}</th><th>{{ __('Date') }}</th><th>{{ __('Reading') }}</th><th>{{ __('Consumption') }}</th><th>{{ __('Anomaly') }}</th></tr></thead>
                        <tbody>
                            @forelse ($readings as $r)
                                <tr wire:key="reading-{{ $r->id }}">
                                    <td>{{ $r->meter->meter_number }}</td>
                                    <td>{{ $r->read_on->toDateString() }}</td>
                                    <td>{{ number_format((float) $r->reading, 3) }}</td>
                                    <td>{{ $r->consumption !== null ? number_format((float) $r->consumption, 3) : '—' }}</td>
                                    <td>
                                        @if ($r->is_anomaly)
                                            <span class="badge bg-danger" title="{{ $r->anomaly_note }}">{{ __('Flagged') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No readings.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">{{ __('Record reading') }}</div>
                <div class="card-body">
                    <select class="form-select mb-2" wire:model="meterId">
                        <option value="">{{ __('Meter') }}</option>
                        @foreach ($meters as $meter)
                            <option value="{{ $meter->id }}">{{ $meter->meter_number }} — {{ $meter->location }}</option>
                        @endforeach
                    </select>
                    <input type="date" class="form-control mb-2" wire:model="readOn">
                    <input type="number" step="0.001" class="form-control mb-2" wire:model="reading" placeholder="{{ __('Reading') }}">
                    <select class="form-select mb-2" wire:model="readingMethod">
                        @foreach (['manual', 'photo', 'smart', 'estimated'] as $method)
                            <option value="{{ $method }}">{{ $method }}</option>
                        @endforeach
                    </select>
                    <select class="form-select mb-2" wire:model="prepaidAssetAccountId">
                        <option value="">{{ __('Prepaid asset account (prepaid meters only, optional)') }}</option>
                        @foreach ($accounts as $account)
                            <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>
                        @endforeach
                    </select>
                    <button type="button" class="btn btn-primary btn-sm" wire:click="record">{{ __('Record reading') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
