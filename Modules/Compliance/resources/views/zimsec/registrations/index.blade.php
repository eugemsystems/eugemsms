<div>
    <h4 class="mb-1">{{ __('ZIMSEC registrations') }} 🇿🇼</h4>
    <p class="text-body-secondary small">{{ __('One row per exam level and series. Deriving candidates copies confirmed ACA-07 entries and their bio-data — nothing here is re-keyed.') }}</p>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('Level / Series') }}</th>
                                <th>{{ __('Centre') }}</th>
                                <th>{{ __('Closes') }}</th>
                                <th>{{ __('Candidates') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($registrations as $registration)
                                <tr wire:key="reg-{{ $registration->id }}">
                                    <td>{{ $registration->exam_level }} — {{ $registration->exam_series }}</td>
                                    <td>{{ $registration->centre_number }}</td>
                                    <td>
                                        {{ $registration->registration_closes_on->toDateString() }}
                                        @php $daysLeft = now()->diffInDays($registration->registration_closes_on, false); @endphp
                                        @if ($registration->status !== 'closed' && $registration->status !== 'confirmed')
                                            <span class="badge {{ $daysLeft < 0 ? 'bg-danger' : ($daysLeft <= 7 ? 'bg-warning text-dark' : 'bg-light text-dark border') }}">
                                                {{ $daysLeft < 0 ? __('overdue') : __(':days day(s) left', ['days' => $daysLeft]) }}
                                            </span>
                                        @endif
                                    </td>
                                    <td>{{ $registration->candidate_count }} ({{ $registration->validated_count }} {{ __('valid') }}, {{ $registration->error_count }} {{ __('errors') }})</td>
                                    <td><span class="badge bg-light text-dark border">{{ $registration->status }}</span></td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="deriveCandidates({{ $registration->id }})" wire:confirm="{{ __('Derive candidates from confirmed ACA-07 entries?') }}">{{ __('Derive') }}</button>
                                        @if ($registration->status !== 'closed')
                                            <button type="button" class="btn btn-outline-danger btn-sm" wire:click="close({{ $registration->id }})" wire:confirm="{{ __('Close this registration?') }}">{{ __('Close') }}</button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-body-secondary py-3">{{ __('No registrations yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">{{ __('New registration') }}</div>
                <div class="card-body">
                    <select class="form-select mb-2" wire:model="academicYearId">
                        <option value="0">{{ __('Select academic year') }}</option>
                        @foreach ($academicYears as $year)
                            <option value="{{ $year->id }}">{{ $year->name }}</option>
                        @endforeach
                    </select>
                    <select class="form-select mb-2" wire:model="examLevel">
                        <option value="grade_7">{{ __('Grade 7') }}</option>
                        <option value="o_level">{{ __('O-Level') }}</option>
                        <option value="a_level">{{ __('A-Level') }}</option>
                    </select>
                    <input type="text" class="form-control mb-2" wire:model="examSeries" placeholder="{{ __('Exam series, e.g. November 2026') }}">
                    <input type="text" class="form-control mb-2" wire:model="centreNumber" placeholder="{{ __('Centre number') }}">
                    <label class="form-label small mb-0">{{ __('Opens on (optional)') }}</label>
                    <input type="date" class="form-control mb-2" wire:model="registrationOpensOn">
                    <label class="form-label small mb-0">{{ __('Closes on') }}</label>
                    <input type="date" class="form-control mb-2" wire:model="registrationClosesOn">
                    <select class="form-select mb-2" wire:model="currency">
                        <option value="USD">USD</option>
                        <option value="ZWG">ZWG</option>
                    </select>
                    <button type="button" class="btn btn-primary btn-sm" wire:click="create">{{ __('Create registration') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
