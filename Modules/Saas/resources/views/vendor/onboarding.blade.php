<div>
    <div class="mb-4 d-flex align-items-center"><div><h4 class="mb-0">{{ __('Onboarding tracker') }}</h4><p class="text-body-secondary small mb-0">{{ __('Per-school progress to go-live. A checklist with no progress for :days days is stalled.', ['days' => $thresholdDays]) }}</p></div><button type="button" class="btn btn-sm btn-outline-secondary ms-auto" wire:click="alertStalled">{{ __('Alert stalled') }}</button></div>
    <div class="row g-4">
        <div class="col-xl-8">
            @forelse ($checklists as $checklist)
                @php($done = collect($checklist->steps)->whereNotNull('completed_at')->count())
                @php($last = collect($checklist->steps)->pluck('completed_at')->filter()->map(fn ($d) => \Illuminate\Support\Carbon::parse($d))->max() ?? $checklist->started_at)
                @php($stalled = $checklist->status !== 'go_live' && $last->diffInDays(now(), absolute: true) >= $thresholdDays)
                <div class="card mb-3" wire:key="ob-{{ $checklist->id }}">
                    <div class="card-header d-flex flex-wrap gap-2 align-items-center">
                        <strong>{{ $schoolNames[$checklist->school_id] ?? '—' }}</strong>
                        <span class="badge text-bg-{{ $checklist->status === 'go_live' ? 'success' : ($stalled ? 'danger' : 'info') }}">{{ $checklist->status === 'go_live' ? __('Go-live') : ($stalled ? __('Stalled') : __('In progress')) }}</span>
                        <span class="small text-body-secondary ms-auto">{{ $done }}/{{ count($checklist->steps) }} @if ($checklist->target_go_live_date) · {{ __('target') }} {{ $checklist->target_go_live_date->toFormattedDateString() }} @endif</span>
                    </div>
                    <ul class="list-group list-group-flush small">
                        @foreach ($checklist->steps as $step)
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span>@if ($step['completed_at']) <i class="ri ri-checkbox-circle-fill text-success"></i> @else <i class="ri ri-checkbox-blank-circle-line"></i> @endif {{ $step['label'] }}@if ($step['owner']) <span class="text-body-secondary">· {{ $step['owner'] }}</span> @endif</span>
                                @unless ($step['completed_at']) <button type="button" class="btn btn-sm btn-outline-primary" wire:click="completeStep({{ $checklist->id }}, '{{ $step['key'] }}')">{{ __('Mark done') }}</button> @endunless
                            </li>
                        @endforeach
                    </ul>
                </div>
            @empty
                <div class="text-body-secondary">{{ __('No onboarding checklists yet.') }}</div>
            @endforelse
        </div>
        <div class="col-xl-4"><div class="card"><div class="card-header">{{ __('Start onboarding') }}</div><div class="card-body">
            <select class="form-select form-select-sm mb-2" wire:model.live="tenantId"><option value="">{{ __('Tenant…') }}</option>@foreach ($tenants as $tenant) <option value="{{ $tenant->id }}">{{ $tenant->name }}</option> @endforeach</select>
            <select class="form-select form-select-sm mb-2" wire:model="schoolId"><option value="">{{ __('School…') }}</option>@foreach ($schools as $school) <option value="{{ $school->id }}">{{ $school->name }}</option> @endforeach</select>
            @error('schoolId') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
            <label class="form-label small mb-1">{{ __('Target go-live') }}</label><input type="date" class="form-control form-control-sm mb-2" wire:model="targetGoLive">
            <select class="form-select form-select-sm mb-2" wire:model="managerId"><option value="">{{ __('Success manager…') }}</option>@foreach ($managers as $manager) <option value="{{ $manager->id }}">{{ $manager->name }}</option> @endforeach</select>
            <button type="button" class="btn btn-primary btn-sm" wire:click="start">{{ __('Start') }}</button>
        </div></div></div>
    </div>
</div>
