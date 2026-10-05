<div>
    <h4 class="mb-1">{{ __('ZIMSEC statements of entry') }} 🇿🇼</h4>

    <div class="row g-2 align-items-end mb-3">
        <div class="col-auto">
            <select class="form-select" wire:model="registrationId">
                <option value="0">{{ __('Select registration') }}</option>
                @foreach ($registrations as $registration)
                    <option value="{{ $registration->id }}">{{ $registration->exam_level }} — {{ $registration->exam_series }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-auto">
            <button type="button" class="btn btn-primary btn-sm" wire:click="distribute">{{ __('Distribute to valid/warned candidates') }}</button>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Candidate') }}</th><th>{{ __('Statement') }}</th><th>{{ __('Confirmed') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($candidates as $candidate)
                        <tr wire:key="stmt-{{ $candidate->id }}">
                            <td>{{ $candidate->surname }}, {{ $candidate->forenames }}</td>
                            <td>{{ $candidate->statement_of_entry_id ? __('Distributed') : __('Not yet') }}</td>
                            <td>{{ $candidate->statement_confirmed ? __('Yes') : __('No') }}</td>
                            <td class="text-end">
                                @if ($candidate->statement_of_entry_id && ! $candidate->statement_confirmed)
                                    <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="confirmReceipt({{ $candidate->id }})">{{ __('Confirm receipt') }}</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('No candidates for this registration.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
