<div>
    <h4 class="mb-1">{{ __('Period structures') }}</h4>
    <p class="text-body-secondary mb-4">{{ $school->name }}</p>

    <div class="row g-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">{{ __('Structures') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Cycle') }}</th><th>{{ __('Slots') }}</th><th>{{ __('Default') }}</th></tr></thead>
                        <tbody>
                            @forelse ($structures as $structure)
                                <tr wire:key="structure-{{ $structure->id }}">
                                    <td>{{ $structure->name }}</td>
                                    <td>{{ $structure->cycle_type }} ({{ $structure->cycle_days }})</td>
                                    <td>{{ $structure->slots->count() }}</td>
                                    <td>@if ($structure->is_default)<span class="badge text-bg-success">{{ __('Default') }}</span>@endif</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-body-secondary py-4">{{ __('No period structures yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card">
                <div class="card-header">{{ __('New period structure') }}</div>
                <div class="card-body">
                    <form wire:submit="create">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <input type="text" class="form-control @error('name') is-invalid @enderror" wire:model="name" placeholder=" ">
                                    <label>{{ __('Name') }}</label>
                                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <select class="form-select" wire:model="sectionId">
                                        <option value="">{{ __('All sections') }}</option>
                                        @foreach ($sections as $section)
                                            <option value="{{ $section->id }}">{{ $section->name }}</option>
                                        @endforeach
                                    </select>
                                    <label>{{ __('Section') }}</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <select class="form-select" wire:model="cycleType">
                                        <option value="weekly">{{ __('Weekly') }}</option>
                                        <option value="six_day">{{ __('Six-day') }}</option>
                                        <option value="two_week">{{ __('Two-week') }}</option>
                                        <option value="ten_day">{{ __('Ten-day') }}</option>
                                    </select>
                                    <label>{{ __('Cycle type') }}</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <input type="number" class="form-control" wire:model="cycleDays" min="1" max="14">
                                    <label>{{ __('Cycle days') }}</label>
                                </div>
                            </div>
                            <div class="col-12 form-check">
                                <input type="checkbox" class="form-check-input" wire:model="isDefault" id="isDefault">
                                <label class="form-check-label" for="isDefault">{{ __('Default structure') }}</label>
                            </div>
                        </div>

                        <hr>
                        <h6>{{ __('Slots') }}</h6>
                        @foreach ($slotRows as $index => $slot)
                            <div class="row g-2 mb-2 align-items-end" wire:key="slot-{{ $index }}">
                                <div class="col-md-1">
                                    <label class="form-label small">{{ __('Day') }}</label>
                                    <input type="number" class="form-control form-control-sm" wire:model="slotRows.{{ $index }}.cycle_day" min="1">
                                </div>
                                <div class="col-md-1">
                                    <label class="form-label small">{{ __('#') }}</label>
                                    <input type="number" class="form-control form-control-sm" wire:model="slotRows.{{ $index }}.period_number" min="1">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small">{{ __('Label') }}</label>
                                    <input type="text" class="form-control form-control-sm" wire:model="slotRows.{{ $index }}.label">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small">{{ __('Type') }}</label>
                                    <select class="form-select form-select-sm" wire:model="slotRows.{{ $index }}.slot_type">
                                        <option value="teaching">{{ __('Teaching') }}</option>
                                        <option value="break">{{ __('Break') }}</option>
                                        <option value="assembly">{{ __('Assembly') }}</option>
                                        <option value="registration">{{ __('Registration') }}</option>
                                        <option value="prep">{{ __('Prep') }}</option>
                                        <option value="games">{{ __('Games') }}</option>
                                        <option value="chapel">{{ __('Chapel') }}</option>
                                        <option value="activity">{{ __('Activity') }}</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small">{{ __('Starts') }}</label>
                                    <input type="time" class="form-control form-control-sm" wire:model="slotRows.{{ $index }}.starts_at">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small">{{ __('Ends') }}</label>
                                    <input type="time" class="form-control form-control-sm" wire:model="slotRows.{{ $index }}.ends_at">
                                </div>
                                <div class="col-md-1">
                                    <label class="form-label small">{{ __('Mins') }}</label>
                                    <input type="number" class="form-control form-control-sm" wire:model="slotRows.{{ $index }}.duration_minutes">
                                </div>
                                <div class="col-md-1">
                                    <button type="button" class="btn btn-sm btn-outline-danger" wire:click="removeSlot({{ $index }})">&times;</button>
                                </div>
                            </div>
                        @endforeach
                        <button type="button" class="btn btn-sm btn-outline-secondary mb-3" wire:click="addSlot">{{ __('+ Add slot') }}</button>

                        <div>
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">{{ __('Create structure') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
