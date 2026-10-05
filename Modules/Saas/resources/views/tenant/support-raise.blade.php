<div>
    <div class="mb-4"><h4 class="mb-0">{{ __('Contact support') }}</h4><p class="text-body-secondary small mb-0">{{ __('This goes to the software vendor’s support team, not to your school. For a complaint about the school itself, use Feedback & Complaints.') }}</p></div>
    <div class="row g-4">
        <div class="col-lg-5"><div class="card"><div class="card-body">
            <div class="row g-2 mb-2">
                <div class="col-6"><select class="form-select form-select-sm" wire:model="category"><option value="how_to">{{ __('How do I…') }}</option><option value="bug">{{ __('Something is broken') }}</option><option value="billing">{{ __('Billing') }}</option><option value="feature_request">{{ __('Feature request') }}</option></select></div>
                <div class="col-6"><select class="form-select form-select-sm" wire:model="priority"><option value="low">{{ __('Low') }}</option><option value="normal">{{ __('Normal') }}</option><option value="high">{{ __('High') }}</option><option value="urgent">{{ __('Urgent') }}</option></select></div>
            </div>
            <input type="text" class="form-control form-control-sm mb-2" wire:model="subject" placeholder="{{ __('Subject') }}">
            @error('subject') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
            <textarea class="form-control form-control-sm mb-2" rows="5" wire:model="description" placeholder="{{ __('Describe the problem') }}"></textarea>
            @error('description') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
            <button type="button" class="btn btn-primary btn-sm" wire:click="raise">{{ __('Send to support') }}</button>
        </div></div></div>
        <div class="col-lg-7"><div class="card">
            <div class="card-header">{{ __('My tickets') }}</div>
            <div class="table-responsive"><table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Subject') }}</th><th>{{ __('Status') }}</th><th>{{ __('Response due') }}</th></tr></thead>
                <tbody>
                    @forelse ($tickets as $ticket)
                        <tr wire:key="mt-{{ $ticket->id }}"><td>{{ $ticket->subject }}</td><td>{{ __(ucfirst(str_replace('_', ' ', $ticket->status))) }}</td><td class="small">{{ $ticket->sla_due_at?->toDayDateTimeString() }}</td></tr>
                    @empty
                        <tr><td colspan="3" class="text-center text-body-secondary py-3">{{ __('You have not raised any tickets.') }}</td></tr>
                    @endforelse
                </tbody>
            </table></div>
        </div></div>
    </div>
</div>
