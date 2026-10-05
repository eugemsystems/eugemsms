<div>
    <h4 class="mb-1">{{ __('Generator log') }}</h4>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Generator') }}</th><th>{{ __('Started') }}</th><th>{{ __('Hours') }}</th><th>{{ __('Reason') }}</th><th>{{ __('L/h') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($runs as $run)
                                <tr wire:key="run-{{ $run->id }}">
                                    <td>{{ $run->generator->code }}</td>
                                    <td>{{ $run->started_at->format('Y-m-d H:i') }}</td>
                                    <td>{{ $run->hours_run !== null ? number_format((float) $run->hours_run, 2) : '—' }}</td>
                                    <td>{{ $run->reason }}</td>
                                    <td>
                                        @if ($run->litres_per_hour !== null)
                                            <span class="{{ $run->is_anomaly ? 'text-danger fw-bold' : '' }}">{{ number_format((float) $run->litres_per_hour, 2) }}</span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td>
                                        @if ($run->stopped_at === null)
                                            <button type="button" class="btn btn-outline-danger btn-sm" wire:click="selectForStop({{ $run->id }})">{{ __('Stop') }}</button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-body-secondary py-3">{{ __('No generator runs.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card mb-3">
                <div class="card-header">{{ __('Start run') }}</div>
                <div class="card-body">
                    <select class="form-select mb-2" wire:model="generatorId">
                        <option value="">{{ __('Generator') }}</option>
                        @foreach ($generators as $gen)
                            <option value="{{ $gen->id }}">{{ $gen->code }} — {{ $gen->name }}</option>
                        @endforeach
                    </select>
                    <select class="form-select mb-2" wire:model="reason">
                        @foreach (['load_shedding', 'fault', 'test', 'maintenance', 'event'] as $reason)
                            <option value="{{ $reason }}">{{ $reason }}</option>
                        @endforeach
                    </select>
                    <input type="text" class="form-control mb-2" wire:model="loadSheddingStage" placeholder="{{ __('Load shedding stage (optional)') }}">
                    <button type="button" class="btn btn-primary btn-sm" wire:click="start">{{ __('Start run') }}</button>
                </div>
            </div>

            @if ($stoppingRunId !== null)
                <div class="card">
                    <div class="card-header">{{ __('Stop run #') }}{{ $stoppingRunId }}</div>
                    <div class="card-body">
                        <input type="number" step="0.01" class="form-control mb-2" wire:model="dieselLitres" placeholder="{{ __('Diesel litres (optional)') }}">
                        <input type="number" class="form-control mb-2" wire:model="dieselUnitPriceMinor" placeholder="{{ __('Diesel unit price (minor units, optional)') }}">
                        <select class="form-select mb-2" wire:model="storeId">
                            <option value="">{{ __('Draw from store (optional)') }}</option>
                            @foreach ($stores as $store)
                                <option value="{{ $store->id }}">{{ $store->code }} — {{ $store->name }}</option>
                            @endforeach
                        </select>
                        <select class="form-select mb-2" wire:model="itemId">
                            <option value="">{{ __('Diesel item (optional)') }}</option>
                            @foreach ($items as $item)
                                <option value="{{ $item->id }}">{{ $item->code }} — {{ $item->name }}</option>
                            @endforeach
                        </select>
                        <button type="button" class="btn btn-danger btn-sm" wire:click="stop">{{ __('Stop run') }}</button>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
