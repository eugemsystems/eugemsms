<div>
    <div class="mb-4"><h4 class="mb-0">{{ __('Feature rollouts') }}</h4><p class="text-body-secondary small mb-0">{{ __('Pilot on named tenants, then a cohort, then a percentage, then everyone — one deliberate step at a time. Nothing escalates on its own.') }}</p></div>
    <div class="row g-4">
        <div class="col-xl-8">
            <div class="card"><div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>{{ __('Feature') }}</th><th>{{ __('Stage') }}</th><th>{{ __('Pilot tenants') }}</th><th>{{ __('Started') }}</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($rollouts as $rollout)
                            <tr wire:key="ro-{{ $rollout->id }}">
                                <td>{{ $rollout->feature_flag_key }}<div class="small text-body-secondary">{{ $rollout->notes }}</div></td>
                                <td><span class="badge text-bg-{{ $rollout->rollout_stage === 'general' ? 'success' : 'info' }}">{{ __(ucfirst($rollout->rollout_stage)) }}</span>@if ($rollout->percentage) {{ $rollout->percentage }}% @endif</td>
                                <td class="small">{{ count($rollout->pilot_tenant_ids ?? []) }}</td>
                                <td class="small">{{ $rollout->started_at?->toFormattedDateString() }}</td>
                                <td class="text-end">@if ($rollout->rollout_stage !== 'general') <button type="button" class="btn btn-sm btn-outline-primary" wire:click="beginAdvance({{ $rollout->id }})">{{ __('Advance') }}</button> @endif</td>
                            </tr>
                            @if ($advancingId === $rollout->id)
                                @php($next = $stageOrder[array_search($rollout->rollout_stage, $stageOrder, true) + 1] ?? null)
                                <tr wire:key="ro-adv-{{ $rollout->id }}"><td colspan="5">
                                    <div class="small mb-2">{{ __('Advance to') }} <strong>{{ $next }}</strong>.</div>
                                    @if ($next === 'cohort')
                                        <select multiple class="form-select form-select-sm mb-2" size="6" wire:model="cohortTenantIds">@foreach ($tenants as $tenant) <option value="{{ $tenant->id }}">{{ $tenant->name }}</option> @endforeach</select>
                                    @elseif ($next === 'percentage')
                                        <input type="number" min="1" max="99" class="form-control form-control-sm w-auto mb-2" wire:model="percentage" placeholder="%">
                                    @endif
                                    @error('advancingId') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                                    <button type="button" class="btn btn-primary btn-sm" wire:click="advance" wire:confirm="{{ __('Advance this rollout? More tenants will see the feature.') }}">{{ __('Confirm advance') }}</button>
                                </td></tr>
                            @endif
                        @empty
                            <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No rollouts.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div></div>
        </div>
        <div class="col-xl-4">
            <div class="card"><div class="card-header">{{ __('Start a pilot') }}</div><div class="card-body">
                <select class="form-select form-select-sm mb-2" wire:model="flagKey"><option value="">{{ __('Feature flag…') }}</option>@foreach ($flags as $flag) <option value="{{ $flag->key }}">{{ $flag->name }} ({{ $flag->key }})</option> @endforeach</select>
                @error('flagKey') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                <select multiple class="form-select form-select-sm mb-2" size="6" wire:model="pilotTenantIds">@foreach ($tenants as $tenant) <option value="{{ $tenant->id }}">{{ $tenant->name }}</option> @endforeach</select>
                @error('pilotTenantIds') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                <input type="text" class="form-control form-control-sm mb-2" wire:model="notes" placeholder="{{ __('Notes (optional)') }}">
                <button type="button" class="btn btn-primary btn-sm" wire:click="start">{{ __('Start pilot') }}</button>
            </div></div>
        </div>
    </div>
</div>
