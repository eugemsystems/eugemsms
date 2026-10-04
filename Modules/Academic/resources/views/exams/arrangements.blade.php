<div>
    <h4 class="mb-1">{{ __('Special arrangements') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Recorded as requested; approval is the only way an arrangement becomes effective for seating and invigilation.') }}</p>

    <div class="row g-4">
        <div class="col-md-7">
            <div class="row g-2 mb-3">
                <div class="col-md-5">
                    <select class="form-select" wire:model.live="sessionId">
                        <option value="">{{ __('Select session') }}</option>
                        @foreach ($sessions as $session)
                            <option value="{{ $session->id }}">{{ $session->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Learner') }}</th><th>{{ __('Type') }}</th><th>{{ __('Extra time') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($arrangements as $arrangement)
                                <tr wire:key="arrangement-{{ $arrangement->id }}">
                                    <td>{{ $arrangement->student?->first_name }} {{ $arrangement->student?->last_name }}</td>
                                    <td>{{ ucfirst(str_replace('_', ' ', $arrangement->arrangement_type)) }}</td>
                                    <td>{{ $arrangement->extra_time_percent ? "+{$arrangement->extra_time_percent}%" : '—' }}</td>
                                    <td><span class="badge text-bg-{{ $arrangement->status === 'approved' ? 'success' : 'warning' }}">{{ ucfirst($arrangement->status) }}</span></td>
                                    <td>
                                        @if ($arrangement->status === 'requested')
                                            <button type="button" class="btn btn-sm btn-primary" wire:click="approve({{ $arrangement->id }})">{{ __('Approve') }}</button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-body-secondary py-4">{{ __('No arrangements recorded yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-5">
            <div class="card">
                <div class="card-header">{{ __('Record arrangement') }}</div>
                <div class="card-body">
                    <form wire:submit="record">
                        <div class="row g-3">
                            <div class="col-12">
                                <div class="form-floating form-floating-outline">
                                    <select class="form-select" wire:model="studentId">
                                        <option value="">{{ __('Select learner') }}</option>
                                        @foreach ($students as $student)
                                            <option value="{{ $student->id }}">{{ $student->first_name }} {{ $student->last_name }}</option>
                                        @endforeach
                                    </select>
                                    <label>{{ __('Learner') }}</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <select class="form-select" wire:model="arrangementType">
                                        <option value="extra_time">{{ __('Extra time') }}</option>
                                        <option value="separate_room">{{ __('Separate room') }}</option>
                                        <option value="reader">{{ __('Reader') }}</option>
                                        <option value="scribe">{{ __('Scribe') }}</option>
                                        <option value="large_print">{{ __('Large print') }}</option>
                                        <option value="rest_breaks">{{ __('Rest breaks') }}</option>
                                        <option value="prompter">{{ __('Prompter') }}</option>
                                        <option value="assistive_technology">{{ __('Assistive technology') }}</option>
                                    </select>
                                    <label>{{ __('Arrangement type') }}</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <input type="number" class="form-control" wire:model="extraTimePercent" placeholder=" ">
                                    <label>{{ __('Extra time % (optional)') }}</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-floating form-floating-outline">
                                    <textarea class="form-control @error('justification') is-invalid @enderror" wire:model="justification" style="height: 80px"></textarea>
                                    <label>{{ __('Justification') }}</label>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary mt-3" wire:loading.attr="disabled">{{ __('Record') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
