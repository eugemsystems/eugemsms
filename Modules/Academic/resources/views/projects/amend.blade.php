<div>
    <h4 class="mb-1">{{ __('Amend verified project') }}</h4>
    <p class="text-body-secondary mb-4">{{ $learnerProject->student?->first_name }} {{ $learnerProject->student?->last_name }} — {{ __('current mark') }}: {{ $learnerProject->raw_mark }}</p>

    <div class="card">
        <div class="card-body">
            <form wire:submit="amend">
                @if ($rubric)
                    @foreach ($rubric->criteria as $criterion)
                        <div class="row g-2 mb-2 align-items-center">
                            <div class="col-md-7">{{ $criterion->criterion }}</div>
                            <div class="col-md-5">
                                <input type="number" step="0.01" class="form-control form-control-sm" wire:model="marks.{{ $criterion->criterion }}" max="{{ $criterion->max_mark }}">
                            </div>
                        </div>
                    @endforeach
                @endif

                <div class="form-floating form-floating-outline mt-3">
                    <textarea class="form-control @error('changeReason') is-invalid @enderror" wire:model="changeReason" style="height: 80px"></textarea>
                    <label>{{ __('Change reason (min 15 characters — proof CORE-07 approval has run)') }}</label>
                    @error('changeReason') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <button type="submit" class="btn btn-danger mt-3" wire:loading.attr="disabled">{{ __('Amend verified mark') }}</button>
            </form>
        </div>
    </div>
</div>
