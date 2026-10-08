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
                    <label>{{ __('Change reason (min 15 characters)') }}</label>
                    @error('changeReason') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <button type="submit" class="btn btn-danger mt-3" wire:loading.attr="disabled" wire:confirm="{{ __('Request approval to amend this verified mark?') }}">{{ __('Request amendment') }}</button>
            </form>
        </div>
    </div>

    @if ($amendmentRequests->isNotEmpty())
        <div class="card mt-3">
            <div class="card-header">{{ __('Amendment requests') }}</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>{{ __('Requested') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($amendmentRequests as $amendmentRequest)
                            <tr wire:key="amend-req-{{ $amendmentRequest->id }}">
                                <td>{{ $amendmentRequest->created_at?->diffForHumans() }}</td>
                                <td><span class="badge text-bg-light border">{{ $amendmentRequest->status }}</span></td>
                                <td>
                                    @if ($amendmentRequest->approval_request_id)
                                        <a href="{{ route('approvals.show', ['school' => $school, 'request' => $amendmentRequest->approval_request_id]) }}" class="small">{{ __('View request') }}</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
