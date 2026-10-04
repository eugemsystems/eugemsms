<div>
    <h4 class="mb-1">{{ __('Budget builder') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Bottom-up, per account per cost centre, with a basis note for how each figure was derived.') }}</p>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header">{{ __('New budget') }}</div>
                <div class="card-body">
                    <input type="text" class="form-control mb-2" wire:model="name" placeholder="{{ __('Name') }}">
                    <select class="form-select mb-2" wire:model="budgetType">
                        <option value="operating">{{ __('Operating') }}</option>
                        <option value="capital">{{ __('Capital') }}</option>
                        <option value="project">{{ __('Project') }}</option>
                    </select>
                    <select class="form-select mb-2" wire:model="periodBasis">
                        <option value="annual">{{ __('Annual') }}</option>
                        <option value="termly">{{ __('Termly') }}</option>
                        <option value="monthly">{{ __('Monthly') }}</option>
                    </select>
                    <button type="button" class="btn btn-primary btn-sm" wire:click="createBudget">{{ __('Create') }}</button>
                </div>
            </div>
            <div class="card">
                <div class="card-header">{{ __('Budgets') }}</div>
                <div class="list-group list-group-flush">
                    @foreach ($budgets as $budget)
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <button type="button" class="btn btn-link p-0" wire:click="$set('budgetId', {{ $budget->id }})">{{ $budget->name }} v{{ $budget->version }} ({{ $budget->status }})</button>
                            @if (in_array($budget->status, ['active', 'approved'], true))
                                <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="revise({{ $budget->id }})">{{ __('Revise') }}</button>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">{{ __('Submit a line') }}</div>
                <div class="card-body">
                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <select class="form-select" wire:model="accountId">
                                <option value="">{{ __('Account') }}</option>
                                @foreach ($accounts as $account)
                                    <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <select class="form-select" wire:model="costCentreId">
                                <option value="">{{ __('Cost centre') }}</option>
                                @foreach ($costCentres as $cc)
                                    <option value="{{ $cc->id }}">{{ $cc->code }} — {{ $cc->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <input type="number" class="form-control mb-2" wire:model="annualAmountMinor" placeholder="{{ __('Annual amount (minor units)') }}">
                    <input type="text" class="form-control mb-2" wire:model="basisNote" placeholder="{{ __('Basis note') }}">
                    <button type="button" class="btn btn-primary btn-sm" wire:click="submitLine">{{ __('Save line') }}</button>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Account') }}</th><th>{{ __('Cost centre') }}</th><th>{{ __('Annual') }}</th><th>{{ __('Committed') }}</th><th>{{ __('Actual') }}</th><th>{{ __('Available') }}</th></tr></thead>
                        <tbody>
                            @forelse ($lines as $line)
                                <tr wire:key="line-{{ $line->id }}">
                                    <td>{{ $line->account_id }}</td>
                                    <td>{{ $line->cost_centre_id }}</td>
                                    <td>{{ number_format($line->annual_amount_minor / 100, 2) }}</td>
                                    <td>{{ number_format($line->committed_minor / 100, 2) }}</td>
                                    <td>{{ number_format($line->actual_minor / 100, 2) }}</td>
                                    <td>{{ number_format($line->available_minor / 100, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-body-secondary py-3">{{ __('Select a budget first.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
