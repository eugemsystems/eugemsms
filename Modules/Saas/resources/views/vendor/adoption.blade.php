<div>
    <div class="mb-4"><h4 class="mb-0">{{ __('Adoption') }}</h4><p class="text-body-secondary small mb-0">{{ __('Measured from recorded activity — a posted journal, a taken roll call — never from a survey.') }}</p></div>
    <div class="d-flex flex-wrap gap-2 mb-3">
        <select class="form-select form-select-sm w-auto" wire:model.live="tenantId"><option value="">{{ __('Tenant…') }}</option>@foreach ($tenants as $tenant) <option value="{{ $tenant->id }}">{{ $tenant->name }}</option> @endforeach</select>
        <select class="form-select form-select-sm w-auto" wire:model.live="schoolId"><option value="">{{ __('School…') }}</option>@foreach ($schools as $school) <option value="{{ $school->id }}">{{ $school->name }}</option> @endforeach</select>
        <input type="month" class="form-control form-control-sm w-auto" wire:model.live="periodMonth">
        <button type="button" class="btn btn-sm btn-outline-primary" wire:click="recompute">{{ __('Recompute') }}</button>
    </div>
    <div class="row g-4">
        <div class="col-lg-8"><div class="card"><div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Module') }}</th><th>{{ __('Activity signal') }}</th><th class="text-end">{{ __('Count') }}</th><th>{{ __('Used?') }}</th></tr></thead>
                <tbody>
                    @forelse ($scores as $score)
                        <tr wire:key="ad-{{ $score->id }}"><td>{{ $score->module_code }}</td><td>{{ str_replace('_', ' ', $score->activity_signal) }}</td><td class="text-end">{{ number_format($score->activity_count) }}</td><td>@if ($score->is_actively_used)<span class="badge text-bg-success">{{ __('Active') }}</span>@else<span class="badge text-bg-secondary">{{ __('No activity') }}</span>@endif</td></tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('Choose a school; recompute if nothing is recorded for the month.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div></div></div>
        <div class="col-lg-4"><div class="card"><div class="card-header">{{ __('Entitled but dormant') }}</div>
            <ul class="list-group list-group-flush small">@forelse ($dormant as $code) <li class="list-group-item">{{ $code }}</li> @empty <li class="list-group-item text-body-secondary">{{ __('None.') }}</li> @endforelse</ul>
            <div class="card-footer small text-body-secondary">{{ __('A conversation about getting value from the module — not an upsell.') }}</div>
        </div></div>
    </div>
</div>
