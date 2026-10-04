<div>
    <h4 class="mb-1">{{ __('Sick bay') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Admit, observe, discharge. An admission pre-populates the roll call as sick_bay (BR-BRD-06-015).') }}</p>

    <div class="row g-4">
        <div class="col-md-7">
            @forelse ($admissions as $admission)
                <div class="card mb-3" wire:key="admission-{{ $admission->id }}">
                    <div class="card-header d-flex justify-content-between">
                        <span>{{ $admission->student?->first_name }} {{ $admission->student?->last_name }} — {{ $admission->presenting_complaint }}</span>
                        <span class="badge text-bg-{{ in_array($admission->severity, ['serious', 'emergency'], true) ? 'danger' : 'secondary' }}">{{ $admission->severity }}</span>
                    </div>
                    <div class="card-body">
                        <div class="small text-body-secondary mb-2">{{ __('Admitted') }} {{ $admission->admitted_at->diffForHumans() }} · {{ __('status') }}: {{ $admission->status }}</div>

                        @if ($admission->observations->isNotEmpty())
                            <table class="table table-sm mb-2">
                                <thead><tr><th>{{ __('Time') }}</th><th>{{ __('Temp') }}</th><th>{{ __('Pulse') }}</th><th>{{ __('Notes') }}</th></tr></thead>
                                <tbody>
                                    @foreach ($admission->observations as $obs)
                                        <tr><td>{{ $obs->observed_at->format('H:i') }}</td><td>{{ $obs->temperature_c }}</td><td>{{ $obs->pulse_bpm }}</td><td>{{ $obs->notes }}</td></tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif

                        <div class="row g-2 mb-2">
                            <div class="col-3"><input type="number" step="0.1" class="form-control form-control-sm" wire:model="temperatureC" placeholder="{{ __('Temp °C') }}"></div>
                            <div class="col-3"><input type="number" class="form-control form-control-sm" wire:model="pulseBpm" placeholder="{{ __('Pulse') }}"></div>
                            <div class="col-4"><input type="text" class="form-control form-control-sm" wire:model="notes" placeholder="{{ __('Notes') }}"></div>
                            <div class="col-2"><button type="button" class="btn btn-sm btn-outline-primary w-100" wire:click="observe({{ $admission->id }})">{{ __('Log') }}</button></div>
                        </div>

                        @if ($viewingAdmissionId === $admission->id)
                            <div class="row g-2">
                                <div class="col-4">
                                    <select class="form-select form-select-sm" wire:model="dischargeDestination">
                                        <option value="hostel">{{ __('Hostel') }}</option>
                                        <option value="home">{{ __('Home') }}</option>
                                        <option value="hospital">{{ __('Hospital') }}</option>
                                        <option value="clinic">{{ __('Clinic') }}</option>
                                    </select>
                                </div>
                                <div class="col-5"><input type="text" class="form-control form-control-sm" wire:model="dischargeNotes" placeholder="{{ __('Discharge notes') }}"></div>
                                <div class="col-3"><button type="button" class="btn btn-sm btn-success w-100" wire:click="discharge({{ $admission->id }})">{{ __('Confirm discharge') }}</button></div>
                            </div>
                        @else
                            <button type="button" class="btn btn-sm btn-outline-success" wire:click="$set('viewingAdmissionId', {{ $admission->id }})">{{ __('Discharge') }}</button>
                        @endif
                    </div>
                </div>
            @empty
                <p class="text-body-secondary">{{ __('No one currently admitted.') }}</p>
            @endforelse
        </div>

        <div class="col-md-5">
            <div class="card">
                <div class="card-header">{{ __('Admit') }}</div>
                <div class="card-body">
                    <select class="form-select mb-2" wire:model="studentId">
                        <option value="">{{ __('Student') }}</option>
                        @foreach ($students as $student)
                            <option value="{{ $student->id }}">{{ $student->first_name }} {{ $student->last_name }}</option>
                        @endforeach
                    </select>
                    <input type="text" class="form-control mb-2" wire:model="presentingComplaint" placeholder="{{ __('Presenting complaint') }}">
                    <select class="form-select mb-2" wire:model="severity">
                        <option value="minor">{{ __('Minor') }}</option>
                        <option value="moderate">{{ __('Moderate') }}</option>
                        <option value="serious">{{ __('Serious') }}</option>
                        <option value="emergency">{{ __('Emergency') }}</option>
                    </select>
                    <div class="form-check mb-2">
                        <input type="checkbox" class="form-check-input" id="isIsolation" wire:model.live="isIsolation">
                        <label class="form-check-label" for="isIsolation">{{ __('Isolation') }}</label>
                    </div>
                    @if ($isIsolation)
                        <input type="text" class="form-control mb-2" wire:model="isolationReason" placeholder="{{ __('Isolation reason') }}">
                    @endif
                    <button type="button" class="btn btn-primary btn-sm" wire:click="admit">{{ __('Admit') }}</button>
                    <p class="small text-body-secondary mt-2 mb-0">{{ __('A serious/emergency admission notifies the guardian immediately — quiet hours and cost caps never suppress it.') }}</p>
                </div>
            </div>
        </div>
    </div>
</div>
