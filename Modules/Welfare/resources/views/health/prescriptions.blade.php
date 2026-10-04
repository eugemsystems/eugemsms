<div>
    <h4 class="mb-1">{{ __('Prescriptions') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Create-only. Requires a valid, unwithdrawn prescribed_medication consent (BR-BRD-06-012).') }}</p>

    <div class="row g-4">
        <div class="col-md-7">
            <div class="card">
                <div class="card-header">{{ __('Prescriptions') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Student') }}</th><th>{{ __('Medication') }}</th><th>{{ __('Dose') }}</th><th>{{ __('Status') }}</th></tr></thead>
                        <tbody>
                            @forelse ($prescriptions as $prescription)
                                <tr wire:key="prescription-{{ $prescription->id }}">
                                    <td>{{ $prescription->student?->first_name }} {{ $prescription->student?->last_name }}</td>
                                    <td>{{ $prescription->medication_name }}</td>
                                    <td>{{ $prescription->dose }} ({{ $prescription->frequency }})</td>
                                    <td><span class="badge text-bg-secondary">{{ $prescription->status }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('No prescriptions.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-5">
            <div class="card">
                <div class="card-header">{{ __('New prescription') }}</div>
                <div class="card-body">
                    <select class="form-select mb-2" wire:model.live="studentId">
                        <option value="">{{ __('Student') }}</option>
                        @foreach ($students as $student)
                            <option value="{{ $student->id }}">{{ $student->first_name }} {{ $student->last_name }}</option>
                        @endforeach
                    </select>
                    @if ($studentId)
                        <select class="form-select mb-2" wire:model="guardianConsentId">
                            <option value="">{{ __('Guardian consent') }}</option>
                            @foreach ($consents as $consent)
                                <option value="{{ $consent->id }}">{{ __('Consent #:id — granted :date', ['id' => $consent->id, 'date' => $consent->granted_at->toDateString()]) }}</option>
                            @endforeach
                        </select>
                        @if ($consents->isEmpty())
                            <p class="small text-danger">{{ __('No valid prescribed_medication consent exists — grant one in Consents first.') }}</p>
                        @endif
                    @endif
                    <input type="text" class="form-control mb-2" wire:model="medicationName" placeholder="{{ __('Medication name') }}">
                    <input type="text" class="form-control mb-2" wire:model="dose" placeholder="{{ __('Dose') }}">
                    <input type="text" class="form-control mb-2" wire:model="frequency" placeholder="{{ __('Frequency') }}">
                    <select class="form-select mb-2" wire:model="route">
                        <option value="oral">{{ __('Oral') }}</option>
                        <option value="topical">{{ __('Topical') }}</option>
                        <option value="inhaled">{{ __('Inhaled') }}</option>
                        <option value="injection">{{ __('Injection') }}</option>
                        <option value="rectal">{{ __('Rectal') }}</option>
                    </select>
                    <input type="text" class="form-control mb-2" wire:model="prescribedBy" placeholder="{{ __('Prescribed by (external practitioner)') }}">
                    <input type="date" class="form-control mb-2" wire:model="prescribedOn">
                    <input type="date" class="form-control mb-2" wire:model="startsOn">
                    <input type="date" class="form-control mb-2" wire:model="endsOn">
                    <div class="form-check mb-1">
                        <input type="checkbox" class="form-check-input" id="isPrn" wire:model="isPrn">
                        <label class="form-check-label" for="isPrn">{{ __('As required (PRN)') }}</label>
                    </div>
                    <div class="form-check mb-2">
                        <input type="checkbox" class="form-check-input" id="isSelfAdministered" wire:model="isSelfAdministered">
                        <label class="form-check-label" for="isSelfAdministered">{{ __('Self-administered (inhaler, EpiPen)') }}</label>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm" wire:click="create">{{ __('Create') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
