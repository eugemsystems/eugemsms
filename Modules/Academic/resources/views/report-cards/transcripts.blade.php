<div>
    <div class="mb-4">
        <h4 class="mb-0">{{ __('Transcripts') }}</h4>
        <p class="text-body-secondary small mb-0">{{ __('Built from published results across every year, with a verification code. Download from the document archive.') }}</p>
    </div>
    <div class="card"><div class="card-body">
        @if ($student)
            <div class="mb-3"><strong>{{ $student->admission_number }} — {{ $student->fullName() }}</strong> <button type="button" class="btn btn-link btn-sm" wire:click="$set('studentId', null)">{{ __('change') }}</button></div>
            @error('studentId') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
            <button type="button" class="btn btn-primary btn-sm mb-3" wire:click="generate">{{ __('Generate transcript') }}</button>
            <ul class="list-group">@forelse ($documents as $document) <li class="list-group-item small" wire:key="d-{{ $document->id }}">{{ $document->generated_at?->format('d M Y H:i') }} — {{ __('verification code') }} <code>{{ $document->verification_code }}</code></li> @empty <li class="list-group-item text-body-secondary small">{{ __('None generated yet.') }}</li> @endforelse</ul>
            @if (Route::has('documents.index')) <a class="small" href="{{ route('documents.index', $school) }}" wire:navigate>{{ __('Open the document archive') }}</a> @endif
        @else
            <input type="search" class="form-control form-control-sm mb-2" wire:model.live.debounce.300ms="search" placeholder="{{ __('Admission number or name') }}">
            @foreach ($matches as $match) <button type="button" class="list-group-item list-group-item-action small border rounded mb-1" wire:key="m-{{ $match->id }}" wire:click="select({{ $match->id }})">{{ $match->admission_number }} — {{ $match->fullName() }}</button> @endforeach
        @endif
    </div></div>
</div>
