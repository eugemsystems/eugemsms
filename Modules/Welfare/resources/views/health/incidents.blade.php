<div>
    <h4 class="mb-1">{{ __('Health incidents') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('A head injury is always at least moderate severity and always notifies the guardian (BR-BRD-06-021).') }}</p>

    <div class="row g-4">
        <div class="col-md-7">
            <div class="card">
                <div class="card-header">{{ __('Reported incidents') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Student') }}</th><th>{{ __('Type') }}</th><th>{{ __('Severity') }}</th><th>{{ __('Reportable') }}</th></tr></thead>
                        <tbody>
                            @forelse ($incidents as $incident)
                                <tr wire:key="incident-{{ $incident->id }}">
                                    <td>{{ $incident->student?->first_name }} {{ $incident->student?->last_name }}</td>
                                    <td>{{ str_replace('_', ' ', $incident->incident_type) }}</td>
                                    <td><span class="badge text-bg-{{ in_array($incident->severity, ['serious', 'critical'], true) ? 'danger' : 'secondary' }}">{{ $incident->severity }}</span></td>
                                    <td>{{ $incident->is_reportable ? __('Yes') : __('No') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('No incidents reported.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-5">
            <div class="card">
                <div class="card-header">{{ __('Report incident') }}</div>
                <div class="card-body">
                    <select class="form-select mb-2" wire:model="studentId">
                        <option value="">{{ __('Student') }}</option>
                        @foreach ($students as $student)
                            <option value="{{ $student->id }}">{{ $student->first_name }} {{ $student->last_name }}</option>
                        @endforeach
                    </select>
                    <select class="form-select mb-2" wire:model="incidentType">
                        <option value="sports_injury">{{ __('Sports injury') }}</option>
                        <option value="fall">{{ __('Fall') }}</option>
                        <option value="burn">{{ __('Burn') }}</option>
                        <option value="cut">{{ __('Cut') }}</option>
                        <option value="fracture">{{ __('Fracture') }}</option>
                        <option value="head_injury">{{ __('Head injury') }}</option>
                        <option value="collapse">{{ __('Collapse') }}</option>
                        <option value="bite">{{ __('Bite') }}</option>
                        <option value="poisoning">{{ __('Poisoning') }}</option>
                    </select>
                    <input type="text" class="form-control mb-2" wire:model="location" placeholder="{{ __('Location') }}">
                    <input type="text" class="form-control mb-2" wire:model="activityAtTime" placeholder="{{ __('Activity at the time (optional)') }}">
                    <textarea class="form-control mb-2" wire:model="description" placeholder="{{ __('Description') }}"></textarea>
                    <textarea class="form-control mb-2" wire:model="firstAidGiven" placeholder="{{ __('First aid given (optional)') }}"></textarea>
                    <select class="form-select mb-2" wire:model="severity">
                        <option value="minor">{{ __('Minor') }}</option>
                        <option value="moderate">{{ __('Moderate') }}</option>
                        <option value="serious">{{ __('Serious') }}</option>
                        <option value="critical">{{ __('Critical') }}</option>
                    </select>
                    <button type="button" class="btn btn-primary btn-sm" wire:click="report">{{ __('Report') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
