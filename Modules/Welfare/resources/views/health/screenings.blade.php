<div>
    <h4 class="mb-1">{{ __('Health screenings') }}</h4>

    <div class="row g-4">
        <div class="col-md-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Student') }}</th><th>{{ __('Type') }}</th><th>{{ __('Outcome') }}</th><th>{{ __('Screened') }}</th></tr></thead>
                        <tbody>
                            @forelse ($screenings as $screening)
                                <tr wire:key="screening-{{ $screening->id }}">
                                    <td>{{ $screening->student?->first_name }} {{ $screening->student?->last_name }}</td>
                                    <td>{{ $screening->screening_type }}</td>
                                    <td><span class="badge text-bg-{{ $screening->outcome === 'refer' ? 'danger' : ($screening->outcome === 'monitor' ? 'warning' : 'success') }}">{{ $screening->outcome }}</span></td>
                                    <td>{{ $screening->screened_on->toDateString() }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('No screenings recorded.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-5">
            <div class="card">
                <div class="card-header">{{ __('Record screening') }}</div>
                <div class="card-body">
                    <select class="form-select mb-2" wire:model="studentId">
                        <option value="">{{ __('Student') }}</option>
                        @foreach ($students as $student)
                            <option value="{{ $student->id }}">{{ $student->first_name }} {{ $student->last_name }}</option>
                        @endforeach
                    </select>
                    <select class="form-select mb-2" wire:model="screeningType">
                        <option value="vision">{{ __('Vision') }}</option>
                        <option value="hearing">{{ __('Hearing') }}</option>
                        <option value="dental">{{ __('Dental') }}</option>
                        <option value="growth">{{ __('Growth') }}</option>
                        <option value="general">{{ __('General') }}</option>
                    </select>
                    <select class="form-select mb-2" wire:model="outcome">
                        <option value="normal">{{ __('Normal') }}</option>
                        <option value="monitor">{{ __('Monitor') }}</option>
                        <option value="refer">{{ __('Refer') }}</option>
                    </select>
                    <input type="text" class="form-control mb-2" wire:model="screenedBy" placeholder="{{ __('Screened by') }}">
                    <textarea class="form-control mb-2" wire:model="resultsNote" placeholder="{{ __('Results note (optional)') }}"></textarea>
                    <button type="button" class="btn btn-primary btn-sm" wire:click="record">{{ __('Record') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
