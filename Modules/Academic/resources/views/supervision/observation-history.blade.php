<div>
    <div class="mb-4">
        <h4 class="mb-0">{{ __('Observation history') }}</h4>
        <p class="text-body-secondary small mb-0">{{ __('The observer\'s scores are their professional judgement: you can add your comments, not change them.') }}</p>
    </div>
    @forelse ($grouped as $staffId => $items)
        <div class="card mb-3" wire:key="g-{{ $staffId }}">
            <div class="card-header d-flex justify-content-between"><strong>{{ $staffNames->get($staffId) }}</strong>
                <span class="small text-body-secondary">{{ $items->pluck('overall_rating')->filter()->map(fn ($r) => str_replace('_', ' ', $r))->implode(' → ') }}</span></div>
            <ul class="list-group list-group-flush">
                @foreach ($items as $observation)
                    <li class="list-group-item" wire:key="o-{{ $observation->id }}">
                        <div class="d-flex justify-content-between">
                            <div>{{ $observation->observed_at->format('d M Y H:i') }} @if ($observation->class_observed) · {{ $observation->class_observed }} @endif
                                <span class="small text-body-secondary">— {{ __('observed by') }} {{ $staffNames->get($observation->observer_staff_id) }}</span>
                                @if ($observation->follow_up_observation_id) <span class="badge text-bg-light border">{{ __('follow-up') }}</span> @endif
                                @if ($observation->overall_rating) <span class="badge text-bg-secondary">{{ str_replace('_', ' ', $observation->overall_rating) }}</span> @endif</div>
                            @if ($staffId === $ownStaffId) <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="startComment({{ $observation->id }})">{{ $observation->teacher_comments ? __('Edit comment') : __('Comment') }}</button> @endif
                        </div>
                        <table class="table table-sm small my-2 w-auto"><tbody>@foreach ($observation->scores as $criterion => $level) <tr><td class="pe-4">{{ $criterion }}</td><td>{{ $level }}</td></tr> @endforeach</tbody></table>
                        @if ($observation->strengths_noted) <div class="small"><strong>{{ __('Strengths') }}:</strong> {{ $observation->strengths_noted }}</div> @endif
                        @if ($observation->areas_for_development) <div class="small"><strong>{{ __('To develop') }}:</strong> {{ $observation->areas_for_development }}</div> @endif
                        @if ($observation->teacher_comments) <div class="small mt-1 border-start ps-2"><strong>{{ __('Teacher comment') }}:</strong> {{ $observation->teacher_comments }}</div> @endif
                        @if ($commentingId === $observation->id)
                            <textarea class="form-control form-control-sm mt-2" rows="3" wire:model="commentText"></textarea>
                            @error('commentText') <div class="text-danger small">{{ $message }}</div> @enderror
                            <button type="button" class="btn btn-sm btn-primary mt-2" wire:click="saveComment">{{ __('Save comment') }}</button>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @empty
        <div class="text-body-secondary">{{ __('No observations to show.') }}</div>
    @endforelse
</div>
