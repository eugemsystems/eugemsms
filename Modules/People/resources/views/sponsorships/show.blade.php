<div>
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
        <div><h4 class="mb-0">{{ $sponsorship->name }}</h4><p class="text-body-secondary small mb-0">{{ $sponsor?->displayName() }} · {{ $sponsorship->sponsorship_type }} · <a href="{{ route('people.sponsorships.index', $school) }}" wire:navigate>{{ __('All sponsorships') }}</a></p></div>
        <div class="d-flex gap-2 align-items-center"><span class="badge text-bg-light border">{{ $sponsorship->status }}</span>
            @if ($sponsorship->status === 'draft') <button type="button" class="btn btn-sm btn-primary" wire:click="changeStatus('active')">{{ __('Activate') }}</button> @endif
            @if ($sponsorship->status === 'active') <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="changeStatus('suspended')">{{ __('Suspend') }}</button> @endif
            @if ($sponsorship->status === 'suspended') <button type="button" class="btn btn-sm btn-outline-primary" wire:click="changeStatus('active')">{{ __('Resume') }}</button> @endif
            @if (in_array($sponsorship->status, ['active', 'suspended'])) <button type="button" class="btn btn-sm btn-outline-danger" wire:click="changeStatus('completed')" wire:confirm="{{ __('Complete this sponsorship? This is final.') }}">{{ __('Complete') }}</button> @endif
        </div>
    </div>
    @php $cap = $sponsorship->budget_minor; $used = $cap ? min(100, round($sponsorship->committed_minor / $cap * 100)) : 0; @endphp
    <div class="row g-3 mb-4">
        <div class="col-md-4"><div class="card"><div class="card-body"><div class="small text-body-secondary">{{ __('Committed') }}</div><div class="fs-5">{{ number_format($sponsorship->committed_minor / 100, 2) }}</div>@if ($cap) <div class="progress mt-1" style="height:.4rem"><div class="progress-bar" style="width: {{ $used }}%"></div></div><div class="small text-body-secondary">{{ __('of') }} {{ number_format($cap / 100, 2) }} {{ $sponsorship->budget_currency }}</div>@endif</div></div></div>
        <div class="col-md-4"><div class="card"><div class="card-body"><div class="small text-body-secondary">{{ __('Invoiced') }}</div><div class="fs-5">{{ number_format($sponsorship->invoiced_minor / 100, 2) }}</div></div></div></div>
        <div class="col-md-4"><div class="card"><div class="card-body"><div class="small text-body-secondary">{{ __('Paid') }}</div><div class="fs-5">{{ number_format($sponsorship->paid_minor / 100, 2) }}</div></div></div></div>
    </div>
    <div class="row g-4">
        <div class="col-xl-8"><div class="card"><div class="table-responsive"><table class="table table-sm align-middle mb-0">
            <thead><tr><th>{{ __('Learner') }}</th><th>{{ __('Condition') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
            <tbody>@forelse ($beneficiaries as $b) <tr wire:key="b-{{ $b->id }}">
                <td>{{ $students->get($b->student_id)?->fullName() }}</td>
                <td class="small">@if ($b->performance_condition) {{ $b->performance_condition }} @if ($b->condition_met === false) <span class="badge text-bg-warning">{{ __('not met — review') }}</span> @elseif ($b->condition_met) <span class="badge text-bg-success">{{ __('met') }}</span> @endif @else — @endif</td>
                <td><span class="badge text-bg-light border">{{ $b->status }}</span></td>
                <td class="text-end text-nowrap">@if ($b->status === 'active')
                    @if ($b->performance_condition) <button type="button" class="btn btn-sm btn-outline-success" wire:click="setCondition({{ $b->id }}, true)">{{ __('Met') }}</button> <button type="button" class="btn btn-sm btn-outline-warning" wire:click="setCondition({{ $b->id }}, false)">{{ __('Not met') }}</button> @endif
                    <button type="button" class="btn btn-sm btn-outline-danger" wire:click="end({{ $b->id }})" wire:confirm="{{ __('End support for this learner? Invoices already issued are not changed.') }}">{{ __('End support') }}</button>
                @endif</td></tr> @empty <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('No beneficiaries yet.') }}</td></tr> @endforelse</tbody>
        </table></div></div></div>
        @if (in_array($sponsorship->status, ['draft', 'active']))
            <div class="col-xl-4"><div class="card"><div class="card-header">{{ __('Add a beneficiary') }}</div><div class="card-body">
                @if ($picked) <div class="mb-2"><strong>{{ $picked->fullName() }}</strong> <button type="button" class="btn btn-link btn-sm" wire:click="$set('studentId', null)">{{ __('change') }}</button></div>
                @else <input type="search" class="form-control form-control-sm mb-1" wire:model.live.debounce.300ms="search" placeholder="{{ __('Admission number or name') }}">@foreach ($matches as $m) <button type="button" class="list-group-item list-group-item-action small border rounded mb-1" wire:key="m-{{ $m->id }}" wire:click="select({{ $m->id }})">{{ $m->admission_number }} — {{ $m->fullName() }}</button> @endforeach @endif
                <input type="number" step="0.01" min="0" class="form-control form-control-sm my-2" wire:model="commitment" placeholder="{{ __('Amount to commit against the budget') }}">
                <input type="text" class="form-control form-control-sm mb-2" wire:model="condition" placeholder="{{ __('Performance condition, e.g. maintain 60%') }}">
                @error('studentId') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                @error('commitment') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                <button type="button" class="btn btn-primary btn-sm" wire:click="addBeneficiary">{{ __('Add') }}</button>
            </div></div></div>
        @endif
    </div>
</div>
