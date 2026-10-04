<div>
    <h4 class="mb-1">{{ __('Malpractice') }}</h4>
    <div class="alert alert-danger">{{ __('Confidential — visible only to users granted the dedicated malpractice permissions, never the general exams.manage permission.') }}</div>

    <div class="row g-4">
        <div class="col-md-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Candidate') }}</th><th>{{ __('Type') }}</th><th>{{ __('Status') }}</th><th>{{ __('Outcome') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($incidents as $incident)
                                <tr wire:key="incident-{{ $incident->id }}">
                                    <td>{{ $incident->candidate?->student?->first_name }} {{ $incident->candidate?->student?->last_name }}</td>
                                    <td>{{ ucfirst(str_replace('_', ' ', $incident->incident_type)) }}</td>
                                    <td><span class="badge text-bg-secondary">{{ ucfirst($incident->status) }}</span></td>
                                    <td>{{ $incident->outcome ? ucfirst(str_replace('_', ' ', $incident->outcome)) : '—' }}</td>
                                    <td>
                                        @if ($incident->status !== 'decided' && $incident->status !== 'closed')
                                            <input type="text" class="form-control form-control-sm d-inline-block mb-1" style="width: 180px" wire:model="investigationNotes.{{ $incident->id }}" placeholder="{{ __('Investigation notes') }}">
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-secondary" wire:click="decide({{ $incident->id }}, 'no_case')">{{ __('No case') }}</button>
                                                <button type="button" class="btn btn-outline-warning" wire:click="decide({{ $incident->id }}, 'warning')">{{ __('Warning') }}</button>
                                                <button type="button" class="btn btn-outline-danger" wire:click="decide({{ $incident->id }}, 'paper_annulled')">{{ __('Annul paper') }}</button>
                                                <button type="button" class="btn btn-danger" wire:click="decide({{ $incident->id }}, 'session_annulled')">{{ __('Annul session') }}</button>
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-body-secondary py-4">{{ __('No incidents reported.') }}</td></tr>
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
                    <form wire:submit="report">
                        <div class="row g-3">
                            <div class="col-12">
                                <div class="form-floating form-floating-outline">
                                    <select class="form-select" wire:model="sessionId">
                                        <option value="">{{ __('Select session') }}</option>
                                        @foreach ($sessions as $session)
                                            <option value="{{ $session->id }}">{{ $session->name }}</option>
                                        @endforeach
                                    </select>
                                    <label>{{ __('Session') }}</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-floating form-floating-outline">
                                    <select class="form-select" wire:model="incidentType">
                                        <option value="unauthorised_material">{{ __('Unauthorised material') }}</option>
                                        <option value="copying">{{ __('Copying') }}</option>
                                        <option value="impersonation">{{ __('Impersonation') }}</option>
                                        <option value="disruption">{{ __('Disruption') }}</option>
                                        <option value="mobile_phone">{{ __('Mobile phone') }}</option>
                                        <option value="leaving_early">{{ __('Leaving early') }}</option>
                                        <option value="paper_leak">{{ __('Paper leak') }}</option>
                                    </select>
                                    <label>{{ __('Incident type') }}</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-floating form-floating-outline">
                                    <input type="datetime-local" class="form-control" wire:model="occurredAt">
                                    <label>{{ __('Occurred at') }}</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-floating form-floating-outline">
                                    <textarea class="form-control @error('description') is-invalid @enderror" wire:model="description" style="height: 80px"></textarea>
                                    <label>{{ __('Description') }}</label>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-danger mt-3" wire:loading.attr="disabled">{{ __('Report') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
