<div>
    <h4 class="mb-1">{{ __('Privacy notices') }} 🇿🇼</h4>
    <p class="text-body-secondary small">{{ __('A new version is always a new row. A parent who consented under version 3 did not consent to version 7.') }}</p>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Version') }}</th><th>{{ __('Title') }}</th><th>{{ __('Effective from') }}</th><th>{{ __('Requires re-consent') }}</th></tr></thead>
                        <tbody>
                            @forelse ($notices as $notice)
                                <tr wire:key="notice-{{ $notice->id }}">
                                    <td>{{ $notice->version }}</td>
                                    <td>{{ $notice->title }}</td>
                                    <td>{{ $notice->effective_from->toDateString() }}</td>
                                    <td>{{ $notice->requires_reconsent ? __('Yes') : __('No') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('No notices yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">{{ __('New version') }}</div>
                <div class="card-body">
                    <input type="text" class="form-control mb-2" wire:model="version" placeholder="{{ __('Version, e.g. 4') }}">
                    <input type="text" class="form-control mb-2" wire:model="title" placeholder="{{ __('Title') }}">
                    <textarea class="form-control mb-2" rows="4" wire:model="content" placeholder="{{ __('Notice content') }}"></textarea>
                    <input type="date" class="form-control mb-2" wire:model="effectiveFrom">
                    <div class="form-check mb-2">
                        <input type="checkbox" class="form-check-input" wire:model="requiresReconsent" id="noticeReconsent">
                        <label class="form-check-label small" for="noticeReconsent">{{ __('Requires re-consent') }}</label>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm" wire:click="create">{{ __('Create version') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
