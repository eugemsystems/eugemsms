<div>
    <h4 class="mb-1">{{ __('Trigger rules') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Rules suggest a sanction to a named human by default. Automatic sanctioning requires the school setting to be turned on first.') }}</p>

    <div class="row g-4">
        <div class="col-md-7">
            <div class="card mb-3">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Type') }}</th><th>{{ __('Automatic') }}</th></tr></thead>
                        <tbody>
                            @forelse ($rules as $rule)
                                <tr wire:key="rule-{{ $rule->id }}">
                                    <td>{{ $rule->name }}</td>
                                    <td>{{ str_replace('_', ' ', $rule->trigger_type) }}</td>
                                    <td>{{ $rule->is_automatic ? __('Yes') : __('No') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-body-secondary py-3">{{ __('No trigger rules.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header">{{ __('Evaluate for a student') }}</div>
                <div class="card-body d-flex gap-2">
                    <select class="form-select" wire:model="evaluatingStudentId">
                        <option value="">{{ __('Student') }}</option>
                        @foreach ($students as $student)
                            <option value="{{ $student->id }}">{{ $student->first_name }} {{ $student->last_name }}</option>
                        @endforeach
                    </select>
                    <button type="button" class="btn btn-outline-primary" wire:click="evaluate">{{ __('Evaluate') }}</button>
                </div>
            </div>
        </div>

        <div class="col-md-5">
            <div class="card">
                <div class="card-header">{{ __('New rule') }}</div>
                <div class="card-body">
                    <input type="text" class="form-control mb-2" wire:model="name" placeholder="{{ __('Name') }}">
                    <select class="form-select mb-2" wire:model.live="triggerType">
                        <option value="points_threshold">{{ __('Points threshold') }}</option>
                        <option value="repeat_category">{{ __('Repeat category') }}</option>
                        <option value="severity_single">{{ __('Single severe occurrence') }}</option>
                    </select>
                    <select class="form-select mb-2" wire:model="suggestedSanctionId">
                        <option value="">{{ __('Suggested sanction') }}</option>
                        @foreach ($sanctionTypes as $type)
                            <option value="{{ $type->id }}">{{ $type->name }}</option>
                        @endforeach
                    </select>
                    @if ($triggerType === 'points_threshold')
                        <input type="number" class="form-control mb-2" wire:model="demeritThreshold" placeholder="{{ __('Demerit threshold') }}">
                        <input type="number" class="form-control mb-2" wire:model="windowDays" placeholder="{{ __('Window (days, optional)') }}">
                    @else
                        <select class="form-select mb-2" wire:model="categoryId">
                            <option value="">{{ __('Category') }}</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                        @if ($triggerType === 'repeat_category')
                            <input type="number" class="form-control mb-2" wire:model="repeatCount" placeholder="{{ __('Repeat count') }}">
                            <input type="number" class="form-control mb-2" wire:model="windowDays" placeholder="{{ __('Window (days, optional)') }}">
                        @endif
                    @endif
                    <div class="form-check mb-2">
                        <input type="checkbox" class="form-check-input" id="isAutomatic" wire:model="isAutomatic">
                        <label class="form-check-label" for="isAutomatic">{{ __('Automatic (requires the school setting on first)') }}</label>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm" wire:click="create">{{ __('Create') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
