<div>
    <h4 class="mb-3">{{ __('Staff pay structure') }}</h4>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card mb-3">
                <div class="card-header">{{ __('New structure') }}</div>
                <div class="card-body">
                    <select class="form-select mb-2" wire:model="staffId">
                        <option value="">{{ __('Staff member') }}</option>
                        @foreach ($staff as $member)
                            <option value="{{ $member->id }}">{{ $member->first_name }} {{ $member->last_name }}</option>
                        @endforeach
                    </select>
                    <select class="form-select mb-2" wire:model="gradeId">
                        <option value="">{{ __('Pay grade (optional)') }}</option>
                        @foreach ($grades as $grade)
                            <option value="{{ $grade->id }}">{{ $grade->code }} — {{ $grade->name }}</option>
                        @endforeach
                    </select>
                    <input type="text" class="form-control mb-2" wire:model="notch" placeholder="{{ __('Notch (optional)') }}">
                    <select class="form-select mb-2" wire:model="primaryCurrency">
                        <option value="USD">{{ __('Primary currency: USD') }}</option>
                        <option value="ZWG">{{ __('Primary currency: ZWG') }}</option>
                    </select>
                    <select class="form-select mb-2" wire:model="paymentCurrency">
                        <option value="USD">{{ __('Payment currency: USD') }}</option>
                        <option value="ZWG">{{ __('Payment currency: ZWG') }}</option>
                    </select>
                    <input type="date" class="form-control mb-2" wire:model="effectiveFrom">
                    <button type="button" class="btn btn-primary btn-sm" wire:click="create">{{ __('Create structure') }}</button>
                    <div class="form-text">{{ __('Any existing active structure for this staff member is superseded, never overwritten.') }}</div>
                </div>
            </div>

            @if ($selectedStructureId)
                <div class="card">
                    <div class="card-header">{{ __('Add component to structure #') }}{{ $selectedStructureId }}</div>
                    <div class="card-body">
                        <select class="form-select mb-2" wire:model="componentId">
                            <option value="">{{ __('Component') }}</option>
                            @foreach ($components as $component)
                                <option value="{{ $component->id }}">{{ $component->code }} — {{ $component->name }}</option>
                            @endforeach
                        </select>
                        <input type="number" class="form-control mb-2" wire:model="componentAmountMinor" placeholder="{{ __('Amount (minor units, if fixed)') }}">
                        <input type="text" class="form-control mb-2" wire:model="componentPercent" placeholder="{{ __('Percent (if percentage_of_basic)') }}">
                        <select class="form-select mb-2" wire:model="componentCurrency">
                            <option value="USD">USD</option>
                            <option value="ZWG">ZWG</option>
                        </select>
                        <input type="date" class="form-control mb-2" wire:model="componentEffectiveFrom">
                        <button type="button" class="btn btn-outline-primary btn-sm" wire:click="addComponent">{{ __('Add component') }}</button>
                    </div>
                </div>
            @endif
        </div>
        <div class="col-lg-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('#') }}</th><th>{{ __('Staff') }}</th><th>{{ __('Currency') }}</th><th>{{ __('Effective') }}</th><th>{{ __('Status') }}</th><th>{{ __('Components') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($structures as $structure)
                                <tr wire:key="structure-{{ $structure->id }}">
                                    <td>{{ $structure->id }}</td>
                                    <td>{{ $structure->staff_id }}</td>
                                    <td>{{ $structure->payment_currency }}</td>
                                    <td>{{ $structure->effective_from->toDateString() }}</td>
                                    <td><span class="badge {{ $structure->status === 'active' ? 'bg-success' : 'bg-secondary' }}">{{ $structure->status }}</span></td>
                                    <td>{{ $structure->components->count() }}</td>
                                    <td><button type="button" class="btn btn-outline-secondary btn-sm" wire:click="selectStructure({{ $structure->id }})">{{ __('Select') }}</button></td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-center text-body-secondary py-3">{{ __('No pay structures yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
