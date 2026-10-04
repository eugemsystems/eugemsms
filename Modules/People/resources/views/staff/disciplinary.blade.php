<div>
    <h4 class="mb-1">{{ __('Disciplinary cases') }}</h4>
    <p class="text-body-secondary mb-4">{{ $staff->fullName() }} — {{ $staff->staff_number }}</p>

    <div class="row g-4">
        <div class="col-md-5">
            <div class="card mb-4">
                <div class="card-header">{{ __('Report a case') }}</div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-12">
                            <input type="text" class="form-control form-control-sm @error('category') is-invalid @enderror" wire:model="category" placeholder="{{ __('Category') }}">
                        </div>
                        <div class="col-12">
                            <textarea class="form-control form-control-sm @error('description') is-invalid @enderror" wire:model="description" rows="3" placeholder="{{ __('Description') }}"></textarea>
                        </div>
                        <div class="col-12">
                            <input type="date" class="form-control form-control-sm @error('incidentDate') is-invalid @enderror" wire:model="incidentDate">
                        </div>
                        <div class="col-12 form-check">
                            <input type="checkbox" class="form-check-input" id="isConfidential" wire:model="isConfidential">
                            <label class="form-check-label small" for="isConfidential">{{ __('Confidential (BR-PPL-04-020)') }}</label>
                        </div>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm mt-3" wire:click="report">{{ __('Report case') }}</button>
                </div>
            </div>

            @if ($viewedCase)
                <div class="card">
                    <div class="card-header">{{ __('Case :number', ['number' => $viewedCase['caseNumber']]) }}</div>
                    <div class="card-body">
                        <dl class="row mb-3">
                            <dt class="col-4">{{ __('Category') }}</dt><dd class="col-8">{{ $viewedCase['category'] }}</dd>
                            <dt class="col-4">{{ __('Stage') }}</dt><dd class="col-8">{{ ucfirst($viewedCase['stage']) }}</dd>
                            <dt class="col-4">{{ __('Outcome') }}</dt><dd class="col-8">{{ $viewedCase['outcome'] ?? '—' }}</dd>
                            <dt class="col-4">{{ __('Description') }}</dt><dd class="col-8">{{ $viewedCase['description'] }}</dd>
                        </dl>

                        <select class="form-select form-select-sm @error('targetStage') is-invalid @enderror" wire:model="targetStage">
                            <option value="">{{ __('Advance to...') }}</option>
                            <option value="investigation">{{ __('Investigation') }}</option>
                            <option value="hearing">{{ __('Hearing') }}</option>
                            <option value="decided">{{ __('Decided') }}</option>
                            <option value="appealed">{{ __('Appealed') }}</option>
                            <option value="closed">{{ __('Closed') }}</option>
                        </select>
                        @if ($targetStage === 'decided')
                            <select class="form-select form-select-sm mt-2" wire:model="outcome">
                                <option value="">{{ __('Outcome') }}</option>
                                <option value="no_case">{{ __('No case') }}</option>
                                <option value="verbal_warning">{{ __('Verbal warning') }}</option>
                                <option value="written_warning">{{ __('Written warning') }}</option>
                                <option value="final_warning">{{ __('Final warning') }}</option>
                                <option value="suspension">{{ __('Suspension') }}</option>
                                <option value="dismissal">{{ __('Dismissal') }}</option>
                            </select>
                            <input type="date" class="form-control form-control-sm mt-2" wire:model="outcomeDate">
                        @endif
                        <button type="button" class="btn btn-sm btn-primary mt-2" wire:click="advanceStage">{{ __('Advance stage') }}</button>
                    </div>
                </div>
            @endif
        </div>

        <div class="col-md-7">
            <div class="card">
                <div class="card-header">{{ __('Cases') }}</div>
                <p class="text-body-secondary small px-3 pt-3 mb-0">{{ __('Description and outcome are hidden until you open a case — opening one is logged.') }}</p>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Case #') }}</th><th>{{ __('Category') }}</th><th>{{ __('Stage') }}</th><th>{{ __('Incident date') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($cases as $case)
                                <tr wire:key="case-{{ $case->id }}">
                                    <td>{{ $case->case_number }}</td>
                                    <td>{{ $case->category }}</td>
                                    <td><span class="badge text-bg-secondary">{{ ucfirst($case->stage) }}</span></td>
                                    <td>{{ $case->incident_date->format('d M Y') }}</td>
                                    <td class="text-end"><button type="button" class="btn btn-sm btn-outline-secondary" wire:click="view({{ $case->id }})">{{ __('View') }}</button></td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-body-secondary py-4">{{ __('No cases reported.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
