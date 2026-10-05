<div>
    <h4 class="mb-1">{{ __('Lost property') }}</h4>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Found') }}</th><th>{{ __('Description') }}</th><th>{{ __('Location') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($items as $item)
                                <tr wire:key="lost-{{ $item->id }}">
                                    <td>{{ $item->found_on->toDateString() }}</td>
                                    <td>{{ $item->description }}</td>
                                    <td>{{ $item->found_location ?? '—' }}</td>
                                    <td>{{ $item->status }}</td>
                                    <td>
                                        @if ($item->status === 'held')
                                            <button type="button" class="btn btn-outline-success btn-sm" wire:click="selectForClaim({{ $item->id }})">{{ __('Claim') }}</button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No lost property.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card mb-3">
                <div class="card-header">{{ __('Report found item') }}</div>
                <div class="card-body">
                    <input type="date" class="form-control mb-2" wire:model="foundOn">
                    <input type="text" class="form-control mb-2" wire:model="description" placeholder="{{ __('Description') }}">
                    <input type="text" class="form-control mb-2" wire:model="foundLocation" placeholder="{{ __('Found location (optional)') }}">
                    <button type="button" class="btn btn-primary btn-sm" wire:click="report">{{ __('Report') }}</button>
                </div>
            </div>

            @if ($claimingItemId !== null)
                <div class="card">
                    <div class="card-header">{{ __('Claim item #') }}{{ $claimingItemId }}</div>
                    <div class="card-body">
                        <select class="form-select mb-2" wire:model="claimedByStudentId">
                            <option value="">{{ __('Claimed by student') }}</option>
                            @foreach ($students as $student)
                                <option value="{{ $student->id }}">{{ $student->fullName() }}</option>
                            @endforeach
                        </select>
                        <button type="button" class="btn btn-success btn-sm" wire:click="claim">{{ __('Confirm claim') }}</button>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
