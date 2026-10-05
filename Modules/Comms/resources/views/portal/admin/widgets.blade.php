<div>
    <h4 class="mb-1">{{ __('Portal widgets') }}</h4>
    <p class="text-body-secondary small">{{ __('Choose which tiles each portal dashboard shows and in what order. Widgets for modules your school has not enabled are not offered. A widget with no saved setting shows its default.') }}</p>

    <div class="d-flex gap-2 mb-3 align-items-center">
        <select class="form-select form-select-sm w-auto" wire:model.live="persona">
            <option value="parent">{{ __('Parent') }}</option>
            <option value="learner">{{ __('Learner') }}</option>
            <option value="staff">{{ __('Staff') }}</option>
        </select>
        <span class="small text-body-secondary">{{ __('At most :max enabled per persona.', ['max' => $max]) }}</span>
    </div>

    @if ($persona === 'learner')
        <div class="alert alert-info small">{{ __('The safeguarding “tell someone” entry point is always present on every learner dashboard. It is not a widget and cannot be disabled here. Widgets also stay hidden below their own minimum grade.') }}</div>
    @endif

    @error('rows') <div class="alert alert-danger small">{{ $message }}</div> @enderror

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Widget') }}</th><th>{{ __('Module') }}</th><th>{{ __('Min. grade') }}</th><th>{{ __('Enabled') }}</th><th style="width: 7rem">{{ __('Order') }}</th></tr></thead>
                <tbody>
                    @forelse ($widgets as $widget)
                        <tr wire:key="widget-{{ $persona }}-{{ $widget->key }}">
                            <td>{{ $widget->title }} <span class="text-body-secondary small">({{ $widget->key }})</span></td>
                            <td class="small">{{ $widget->moduleCode }}</td>
                            <td class="small">{{ $widget->minGradeOrdinal ?? '—' }}</td>
                            <td>
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" id="w-{{ $widget->key }}" wire:model="rows.{{ $widget->key }}.enabled" @disabled(! $canManage)>
                                </div>
                            </td>
                            <td><input type="number" class="form-control form-control-sm" min="0" max="999" wire:model="rows.{{ $widget->key }}.sort" @disabled(! $canManage)></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No configurable widgets for this persona.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($unavailableCount > 0)
            <div class="card-footer small text-body-secondary">{{ trans_choice(':count widget is hidden because its module is not enabled for this school.|:count widgets are hidden because their modules are not enabled for this school.', $unavailableCount, ['count' => $unavailableCount]) }}</div>
        @endif
    </div>

    @if ($canManage)
        <button type="button" class="btn btn-primary btn-sm mt-3" wire:click="save">{{ __('Save configuration') }}</button>
    @endif
</div>
