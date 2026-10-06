<div>
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-4">
        <div><h4 class="mb-0">{{ __('Admissions funnel') }}</h4><p class="text-body-secondary small mb-0">{{ __('How many reached each step, and why enquiries were lost.') }}</p></div>
        <select class="form-select form-select-sm w-auto" wire:model.live="intakeId"><option value="">{{ __('All intakes') }}</option>@foreach ($intakes as $intake) <option value="{{ $intake->id }}">{{ $intake->name }}</option> @endforeach</select>
    </div>
    @php $steps = [['Enquiries', $funnel['enquiries']], ['Applied', $funnel['applied']], ['Offered', $funnel['offered']], ['Accepted', $funnel['accepted']], ['Enrolled', $funnel['enrolled']]]; $top = max(1, $funnel['enquiries'], $funnel['applied']); @endphp
    <div class="card mb-4"><div class="card-body">
        @foreach ($steps as [$label, $count]) <div class="d-flex align-items-center gap-2 mb-2" wire:key="f-{{ $label }}"><div style="width:7rem">{{ __($label) }}</div><div class="progress flex-grow-1" style="height:1.2rem"><div class="progress-bar" style="width: {{ min(100, $count / $top * 100) }}%">{{ $count }}</div></div></div> @endforeach
    </div></div>
    <div class="row g-4">
        <div class="col-md-6"><div class="card"><div class="card-header">{{ __('Enquiries by stage') }}</div><ul class="list-group list-group-flush small">@forelse ($funnel['byStage'] as $stage => $count) <li class="list-group-item d-flex justify-content-between">{{ ucfirst(str_replace('_', ' ', $stage)) }}<span>{{ $count }}</span></li> @empty <li class="list-group-item text-body-secondary">{{ __('None.') }}</li> @endforelse</ul></div></div>
        <div class="col-md-6"><div class="card"><div class="card-header">{{ __('Why enquiries were lost') }}</div><ul class="list-group list-group-flush small">@forelse ($funnel['lost'] as $reason => $count) <li class="list-group-item d-flex justify-content-between">{{ ucfirst(str_replace('_', ' ', (string) $reason)) }}<span>{{ $count }}</span></li> @empty <li class="list-group-item text-body-secondary">{{ __('None lost.') }}</li> @endforelse</ul></div></div>
    </div>
</div>
