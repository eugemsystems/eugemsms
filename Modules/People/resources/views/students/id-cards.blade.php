<div>
    <h4 class="mb-1">{{ __('ID card') }}</h4>
    <p class="text-body-secondary mb-4">{{ $student->fullName() }} — {{ $student->admission_number }} · <a href="{{ route('people.students.show', [$school, $student]) }}" wire:navigate>{{ __('Back to profile') }}</a></p>
    <div class="card"><div class="card-body">
        <button type="button" class="btn btn-primary btn-sm mb-3" wire:click="generate">{{ __('Generate a card') }}</button>
        <ul class="list-group">@forelse ($cards as $card) <li class="list-group-item small" wire:key="k-{{ $card->id }}">{{ $card->generated_at?->format('d M Y H:i') }} — <code>{{ $card->verification_code }}</code></li> @empty <li class="list-group-item text-body-secondary small">{{ __('No cards issued yet.') }}</li> @endforelse</ul>
        @if (Route::has('documents.index')) <a class="small d-block mt-2" href="{{ route('documents.index', $school) }}" wire:navigate>{{ __('Open the document archive to print') }}</a> @endif
    </div></div>
</div>
