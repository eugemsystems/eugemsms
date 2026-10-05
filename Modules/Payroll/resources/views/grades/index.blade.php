<div>
    <h4 class="mb-3">{{ __('Pay grades') }}</h4>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Code') }}</th><th>{{ __('Name') }}</th><th>{{ __('Category') }}</th><th>{{ __('Range') }}</th><th>{{ __('Notches') }}</th></tr></thead>
                        <tbody>
                            @forelse ($grades as $grade)
                                <tr wire:key="grade-{{ $grade->id }}">
                                    <td>{{ $grade->code }}</td>
                                    <td>{{ $grade->name }}</td>
                                    <td>{{ $grade->category }}</td>
                                    <td>{{ $grade->currency }} {{ number_format((int) $grade->min_salary_minor / 100, 2) }}–{{ number_format((int) $grade->max_salary_minor / 100, 2) }}</td>
                                    <td>
                                        @forelse ($grade->notches as $notch)
                                            <span class="badge bg-light text-dark border">{{ $notch->notch }}: {{ number_format($notch->basic_salary_minor / 100, 2) }} {{ $notch->currency }}</span>
                                        @empty
                                            <span class="text-body-secondary">{{ __('none') }}</span>
                                        @endforelse
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No pay grades yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card mb-3">
                <div class="card-header">{{ __('New pay grade') }}</div>
                <div class="card-body">
                    <input type="text" class="form-control mb-2" wire:model="code" placeholder="{{ __('Code') }}">
                    <input type="text" class="form-control mb-2" wire:model="name" placeholder="{{ __('Name') }}">
                    <select class="form-select mb-2" wire:model="category">
                        <option value="teaching">{{ __('Teaching') }}</option>
                        <option value="administration">{{ __('Administration') }}</option>
                        <option value="support">{{ __('Support') }}</option>
                        <option value="management">{{ __('Management') }}</option>
                        <option value="ancillary">{{ __('Ancillary') }}</option>
                    </select>
                    <select class="form-select mb-2" wire:model="currency">
                        <option value="USD">USD</option>
                        <option value="ZWG">ZWG</option>
                    </select>
                    <input type="number" class="form-control mb-2" wire:model="minSalaryMinor" placeholder="{{ __('Min salary (minor units)') }}">
                    <input type="number" class="form-control mb-2" wire:model="maxSalaryMinor" placeholder="{{ __('Max salary (minor units)') }}">
                    <input type="text" class="form-control mb-2" wire:model="necGradeReference" placeholder="{{ __('NEC grade reference (optional)') }}">
                    <button type="button" class="btn btn-primary btn-sm" wire:click="create">{{ __('Create grade') }}</button>
                </div>
            </div>
            <div class="card">
                <div class="card-header">{{ __('Add notch') }}</div>
                <div class="card-body">
                    <select class="form-select mb-2" wire:model="notchGradeId">
                        <option value="">{{ __('Grade') }}</option>
                        @foreach ($grades as $grade)
                            <option value="{{ $grade->id }}">{{ $grade->code }} — {{ $grade->name }}</option>
                        @endforeach
                    </select>
                    <input type="text" class="form-control mb-2" wire:model="notchLabel" placeholder="{{ __('Notch label') }}">
                    <input type="number" class="form-control mb-2" wire:model="notchBasicSalaryMinor" placeholder="{{ __('Basic salary (minor units)') }}">
                    <select class="form-select mb-2" wire:model="notchCurrency">
                        <option value="USD">USD</option>
                        <option value="ZWG">ZWG</option>
                    </select>
                    <input type="date" class="form-control mb-2" wire:model="notchEffectiveFrom">
                    <button type="button" class="btn btn-outline-primary btn-sm" wire:click="addNotch">{{ __('Add notch') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
