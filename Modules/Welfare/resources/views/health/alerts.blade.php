<div>
    <h4 class="mb-1">{{ __('Medical alert board') }}</h4>
    <p class="text-body-secondary mb-3">{{ __('Tier 2 — public summary and action only. No diagnosis or clinical note is shown here (AC-BRD-06-008).') }}</p>

    <input type="text" class="form-control mb-3" style="max-width: 320px" wire:model.live.debounce.400ms="search" placeholder="{{ __('Search learner…') }}">

    <div class="row g-3">
        @forelse ($conditions as $condition)
            <div class="col-md-4" wire:key="alert-{{ $condition->id }}">
                <div class="card h-100 {{ in_array($condition->severity, ['life_threatening', 'severe'], true) ? 'border-danger' : '' }}">
                    <div class="card-body">
                        <h6 class="card-title mb-1">{{ $condition->student?->first_name }} {{ $condition->student?->last_name }}</h6>
                        <span class="badge text-bg-{{ in_array($condition->severity, ['life_threatening', 'severe'], true) ? 'danger' : 'secondary' }} mb-2">{{ str_replace('_', ' ', $condition->severity) }}</span>
                        <p class="small mb-1">{{ $condition->public_summary }}</p>
                        <div class="small text-body-secondary">
                            @if ($condition->requires_emergency_plan)
                                <div>{{ $planStatus[$condition->student_id] ?? false ? __('Care plan approved') : __('⚠ Care plan not yet approved') }}</div>
                            @endif
                            @if ($condition->affects_dietary)
                                <div>{{ __('Dietary — see catering register') }}</div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <p class="text-body-secondary">{{ __('No active medical alerts.') }}</p>
        @endforelse
    </div>
</div>
