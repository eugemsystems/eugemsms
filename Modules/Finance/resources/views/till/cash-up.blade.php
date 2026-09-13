<div>
    <div class="mb-4">
        <h4 class="mb-1">{{ __('Cash up till') }}</h4>
        <p class="text-body-secondary mb-0">{{ __('Till :code — session :number', ['code' => $tillSession->till->code, 'number' => $tillSession->session_number]) }}</p>
    </div>

    <div class="card">
        <div class="card-body">
            @if ($tillSession->status === 'open')
                <p class="text-body-secondary">{{ __('Count the physical cash and other tenders in the drawer, then declare the total for each currency. The expected total is not shown until after you declare — this is a blind count.') }}</p>

                <form wire:submit="declare">
                    <div class="row g-3 mb-3">
                        @foreach ($declaredClosing as $currency => $amount)
                            <div class="col-md-4">
                                <div class="form-floating form-floating-outline">
                                    <input type="text" inputmode="decimal" class="form-control @error('declaredClosing') is-invalid @enderror" id="declared-{{ $currency }}" wire:model="declaredClosing.{{ $currency }}" placeholder="0.00">
                                    <label for="declared-{{ $currency }}">{{ __(':currency counted', ['currency' => $currency]) }}</label>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    @error('declaredClosing') <div class="alert alert-danger">{{ $message }}</div> @enderror
                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">{{ __('Declare count') }}</button>
                </form>
            @elseif ($needsSupervisorSignOff)
                <div class="alert alert-warning">
                    {{ __('The declared count is outside the allowed variance tolerance. A user holding the till-supervisor permission must open Till → Variance approval, review this session, and sign off before it can close.') }}
                </div>
                @error('varianceReason') <div class="alert alert-danger">{{ $message }}</div> @enderror
                <p class="text-body-secondary mb-0">{{ __('Declared count is locked in — no further action is needed from you on this screen.') }}</p>
            @else
                <p class="text-body-secondary">{{ __('Count declared. Reveal the variance and close this session.') }}</p>

                <form wire:submit="reveal">
                    <div class="mb-3">
                        <div class="form-floating form-floating-outline">
                            <textarea class="form-control @error('varianceReason') is-invalid @enderror" id="varianceReason" wire:model="varianceReason" style="height: 6rem;" placeholder=" "></textarea>
                            <label for="varianceReason">{{ __('Variance reason (only needed if a variance is found)') }}</label>
                            @error('varianceReason') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">{{ __('Reveal & close session') }}</button>
                </form>
            @endif
        </div>
    </div>
</div>
