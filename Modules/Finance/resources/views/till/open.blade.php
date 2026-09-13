<div>
    <div class="mb-4">
        <h4 class="mb-1">{{ __('Open till') }}</h4>
        <p class="text-body-secondary mb-0">{{ __('You may hold at most one open till session at a time.') }}</p>
    </div>

    @if ($myOpenSession)
        <div class="alert alert-info d-flex align-items-center justify-content-between">
            <span>{{ __('You already have an open session — :code (:number).', ['code' => $myOpenSession->till->code, 'number' => $myOpenSession->session_number]) }}</span>
            <a href="{{ route('finance.receipts.capture', ['school' => $school, 'tillSession' => $myOpenSession]) }}" class="btn btn-sm btn-primary" wire:navigate>{{ __('Continue receipting') }}</a>
        </div>
    @else
        <div class="card">
            <div class="card-body">
                <form wire:submit="open">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <div class="form-floating form-floating-outline">
                                <select class="form-select @error('tillId') is-invalid @enderror" id="tillId" wire:model="tillId">
                                    <option value="">{{ __('Select a till') }}</option>
                                    @foreach ($tills as $till)
                                        <option value="{{ $till->id }}">{{ $till->code }} — {{ $till->name }}</option>
                                    @endforeach
                                </select>
                                <label for="tillId">{{ __('Till') }}</label>
                                @error('tillId') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    </div>

                    <p class="text-body-secondary">{{ __('Count the starting float in the drawer for each currency.') }}</p>
                    <div class="row g-3 mb-3">
                        @foreach ($openingFloat as $currency => $amount)
                            <div class="col-md-4">
                                <div class="form-floating form-floating-outline">
                                    <input type="text" inputmode="decimal" class="form-control" id="float-{{ $currency }}" wire:model="openingFloat.{{ $currency }}" placeholder="0.00">
                                    <label for="float-{{ $currency }}">{{ __(':currency opening float', ['currency' => $currency]) }}</label>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">{{ __('Open till') }}</button>
                </form>
            </div>
        </div>
    @endif
</div>
