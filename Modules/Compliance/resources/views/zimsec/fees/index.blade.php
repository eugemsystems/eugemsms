<div>
    <h4 class="mb-1">{{ __('ZIMSEC entry fees') }} 🇿🇼</h4>
    <p class="text-body-secondary small">{{ __('Collections must reconcile to the amount remitted to ZIMSEC — a shortfall blocks registration closure.') }}</p>

    <select class="form-select mb-3" style="max-width:420px" wire:model="registrationId">
        <option value="0">{{ __('Select registration') }}</option>
        @foreach ($registrations as $registration)
            <option value="{{ $registration->id }}">{{ $registration->exam_level }} — {{ $registration->exam_series }}</option>
        @endforeach
    </select>

    @if ($selected)
        @php $shortfall = $selected->total_fees_minor - $selected->collected_minor; @endphp
        <div class="row g-3 mb-3">
            <div class="col-md-3"><div class="card card-body"><div class="small text-body-secondary">{{ __('Billed') }}</div><div class="fs-5">{{ number_format($selected->total_fees_minor / 100, 2) }} {{ $selected->currency }}</div></div></div>
            <div class="col-md-3"><div class="card card-body"><div class="small text-body-secondary">{{ __('Collected') }}</div><div class="fs-5">{{ number_format($selected->collected_minor / 100, 2) }} {{ $selected->currency }}</div></div></div>
            <div class="col-md-3"><div class="card card-body"><div class="small text-body-secondary">{{ __('Remitted') }}</div><div class="fs-5">{{ number_format($selected->remitted_minor / 100, 2) }} {{ $selected->currency }}</div></div></div>
            <div class="col-md-3"><div class="card card-body {{ $shortfall > 0 ? 'border-danger' : '' }}"><div class="small text-body-secondary">{{ __('Shortfall') }}</div><div class="fs-5 {{ $shortfall > 0 ? 'text-danger' : 'text-success' }}">{{ number_format($shortfall / 100, 2) }} {{ $selected->currency }}</div></div></div>
        </div>

        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">{{ __('Bill entry fees') }}</div>
                    <div class="card-body">
                        <select class="form-select mb-2" wire:model="feeComponentId">
                            <option value="0">{{ __('Select fee component') }}</option>
                            @foreach ($feeComponents as $component)
                                <option value="{{ $component->id }}">{{ $component->name }}</option>
                            @endforeach
                        </select>
                        <button type="button" class="btn btn-outline-primary btn-sm" wire:click="billFees">{{ __('Bill not-yet-billed candidates') }}</button>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">{{ __('Record collection') }}</div>
                    <div class="card-body">
                        <input type="number" step="0.01" class="form-control mb-2" wire:model="collectionAmount" placeholder="{{ __('Amount collected') }}">
                        <button type="button" class="btn btn-outline-primary btn-sm" wire:click="recordCollection">{{ __('Record') }}</button>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">{{ __('Record remittance') }}</div>
                    <div class="card-body">
                        <input type="number" step="0.01" class="form-control mb-2" wire:model="remittanceAmount" placeholder="{{ __('Amount remitted to ZIMSEC') }}">
                        <button type="button" class="btn btn-outline-primary btn-sm" wire:click="recordRemittance">{{ __('Record') }}</button>
                    </div>
                </div>
            </div>
        </div>
    @else
        <p class="text-body-secondary">{{ __('Select a registration above.') }}</p>
    @endif
</div>
