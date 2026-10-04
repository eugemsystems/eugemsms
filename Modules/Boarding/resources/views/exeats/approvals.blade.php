<div>
    <h4 class="mb-1">{{ __('Exeat approval queue') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Batched for working through requests quickly.') }}</p>

    @forelse ($pending as $exeat)
        <div class="card mb-3">
            <div class="card-body d-flex justify-content-between align-items-start flex-wrap gap-2">
                <div>
                    <strong>{{ $exeat->student->first_name }} {{ $exeat->student->last_name }}</strong> — {{ $exeat->exeatType->name }}
                    <div class="small text-body-secondary">{{ $exeat->departs_at->format('Y-m-d H:i') }} → {{ $exeat->returns_by->format('Y-m-d H:i') }} · {{ $exeat->destination_address }}</div>
                    <div class="small">{{ $exeat->reason }}</div>
                </div>
                <div class="d-flex gap-2 align-items-center">
                    <input type="text" class="form-control form-control-sm" style="width: 220px" wire:model="rejectionReasons.{{ $exeat->id }}" placeholder="{{ __('Rejection reason') }}">
                    <button type="button" class="btn btn-sm btn-danger" wire:click="reject({{ $exeat->id }})">{{ __('Reject') }}</button>
                    <button type="button" class="btn btn-sm btn-success" wire:click="approve({{ $exeat->id }})">{{ __('Approve') }}</button>
                </div>
            </div>
        </div>
    @empty
        <div class="card"><div class="card-body text-body-secondary">{{ __('Nothing pending.') }}</div></div>
    @endforelse
</div>
