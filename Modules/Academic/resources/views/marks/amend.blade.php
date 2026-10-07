<div>
    <h4 class="mb-1">{{ __('Amend mark') }}</h4>
    <p class="text-body-secondary mb-4">{{ $assessment->title }} — <span class="badge text-bg-secondary">{{ ucfirst($assessment->status) }}</span></p>

    @if ($assessment->status === 'published')
        <div class="alert alert-warning">{{ __('This assessment is published — amending requires academic.result.amend_published, and routes through approval before anything changes.') }}</div>
    @endif

    <div class="card">
        <div class="card-header">{{ __('Existing marks') }}</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="form-floating form-floating-outline">
                        <select class="form-select @error('studentId') is-invalid @enderror" wire:model="studentId">
                            <option value="">{{ __('Select') }}</option>
                            @foreach ($marks as $mark)
                                <option value="{{ $mark->student_id }}">{{ $mark->student?->first_name }} {{ $mark->student?->last_name }} ({{ $mark->is_absent ? __('absent') : $mark->raw_mark }})</option>
                            @endforeach
                        </select>
                        <label>{{ __('Student') }}</label>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-floating form-floating-outline">
                        <input type="number" step="0.01" class="form-control" wire:model="rawMark" @disabled($isAbsent) placeholder=" ">
                        <label>{{ __('New mark') }}</label>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-check form-switch mt-3">
                        <input class="form-check-input" type="checkbox" wire:model="isAbsent">
                        <label class="form-check-label">{{ __('Absent') }}</label>
                    </div>
                </div>
                <div class="col-12">
                    <div class="form-floating form-floating-outline">
                        <textarea class="form-control @error('changeReason') is-invalid @enderror" wire:model="changeReason" placeholder=" " style="height: 80px"></textarea>
                        <label>{{ __('Reason (minimum 15 characters)') }}</label>
                        @error('changeReason') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>
            <button type="button" class="btn btn-primary mt-3" wire:click="amend" wire:confirm="{{ $assessment->status === 'published' ? __('Request approval to amend this mark?') : __('Amend this mark? Positions will be recomputed for the whole class and level.') }}">{{ $assessment->status === 'published' ? __('Request amendment') : __('Amend mark') }}</button>
        </div>
    </div>

    @if ($amendmentRequests->isNotEmpty())
        <div class="card mt-3">
            <div class="card-header">{{ __('Amendment requests') }}</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>{{ __('Student') }}</th><th>{{ __('New mark') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($amendmentRequests as $amendmentRequest)
                            <tr wire:key="amend-req-{{ $amendmentRequest->id }}">
                                <td>{{ $amendmentRequest->student?->first_name }} {{ $amendmentRequest->student?->last_name }}</td>
                                <td>{{ $amendmentRequest->new_is_absent ? __('absent') : $amendmentRequest->new_raw_mark }}</td>
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
