<div>
    <div class="mb-4">
        <h4 class="mb-0">{{ __('Awards') }}</h4>
        <p class="text-body-secondary small mb-0">{{ __('Granted discounts, bursaries and scholarships. Revoking takes effect from a date forward — terms already billed are never re-invoiced.') }}</p>
    </div>
    <div class="d-flex flex-wrap gap-2 mb-3">
        <select class="form-select form-select-sm w-auto" wire:model.live="statusFilter"><option value="">{{ __('All statuses') }}</option>@foreach (['pending_approval', 'active', 'suspended', 'ended', 'revoked'] as $s) <option value="{{ $s }}">{{ __(ucfirst(str_replace('_', ' ', $s))) }}</option> @endforeach</select>
        <select class="form-select form-select-sm w-auto" wire:model.live="schemeFilter"><option value="">{{ __('All schemes') }}</option>@foreach ($schemes as $scheme) <option value="{{ $scheme->id }}">{{ $scheme->code }}</option> @endforeach</select>
        <input type="search" class="form-control form-control-sm w-auto" wire:model.live.debounce.300ms="search" placeholder="{{ __('Learner') }}">
    </div>
    <div class="card"><div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead><tr><th>{{ __('Learner') }}</th><th>{{ __('Scheme') }}</th><th>{{ __('Award') }}</th><th>{{ __('From') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
            <tbody>
                @forelse ($awards as $award)
                    <tr wire:key="aw-{{ $award->id }}">
                        <td>{{ $students[$award->student_id]?->fullName() ?? '—' }}</td>
                        <td>{{ $schemes->firstWhere('id', $award->scheme_id)?->code }}</td>
                        <td>{{ $award->award_method === 'percentage' ? $award->award_percent.'%' : number_format(($award->award_amount_minor ?? 0) / 100, 2).' '.$award->currency }}</td>
                        <td class="small">{{ $award->effective_from?->toFormattedDateString() }}@if ($award->effective_to) → {{ $award->effective_to->toFormattedDateString() }} @endif</td>
                        <td><span class="badge text-bg-{{ ['active' => 'success', 'suspended' => 'warning', 'pending_approval' => 'info'][$award->status] ?? 'secondary' }}">{{ __(ucfirst(str_replace('_', ' ', $award->status))) }}</span>@if ($award->revoked_reason) <div class="small text-body-secondary">{{ $award->revoked_reason }}</div> @endif</td>
                        <td class="text-end">@if ($canRevoke && in_array($award->status, ['active', 'suspended', 'pending_approval'])) <button type="button" class="btn btn-sm btn-outline-danger" wire:click="beginRevoke({{ $award->id }})">{{ __('Revoke') }}</button> @endif</td>
                    </tr>
                    @if ($revokingId === $award->id)
                        <tr wire:key="aw-rv-{{ $award->id }}"><td colspan="6"><div class="row g-2 align-items-start">
                            <div class="col-md-5"><input type="text" class="form-control form-control-sm" wire:model="reason" placeholder="{{ __('Reason (required)') }}">@error('reason') <div class="text-danger small">{{ $message }}</div> @enderror</div>
                            <div class="col-md-3"><input type="date" class="form-control form-control-sm" wire:model="effectiveTo"></div>
                            <div class="col-md-4"><button type="button" class="btn btn-danger btn-sm" wire:click="revoke" wire:confirm="{{ __('Revoke this award from that date?') }}">{{ __('Revoke') }}</button></div>
                        </div></td></tr>
                    @endif
                @empty
                    <tr><td colspan="6" class="text-center text-body-secondary py-3">{{ __('No awards.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div></div>
</div>
