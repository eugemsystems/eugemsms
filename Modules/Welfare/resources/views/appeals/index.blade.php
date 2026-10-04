<div>
    <h4 class="mb-1">{{ __('Appeals') }}</h4>

    <div class="row g-4">
        <div class="col-md-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Student') }}</th><th>{{ __('Status') }}</th><th>{{ __('Outcome') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($appeals as $appeal)
                                <tr wire:key="appeal-{{ $appeal->id }}">
                                    <td>{{ $appeal->sanction?->student?->first_name }} {{ $appeal->sanction?->student?->last_name }}</td>
                                    <td><span class="badge text-bg-secondary">{{ $appeal->status }}</span></td>
                                    <td>{{ $appeal->outcome ? str_replace('_', ' ', $appeal->outcome) : '—' }}</td>
                                    <td>
                                        @if ($appeal->status !== 'decided')
                                            @if ($decidingAppealId === $appeal->id)
                                                <div class="d-flex gap-2">
                                                    <select class="form-select form-select-sm" wire:model="outcome">
                                                        <option value="upheld">{{ __('Upheld') }}</option>
                                                        <option value="reduced">{{ __('Reduced') }}</option>
                                                        <option value="overturned">{{ __('Overturned') }}</option>
                                                        <option value="dismissed">{{ __('Dismissed') }}</option>
                                                    </select>
                                                    <input type="text" class="form-control form-control-sm" wire:model="outcomeReason" placeholder="{{ __('Reason') }}">
                                                    <button type="button" class="btn btn-sm btn-success" wire:click="decide({{ $appeal->id }})">{{ __('Confirm') }}</button>
                                                </div>
                                            @else
                                                <button type="button" class="btn btn-sm btn-outline-primary" wire:click="$set('decidingAppealId', {{ $appeal->id }})">{{ __('Decide') }}</button>
                                            @endif
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('No appeals.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-5">
            <div class="card">
                <div class="card-header">{{ __('Lodge appeal') }}</div>
                <div class="card-body">
                    <select class="form-select mb-2" wire:model="sanctionId">
                        <option value="">{{ __('Sanction') }}</option>
                        @foreach ($sanctions as $sanction)
                            <option value="{{ $sanction->id }}">{{ $sanction->student?->first_name }} {{ $sanction->student?->last_name }} — {{ $sanction->starts_on->toDateString() }}</option>
                        @endforeach
                    </select>
                    <textarea class="form-control mb-2" wire:model="grounds" placeholder="{{ __('Grounds') }}"></textarea>
                    <div class="form-check mb-2">
                        <input type="checkbox" class="form-check-input" id="lodgedByStudent" wire:model="lodgedByStudent">
                        <label class="form-check-label" for="lodgedByStudent">{{ __('Lodged by the student') }}</label>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm" wire:click="lodge">{{ __('Lodge') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
