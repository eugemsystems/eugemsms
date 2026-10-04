<div>
    <h4 class="mb-1">{{ __('Immunisations') }}</h4>

    <div class="row g-4">
        <div class="col-md-7">
            <div class="card">
                <div class="card-header">{{ __('Record') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Student') }}</th><th>{{ __('Vaccine') }}</th><th>{{ __('Status') }}</th><th>{{ __('Next due') }}</th></tr></thead>
                        <tbody>
                            @forelse ($immunisations as $immunisation)
                                <tr wire:key="immunisation-{{ $immunisation->id }}">
                                    <td>{{ $immunisation->student?->first_name }} {{ $immunisation->student?->last_name }}</td>
                                    <td>{{ $immunisation->vaccine }} @if ($immunisation->dose_number) ({{ __('dose :n', ['n' => $immunisation->dose_number]) }}) @endif</td>
                                    <td><span class="badge text-bg-secondary">{{ $immunisation->status }}</span></td>
                                    <td>{{ $immunisation->next_due_on?->toDateString() ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('No immunisations recorded.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-5">
            <div class="card">
                <div class="card-header">{{ __('Record immunisation') }}</div>
                <div class="card-body">
                    <select class="form-select mb-2" wire:model="studentId">
                        <option value="">{{ __('Student') }}</option>
                        @foreach ($students as $student)
                            <option value="{{ $student->id }}">{{ $student->first_name }} {{ $student->last_name }}</option>
                        @endforeach
                    </select>
                    <input type="text" class="form-control mb-2" wire:model="vaccine" placeholder="{{ __('Vaccine') }}">
                    <input type="number" class="form-control mb-2" wire:model="doseNumber" placeholder="{{ __('Dose number (optional)') }}">
                    <select class="form-select mb-2" wire:model="status">
                        <option value="recorded">{{ __('Recorded') }}</option>
                        <option value="due">{{ __('Due') }}</option>
                        <option value="overdue">{{ __('Overdue') }}</option>
                        <option value="declined">{{ __('Declined') }}</option>
                        <option value="exempt">{{ __('Exempt') }}</option>
                    </select>
                    <input type="date" class="form-control mb-2" wire:model="administeredOn" placeholder="{{ __('Administered on') }}">
                    <input type="text" class="form-control mb-2" wire:model="administeredBy" placeholder="{{ __('Administered by') }}">
                    <input type="date" class="form-control mb-2" wire:model="nextDueOn" placeholder="{{ __('Next due') }}">
                    <input type="text" class="form-control mb-2" wire:model="declineReason" placeholder="{{ __('Decline reason (if declined)') }}">
                    <button type="button" class="btn btn-primary btn-sm" wire:click="record">{{ __('Record') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
