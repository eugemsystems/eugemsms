<div>
    <h4 class="mb-1">{{ __('Siblings') }}</h4>
    <p class="text-body-secondary mb-4">{{ $student->fullName() }} — {{ $student->admission_number }} · <a href="{{ route('people.students.show', [$school, $student]) }}" wire:navigate>{{ __('Back to profile') }}</a></p>
    <div class="row g-4">
        <div class="col-lg-7"><div class="card"><ul class="list-group list-group-flush">
            @forelse ($links as $link)
                <li class="list-group-item d-flex justify-content-between align-items-center" wire:key="s-{{ $link->id }}">
                    <span><a href="{{ route('people.students.show', [$school, $siblings->get($link->sibling_student_id)]) }}" wire:navigate>{{ $siblings->get($link->sibling_student_id)?->fullName() }}</a> <span class="small text-body-secondary">{{ $siblings->get($link->sibling_student_id)?->admission_number }} · {{ str_replace('_', ' ', $link->relationship) }}</span></span>
                    <button type="button" class="btn btn-sm btn-outline-danger" wire:click="unlink({{ $link->sibling_student_id }})" wire:confirm="{{ __('Remove this sibling link?') }}">{{ __('Remove') }}</button>
                </li>
            @empty
                <li class="list-group-item text-body-secondary">{{ __('No sibling links. Learners who share a guardian are already treated as siblings for discounts.') }}</li>
            @endforelse
        </ul></div></div>
        <div class="col-lg-5"><div class="card"><div class="card-header">{{ __('Link a sibling') }}</div><div class="card-body">
            @if ($picked) <div class="mb-2"><strong>{{ $picked->fullName() }}</strong> <button type="button" class="btn btn-link btn-sm" wire:click="$set('siblingId', null)">{{ __('change') }}</button></div>
            @else <input type="search" class="form-control form-control-sm mb-1" wire:model.live.debounce.300ms="search" placeholder="{{ __('Admission number or name') }}">
                @foreach ($matches as $match) <button type="button" class="list-group-item list-group-item-action small border rounded mb-1" wire:key="m-{{ $match->id }}" wire:click="select({{ $match->id }})">{{ $match->admission_number }} — {{ $match->fullName() }}</button> @endforeach
            @endif
            <select class="form-select form-select-sm my-2" wire:model="relationship">@foreach (['full', 'half', 'step', 'adopted', 'cousin_treated_as'] as $r) <option value="{{ $r }}">{{ ucfirst(str_replace('_', ' ', $r)) }}</option> @endforeach</select>
            @error('siblingId') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
            <button type="button" class="btn btn-primary btn-sm" wire:click="link">{{ __('Link') }}</button>
        </div></div></div>
    </div>
</div>
