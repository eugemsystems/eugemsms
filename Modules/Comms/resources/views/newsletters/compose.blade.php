<div>
    <h4 class="mb-1">{{ __('Newsletters') }}</h4>
    <p class="text-body-secondary small">{{ __('Save an issue as a draft or schedule it. Automatic sending to an audience is not available yet, so a scheduled issue is not delivered by this screen.') }}</p>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Issue') }}</th><th>{{ __('Title') }}</th><th>{{ __('Status') }}</th><th>{{ __('Scheduled') }}</th></tr></thead>
                        <tbody>
                            @forelse ($newsletters as $newsletter)
                                <tr wire:key="nl-{{ $newsletter->id }}">
                                    <td>{{ $newsletter->issue_number }}</td>
                                    <td>{{ $newsletter->title }}</td>
                                    <td><span class="badge {{ $newsletter->status === 'sent' ? 'bg-label-success' : ($newsletter->status === 'scheduled' ? 'bg-label-warning' : 'bg-label-secondary') }}">{{ $newsletter->status }}</span></td>
                                    <td class="small">{{ $newsletter->scheduled_for?->toDateTimeString() ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('No newsletters yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">{{ __('New issue') }}</div>
                <div class="card-body">
                    <input type="text" class="form-control mb-2" wire:model="issueNumber" placeholder="{{ __('Issue number, e.g. 2026-T1-03') }}">
                    @error('issueNumber') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                    <input type="text" class="form-control mb-2" wire:model="title" placeholder="{{ __('Title') }}">
                    @error('title') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                    <textarea class="form-control mb-1" rows="8" wire:model="contentHtml" placeholder="{{ __('Content (basic HTML: p, br, strong, em, lists, links, headings)') }}"></textarea>
                    @error('contentHtml') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                    <label class="form-label small mb-0">{{ __('Schedule for (blank = keep as draft)') }}</label>
                    <input type="datetime-local" class="form-control mb-2" wire:model="scheduledFor">
                    @error('scheduledFor') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                    <button type="button" class="btn btn-primary btn-sm" wire:click="save">{{ __('Save') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
