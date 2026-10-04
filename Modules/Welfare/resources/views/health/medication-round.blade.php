<div>
    <h4 class="mb-1">{{ __('Medication round') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Consent is checked server-side on every record. Controlled stock requires a second, different witness.') }}</p>

    <div class="card" style="max-width: 640px">
        <div class="card-body">
            <select class="form-select mb-2" wire:model.live="studentId">
                <option value="">{{ __('Student') }}</option>
                @foreach ($students as $student)
                    <option value="{{ $student->id }}">{{ $student->first_name }} {{ $student->last_name }}</option>
                @endforeach
            </select>

            @if ($studentId)
                <select class="form-select mb-2" wire:model.live="prescriptionId">
                    <option value="">{{ __('No prescription — OTC / standing consent') }}</option>
                    @foreach ($prescriptions as $prescription)
                        <option value="{{ $prescription->id }}">{{ $prescription->medication_name }} — {{ $prescription->dose }} ({{ $prescription->frequency }})</option>
                    @endforeach
                </select>
            @endif

            <div class="row g-2 mb-2">
                <div class="col-5"><input type="text" class="form-control" wire:model="medicationName" placeholder="{{ __('Medication name') }}"></div>
                <div class="col-3"><input type="text" class="form-control" wire:model="dose" placeholder="{{ __('Dose') }}"></div>
                <div class="col-4">
                    <select class="form-select" wire:model="route">
                        <option value="oral">{{ __('Oral') }}</option>
                        <option value="topical">{{ __('Topical') }}</option>
                        <option value="inhaled">{{ __('Inhaled') }}</option>
                        <option value="injection">{{ __('Injection') }}</option>
                        <option value="rectal">{{ __('Rectal') }}</option>
                    </select>
                </div>
            </div>

            <select class="form-select mb-2" wire:model="clinicStockId">
                <option value="">{{ __('Clinic stock (optional)') }}</option>
                @foreach ($stock as $item)
                    <option value="{{ $item->id }}">{{ $item->name }} {{ $item->is_controlled ? '⚠ controlled' : '' }} {{ $item->isExpired() ? '— EXPIRED' : '' }}</option>
                @endforeach
            </select>

            <input type="number" class="form-control mb-2" wire:model="witnessedByUserId" placeholder="{{ __('Witness user id (required for controlled stock)') }}">

            <select class="form-select mb-2" wire:model.live="outcome">
                <option value="given">{{ __('Given') }}</option>
                <option value="refused_by_learner">{{ __('Refused by learner') }}</option>
                <option value="omitted">{{ __('Omitted') }}</option>
                <option value="vomited">{{ __('Vomited') }}</option>
            </select>
            @if ($outcome !== 'given')
                <input type="text" class="form-control mb-2" wire:model="omissionReason" placeholder="{{ __('Reason — required, silence is not an acceptable record') }}">
            @endif

            <div class="form-check mb-2">
                <input type="checkbox" class="form-check-input" id="emergencyProvision" wire:model="emergencyProvision">
                <label class="form-check-label" for="emergencyProvision">{{ __('Emergency provision — proceeding without consent to save a life') }}</label>
            </div>

            <button type="button" class="btn btn-primary" wire:click="administer">{{ __('Record administration') }}</button>
        </div>
    </div>
</div>
