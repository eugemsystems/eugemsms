<div>
    <div class="mb-4">
        <h4 class="mb-0">{{ __('Item analysis') }}</h4>
        <p class="text-body-secondary small mb-0">{{ __('Difficulty is the share of candidates who got an item right; discrimination is how much better the top 27% did than the bottom 27%. Low discrimination is a prompt to review the item, not to delete it.') }}</p>
    </div>
    <div class="d-flex gap-2 mb-3"><select class="form-select form-select-sm w-auto" wire:model.live="testId"><option value="">{{ __('Closed test…') }}</option>@foreach ($tests as $option) <option value="{{ $option->id }}">{{ $option->title }}</option> @endforeach</select>@if ($test) <button type="button" class="btn btn-sm btn-outline-primary" wire:click="compute">{{ __('Compute from results') }}</button> @endif</div>
    <div class="card"><div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead><tr><th>{{ __('Question') }}</th><th class="text-end">{{ __('Difficulty') }}</th><th class="text-end">{{ __('Discrimination') }}</th><th></th></tr></thead>
            <tbody>
                @forelse ($items as $item)
                    <tr wire:key="ia-{{ $item->id }}"><td>{{ \Illuminate\Support\Str::limit($item->prompt, 90) }}</td><td class="text-end">{{ $item->difficulty_index ?? '—' }}</td><td class="text-end">{{ $item->discrimination_index ?? '—' }}</td><td>@if ($item->discrimination_index !== null && (float) $item->discrimination_index < 0.2) <span class="badge text-bg-warning">{{ __('Review') }}</span> @endif</td></tr>
                @empty
                    <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('Choose a closed test.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div></div>
</div>
