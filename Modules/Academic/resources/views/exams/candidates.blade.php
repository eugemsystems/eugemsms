<div>
    <h4 class="mb-1">{{ __('Examination candidates') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Derived from ACA-02 subject enrolments — never typed from scratch.') }}</p>

    <div class="row g-2 mb-3 align-items-center">
        <div class="col-md-4">
            <select class="form-select" wire:model.live="sessionId">
                <option value="">{{ __('Select session') }}</option>
                @foreach ($sessions as $session)
                    <option value="{{ $session->id }}">{{ $session->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <button type="button" class="btn btn-outline-primary w-100" wire:click="derive" @disabled(! $sessionId)>{{ __('Derive from enrolments') }}</button>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Learner') }}</th><th>{{ __('Index number') }}</th><th>{{ __('Subjects entered') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($candidates as $candidate)
                        <tr wire:key="candidate-{{ $candidate->id }}">
                            <td>{{ $candidate->student?->first_name }} {{ $candidate->student?->last_name }}</td>
                            <td>{{ $candidate->index_number }}</td>
                            <td>{{ count($candidate->entered_subjects) }}</td>
                            <td><span class="badge text-bg-{{ $candidate->entry_status === 'confirmed' ? 'success' : 'warning' }}">{{ ucfirst($candidate->entry_status) }}</span></td>
                            <td>
                                @if ($candidate->entry_status !== 'confirmed')
                                    <button type="button" class="btn btn-sm btn-primary" wire:click="confirm({{ $candidate->id }})">{{ __('Confirm') }}</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-body-secondary py-4">{{ __('No candidates derived yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
