<div>
    <h4 class="mb-1">{{ __('Application documents') }}</h4>
    <p class="text-body-secondary mb-4">{{ $application->first_name }} {{ $application->last_name }} · {{ $application->application_number }} · <a href="{{ route('people.admissions.applications.show', [$school, $application]) }}" wire:navigate>{{ __('Back to application') }}</a></p>
    <div class="row g-4">
        <div class="col-lg-8"><div class="card"><ul class="list-group list-group-flush">
            @forelse ($documents as $d) <li class="list-group-item d-flex justify-content-between align-items-center" wire:key="d-{{ $d->id }}"><span>{{ ucfirst(str_replace('_', ' ', $d->document_type)) }} @if ($d->is_verified) <span class="badge text-bg-success">{{ __('verified') }}</span> @endif</span>@unless ($d->is_verified) <button type="button" class="btn btn-sm btn-outline-primary" wire:click="verify({{ $d->id }})">{{ __('Verify') }}</button> @endunless</li>
            @empty <li class="list-group-item text-body-secondary">{{ __('No documents yet.') }}</li> @endforelse
        </ul></div></div>
        <div class="col-lg-4"><div class="card"><div class="card-body">
            <select class="form-select form-select-sm mb-2" wire:model="documentType">@foreach ($types as $t) <option value="{{ $t }}">{{ ucfirst(str_replace('_', ' ', $t)) }}</option> @endforeach</select>
            <input type="file" class="form-control form-control-sm mb-2" wire:model="file">@error('file') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
            <button type="button" class="btn btn-primary btn-sm" wire:click="attach" wire:loading.attr="disabled">{{ __('Attach') }}</button>
        </div></div></div>
    </div>
</div>
