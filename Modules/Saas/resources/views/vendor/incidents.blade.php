<div>
    <div class="mb-4"><h4 class="mb-0">{{ __('Incidents') }}</h4><p class="text-body-secondary small mb-0">{{ __('Content of the public status page at /status. Updates append to the timeline; a resolved incident is closed.') }}</p></div>
    <div class="row g-4">
        <div class="col-xl-8">
            @forelse ($incidents as $incident)
                <div class="card mb-3" wire:key="in-{{ $incident->id }}">
                    <div class="card-header d-flex flex-wrap gap-2 align-items-center">
                        <strong>{{ $incident->title }}</strong>
                        <span class="badge text-bg-{{ ['critical' => 'danger', 'major' => 'warning', 'minor' => 'info'][$incident->severity] ?? 'secondary' }}">{{ __(ucfirst($incident->severity)) }}</span>
                        <span class="badge text-bg-{{ $incident->status === 'resolved' ? 'success' : 'secondary' }}">{{ __(ucfirst($incident->status)) }}</span>
                        @unless ($incident->is_public) <span class="badge text-bg-light">{{ __('Private') }}</span> @endunless
                        @if ($incident->status !== 'resolved') <button type="button" class="btn btn-sm btn-outline-primary ms-auto" wire:click="beginUpdate({{ $incident->id }})">{{ __('Post update') }}</button> @endif
                    </div>
                    <div class="card-body small">
                        <div class="text-body-secondary mb-2">{{ implode(', ', $incident->affected_components) }}</div>
                        @foreach (array_reverse($incident->updates) as $update)
                            <div class="mb-1"><span class="text-body-secondary">{{ \Illuminate\Support\Carbon::parse($update['at'])->toDayDateTimeString() }}</span> · <strong>{{ __(ucfirst($update['status'])) }}</strong> — {{ $update['message'] }}</div>
                        @endforeach
                        @if ($updatingId === $incident->id)
                            <div class="row g-2 mt-2">
                                <div class="col-md-3"><select class="form-select form-select-sm" wire:model="updateStatus">@foreach (['investigating', 'identified', 'monitoring', 'resolved'] as $s) <option value="{{ $s }}">{{ __(ucfirst($s)) }}</option> @endforeach</select></div>
                                <div class="col-md-7"><input type="text" class="form-control form-control-sm" wire:model="updateMessage" placeholder="{{ __('What changed?') }}">@error('updateMessage') <div class="text-danger small">{{ $message }}</div> @enderror</div>
                                <div class="col-md-2"><button type="button" class="btn btn-primary btn-sm" wire:click="postUpdate">{{ __('Post') }}</button></div>
                            </div>
                        @endif
                    </div>
                </div>
            @empty
                <div class="text-body-secondary">{{ __('No incidents.') }}</div>
            @endforelse
        </div>
        <div class="col-xl-4"><div class="card"><div class="card-header">{{ __('Open an incident') }}</div><div class="card-body">
            <input type="text" class="form-control form-control-sm mb-2" wire:model="title" placeholder="{{ __('Title') }}">
            @error('title') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
            <input type="text" class="form-control form-control-sm mb-2" wire:model="components" placeholder="{{ __('Affected components, comma separated') }}">
            @error('components') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
            <select class="form-select form-select-sm mb-2" wire:model="severity"><option value="minor">{{ __('Minor') }}</option><option value="major">{{ __('Major') }}</option><option value="critical">{{ __('Critical') }}</option></select>
            <textarea class="form-control form-control-sm mb-2" rows="3" wire:model="message" placeholder="{{ __('Initial message') }}"></textarea>
            @error('message') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
            <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" id="inc-pub" wire:model="isPublic"><label class="form-check-label small" for="inc-pub">{{ __('Show on the public status page') }}</label></div>
            <button type="button" class="btn btn-primary btn-sm" wire:click="open">{{ __('Open') }}</button>
        </div></div></div>
    </div>
</div>
