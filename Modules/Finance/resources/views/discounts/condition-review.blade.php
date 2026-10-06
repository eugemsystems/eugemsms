<div>
    <div class="mb-4">
        <h4 class="mb-0">{{ __('Condition review') }}</h4>
        <p class="text-body-secondary small mb-0">{{ __('Check conditional awards against recorded results. A failed condition suspends the award for a person to decide — it never revokes by itself.') }}</p>
    </div>
    <div class="mb-3"><select class="form-select form-select-sm w-auto" wire:model.live="termId">@foreach ($terms as $term) <option value="{{ $term->id }}">{{ $term->name }}</option> @endforeach</select></div>
    <div class="card"><div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead><tr><th>{{ __('Learner') }}</th><th>{{ __('Condition') }}</th><th>{{ __('Last checked') }}</th><th>{{ __('Result') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
            <tbody>
                @forelse ($awards as $award)
                    <tr wire:key="cr-{{ $award->id }}">
                        <td>{{ $students[$award->student_id]?->fullName() ?? '—' }}</td>
                        <td class="small">{{ $award->condition_note ?? __('Minimum :pct% average', ['pct' => $award->scheme?->minimum_average_percent]) }}</td>
                        <td class="small">{{ $award->condition_last_checked_at?->diffForHumans() ?? '—' }}</td>
                        <td>@if ($award->condition_met === null) — @elseif ($award->condition_met) <span class="badge text-bg-success">{{ __('Met') }}</span> @else <span class="badge text-bg-danger">{{ __('Not met') }}</span> @endif</td>
                        <td>{{ __(ucfirst($award->status)) }}</td>
                        <td class="text-end text-nowrap"><button type="button" class="btn btn-sm btn-outline-primary" wire:click="review({{ $award->id }})">{{ __('Check') }}</button> @if ($award->status === 'suspended') <button type="button" class="btn btn-sm btn-outline-success" wire:click="beginReinstate({{ $award->id }})">{{ __('Reinstate') }}</button> @endif</td>
                    </tr>
                    @if ($reinstatingId === $award->id)
                        <tr wire:key="cr-ri-{{ $award->id }}"><td colspan="6"><div class="row g-2"><div class="col-md-8"><input type="text" class="form-control form-control-sm" wire:model="reason" placeholder="{{ __('Why is it reinstated? (required)') }}">@error('reason') <div class="text-danger small">{{ $message }}</div> @enderror</div><div class="col-md-4"><button type="button" class="btn btn-success btn-sm" wire:click="reinstate">{{ __('Reinstate') }}</button></div></div></td></tr>
                    @endif
                @empty
                    <tr><td colspan="6" class="text-center text-body-secondary py-3">{{ __('No conditional awards.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div></div>
</div>
