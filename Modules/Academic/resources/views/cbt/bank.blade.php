<div>
    <div class="mb-4">
        <h4 class="mb-0">{{ __('Question bank') }}</h4>
        <p class="text-body-secondary small mb-0">{{ __('Tag by subject, topic and difficulty. Objective types mark themselves; written and upload types are marked by hand.') }}</p>
    </div>
    <div class="row g-4">
        <div class="col-xl-8">
            <div class="d-flex flex-wrap gap-2 mb-3">
                <select class="form-select form-select-sm w-auto" wire:model.live="subjectFilter"><option value="">{{ __('All subjects') }}</option>@foreach ($subjects as $subject) <option value="{{ $subject->id }}">{{ $subject->name }}</option> @endforeach</select>
                <select class="form-select form-select-sm w-auto" wire:model.live="difficultyFilter"><option value="">{{ __('Any difficulty') }}</option><option value="easy">{{ __('Easy') }}</option><option value="medium">{{ __('Medium') }}</option><option value="hard">{{ __('Hard') }}</option></select>
                <input type="search" class="form-control form-control-sm w-auto" wire:model.live.debounce.300ms="topicFilter" placeholder="{{ __('Topic') }}">
            </div>
            <div class="card"><div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>{{ __('Question') }}</th><th>{{ __('Type') }}</th><th>{{ __('Level') }}</th><th class="text-end">{{ __('Marks') }}</th><th class="text-end">{{ __('Used') }}</th><th class="text-end">{{ __('Diff.') }}</th><th class="text-end">{{ __('Discr.') }}</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($questions as $question)
                            <tr wire:key="q-{{ $question->id }}" class="{{ $question->is_active ? '' : 'text-body-secondary' }}">
                                <td>{{ \Illuminate\Support\Str::limit($question->prompt, 80) }}<div class="small text-body-secondary">{{ $question->topic }}</div></td>
                                <td class="small">{{ str_replace('_', ' ', $question->item_type) }} @unless ($question->is_auto_markable) <span class="badge text-bg-light border">{{ __('manual') }}</span> @endunless</td>
                                <td>{{ __(ucfirst($question->difficulty)) }}</td><td class="text-end">{{ $question->max_mark }}</td><td class="text-end">{{ $question->usage_count }}</td>
                                <td class="text-end">{{ $question->difficulty_index ?? '—' }}</td><td class="text-end">{{ $question->discrimination_index ?? '—' }}</td>
                                <td class="text-end"><button type="button" class="btn btn-sm btn-outline-secondary" wire:click="setActive({{ $question->id }}, {{ $question->is_active ? 'false' : 'true' }})">{{ $question->is_active ? __('Retire') : __('Restore') }}</button></td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-body-secondary py-3">{{ __('No questions yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div></div>
        </div>
        <div class="col-xl-4"><div class="card"><div class="card-header">{{ __('Add a question') }}</div><div class="card-body">
            <select class="form-select form-select-sm mb-2" wire:model="subjectId"><option value="">{{ __('Subject…') }}</option>@foreach ($subjects as $subject) <option value="{{ $subject->id }}">{{ $subject->name }}</option> @endforeach</select>
            @error('subjectId') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
            <div class="row g-2 mb-2">
                <div class="col-6"><select class="form-select form-select-sm" wire:model.live="itemType"><option value="mcq">{{ __('Multiple choice') }}</option><option value="true_false">{{ __('True / false') }}</option><option value="fill_in">{{ __('Fill in') }}</option><option value="short_answer">{{ __('Short answer') }}</option><option value="essay">{{ __('Essay') }}</option><option value="file_upload">{{ __('File upload') }}</option></select></div>
                <div class="col-6"><select class="form-select form-select-sm" wire:model="difficulty"><option value="easy">{{ __('Easy') }}</option><option value="medium">{{ __('Medium') }}</option><option value="hard">{{ __('Hard') }}</option></select></div>
            </div>
            <input type="text" class="form-control form-control-sm mb-2" wire:model="topic" placeholder="{{ __('Topic') }}">
            <textarea class="form-control form-control-sm mb-2" rows="3" wire:model="prompt" placeholder="{{ __('Question') }}"></textarea>
            @error('prompt') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
            @if ($itemType === 'mcq') <textarea class="form-control form-control-sm mb-2" rows="4" wire:model="options" placeholder="{{ __('Options, one per line') }}"></textarea> @endif
            @if (in_array($itemType, ['mcq', 'true_false', 'fill_in'])) <input type="text" class="form-control form-control-sm mb-2" wire:model="correct" placeholder="{{ ['mcq' => __('Correct option number(s), first = 0, e.g. 0 or 1,3'), 'true_false' => __('true or false'), 'fill_in' => __('Accepted answer(s), separated by |')][$itemType] }}">@endif
            @error('correct') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
            <input type="number" step="0.5" min="0.5" class="form-control form-control-sm mb-2" wire:model="maxMark" placeholder="{{ __('Marks') }}">
            <button type="button" class="btn btn-primary btn-sm" wire:click="create">{{ __('Add') }}</button>
        </div></div></div>
    </div>
</div>
