<div>
    <div class="mb-4"><h4 class="mb-0">{{ __('Broadcasts') }}</h4><p class="text-body-secondary small mb-0">{{ __('Announcements to tenant administrators. Say explicitly who receives each one.') }}</p></div>
    <div class="row g-4">
        <div class="col-xl-7"><div class="card"><div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Title') }}</th><th>{{ __('Severity') }}</th><th>{{ __('Audience') }}</th><th>{{ __('Runs') }}</th></tr></thead>
                <tbody>
                    @forelse ($announcements as $a)
                        <tr wire:key="bc-{{ $a->id }}"><td>{{ $a->title }}</td><td>{{ __(ucfirst($a->severity)) }}</td><td>{{ $a->target_tenant_ids === null ? __('Every tenant') : trans_choice(':count tenant|:count tenants', count($a->target_tenant_ids)) }}</td><td class="small">{{ $a->starts_at?->toDayDateTimeString() }}@if ($a->ends_at) → {{ $a->ends_at->toDayDateTimeString() }} @endif</td></tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('No announcements.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div></div></div>
        <div class="col-xl-5"><div class="card"><div class="card-header">{{ __('Compose') }}</div><div class="card-body">
            <input type="text" class="form-control form-control-sm mb-2" wire:model="title" placeholder="{{ __('Title') }}">
            @error('title') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
            <textarea class="form-control form-control-sm mb-2" rows="4" wire:model="body" placeholder="{{ __('Message') }}"></textarea>
            @error('body') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
            <select class="form-select form-select-sm mb-2" wire:model="severity"><option value="info">{{ __('Info') }}</option><option value="maintenance">{{ __('Maintenance') }}</option><option value="incident">{{ __('Incident') }}</option></select>
            <div class="mb-2">
                <div class="form-check form-check-inline"><input class="form-check-input" type="radio" id="aud-sel" value="selected" wire:model.live="audience"><label class="form-check-label small" for="aud-sel">{{ __('Named tenants') }}</label></div>
                <div class="form-check form-check-inline"><input class="form-check-input" type="radio" id="aud-all" value="all" wire:model.live="audience"><label class="form-check-label small" for="aud-all">{{ __('Every tenant') }}</label></div>
            </div>
            @if ($audience === 'selected') <select multiple class="form-select form-select-sm mb-2" size="6" wire:model="tenantIds">@foreach ($tenants as $tenant) <option value="{{ $tenant->id }}">{{ $tenant->name }}</option> @endforeach</select> @endif
            @error('audience') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
            <div class="row g-2 mb-2"><div class="col-6"><input type="datetime-local" class="form-control form-control-sm" wire:model="startsAt"></div><div class="col-6"><input type="datetime-local" class="form-control form-control-sm" wire:model="endsAt"></div></div>
            <button type="button" class="btn btn-primary btn-sm" wire:click="post" wire:confirm="{{ __('Post this announcement?') }}">{{ __('Post') }}</button>
        </div></div></div>
    </div>
</div>
