<div>
    <h4 class="mb-1">{{ __('Learner incompatibilities') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('The allocation engine honours these; the reason is restricted unless you hold the manage permission.') }}</p>

    <div class="row g-4">
        <div class="col-md-7">
            <div class="card">
                <div class="card-header">{{ __('Active') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Learners') }}</th><th>{{ __('Scope') }}</th><th>{{ __('Category') }}</th><th>{{ __('Reason') }}</th></tr></thead>
                        <tbody>
                            @forelse ($incompatibilities as $row)
                                <tr>
                                    <td>{{ $row->studentA->first_name }} {{ $row->studentA->last_name }} ↔ {{ $row->studentB->first_name }} {{ $row->studentB->last_name }}</td>
                                    <td>{{ ucfirst($row->scope) }}</td>
                                    <td>{{ str_replace('_', ' ', $row->reason_category) }}</td>
                                    <td>{{ $row->display_reason }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('None recorded.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-5">
            <div class="card">
                <div class="card-header">{{ __('New incompatibility') }}</div>
                <div class="card-body">
                    <form wire:submit="create">
                        <select class="form-select mb-2" wire:model="studentAId">
                            <option value="">{{ __('Learner A') }}</option>
                            @foreach ($students as $student)
                                <option value="{{ $student->id }}">{{ $student->first_name }} {{ $student->last_name }}</option>
                            @endforeach
                        </select>
                        <select class="form-select mb-2" wire:model="studentBId">
                            <option value="">{{ __('Learner B') }}</option>
                            @foreach ($students as $student)
                                <option value="{{ $student->id }}">{{ $student->first_name }} {{ $student->last_name }}</option>
                            @endforeach
                        </select>
                        <select class="form-select mb-2" wire:model="scope">
                            <option value="room">{{ __('Room') }}</option>
                            <option value="wing">{{ __('Wing') }}</option>
                            <option value="hostel">{{ __('Hostel') }}</option>
                        </select>
                        <select class="form-select mb-2" wire:model="reasonCategory">
                            <option value="bullying">{{ __('Bullying') }}</option>
                            <option value="conflict">{{ __('Conflict') }}</option>
                            <option value="safeguarding">{{ __('Safeguarding') }}</option>
                            <option value="family_request">{{ __('Family request') }}</option>
                            <option value="medical">{{ __('Medical') }}</option>
                        </select>
                        <textarea class="form-control mb-2" wire:model="reason" placeholder="{{ __('Reason') }}"></textarea>
                        <div class="form-check mb-2">
                            <input type="checkbox" class="form-check-input" wire:model="isConfidential" id="isConfidential">
                            <label class="form-check-label" for="isConfidential">{{ __('Confidential — reason restricted to those with manage rights') }}</label>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm">{{ __('Record') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
