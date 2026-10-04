<div>
    <h4 class="mb-1">{{ __('Waiting list') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Ordered by priority score. Recompute after a bed is released.') }}</p>

    <div class="row g-4">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>{{ __('Waiting') }}</span>
                    <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="reevaluate">{{ __('Recompute positions') }}</button>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>#</th><th>{{ __('Learner') }}</th><th>{{ __('Preferred hostel') }}</th><th>{{ __('Reason') }}</th></tr></thead>
                        <tbody>
                            @forelse ($entries as $entry)
                                <tr><td>{{ $entry->position }}</td><td>{{ $entry->student->first_name }} {{ $entry->student->last_name }}</td><td>{{ $entry->preferredHostel?->code ?? '—' }}</td><td>{{ $entry->reason }}</td></tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('Nobody waiting.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">{{ __('Add to waiting list') }}</div>
                <div class="card-body">
                    <select class="form-select mb-2" wire:model="studentId">
                        <option value="">{{ __('Select learner') }}</option>
                        @foreach ($students as $student)
                            <option value="{{ $student->id }}">{{ $student->first_name }} {{ $student->last_name }}</option>
                        @endforeach
                    </select>
                    <select class="form-select mb-2" wire:model="preferredHostelId">
                        <option value="">{{ __('No preference') }}</option>
                        @foreach ($hostels as $hostel)
                            <option value="{{ $hostel->id }}">{{ $hostel->name }}</option>
                        @endforeach
                    </select>
                    <input type="text" class="form-control mb-2" wire:model="reason" placeholder="{{ __('Reason (optional)') }}">
                    <button type="button" class="btn btn-primary btn-sm" wire:click="add">{{ __('Add') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
