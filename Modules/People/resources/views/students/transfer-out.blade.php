<div>
    <h4 class="mb-1">{{ __('Transfer out') }}</h4>
    <p class="text-body-secondary mb-4">{{ $student->fullName() }} — {{ $student->admission_number }} · <a href="{{ route('people.students.show', [$school, $student]) }}" wire:navigate>{{ __('Back to profile') }}</a></p>
    @php $blocked = collect($clearance)->flatten()->isNotEmpty(); @endphp
    <div class="row g-4">
        <div class="col-lg-6"><div class="card"><div class="card-header">{{ __('Clearance') }}</div><ul class="list-group list-group-flush">
            @forelse ($clearance as $module => $reasons)
                <li class="list-group-item d-flex justify-content-between" wire:key="c-{{ $module }}"><span class="text-capitalize">{{ $module }}</span>
                    @if ($reasons === []) <span class="badge text-bg-success">{{ __('clear') }}</span> @else <span class="text-danger small">{{ implode('; ', $reasons) }}</span> @endif</li>
            @empty
                <li class="list-group-item text-body-secondary">{{ __('No clearance checks are registered.') }}</li>
            @endforelse
        </ul></div></div>
        <div class="col-lg-6"><div class="card"><div class="card-body">
            <input type="date" class="form-control form-control-sm mb-2" wire:model="exitedOn">
            @error('exitedOn') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
            <input type="text" class="form-control form-control-sm mb-2" wire:model="destination" placeholder="{{ __('Destination school') }}">
            <input type="text" class="form-control form-control-sm mb-2" wire:model="reason" placeholder="{{ __('Reason') }}">
            @if ($blocked)
                <div class="alert alert-warning small">{{ __('Clearance is incomplete. Only the head may transfer anyway, with a written reason that is kept on the timeline.') }}</div>
                <textarea class="form-control form-control-sm mb-2" rows="2" wire:model="overrideReason" placeholder="{{ __('Reason for transferring without full clearance') }}"></textarea>
                @error('overrideReason') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
            @endif
            <button type="button" class="btn btn-danger btn-sm" wire:click="transfer" wire:confirm="{{ __('Transfer this learner out? Billing stops from the exit date.') }}">{{ __('Transfer out') }}</button>
        </div></div></div>
    </div>
</div>
