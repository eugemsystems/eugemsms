<div>
    <div class="mb-4"><h4 class="mb-0">{{ __('Contact update queue') }}</h4><p class="text-body-secondary small mb-0">{{ __('Phone, email and address control where messages go and how accounts are recovered, so changes wait for approval.') }}</p></div>
    @forelse ($pending as $update)
        @php $guardian = $guardians->get($update->guardian_id); @endphp
        <div class="card mb-3" wire:key="u-{{ $update->id }}"><div class="card-header d-flex justify-content-between"><strong>{{ $guardian?->displayName() }}</strong><span class="small text-body-secondary">{{ $update->created_at?->format('d M H:i') }}</span></div>
            <table class="table table-sm mb-0"><thead><tr><th>{{ __('Field') }}</th><th>{{ __('Now') }}</th><th>{{ __('Requested') }}</th></tr></thead><tbody>
                @foreach ($update->changes as $field => $value) <tr><td>{{ str_replace('_', ' ', $field) }}</td><td class="text-body-secondary">{{ $guardian?->{$field} ?? '—' }}</td><td><strong>{{ $value ?? '—' }}</strong></td></tr> @endforeach
            </tbody></table>
            <div class="card-body">
                <button type="button" class="btn btn-primary btn-sm" wire:click="approve({{ $update->id }})">{{ __('Approve') }}</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="startReject({{ $update->id }})">{{ __('Reject') }}</button>
                @if ($rejectingId === $update->id) <input type="text" class="form-control form-control-sm mt-2" wire:model="note" placeholder="{{ __('Reason (optional)') }}"><button type="button" class="btn btn-secondary btn-sm mt-2" wire:click="reject">{{ __('Confirm rejection') }}</button> @endif
            </div></div>
    @empty
        <div class="text-body-secondary">{{ __('Nothing waiting.') }}</div>
    @endforelse
</div>
