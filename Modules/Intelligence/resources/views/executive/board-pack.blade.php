<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="{{ route('insights.executive.head', $school) }}" class="btn btn-icon btn-outline-secondary btn-sm" wire:navigate>
            <i class="ri ri-arrow-left-line"></i>
        </a>
        <div>
            <h4 class="mb-0">{{ __('Board pack') }}</h4>
            <p class="text-body-secondary small mb-0">{{ __('One document for the board: enrolment, finance, staffing and boarding for a term. The financial section is the income statement, unchanged. Academic outcomes and the risk summary are not available yet.') }}</p>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Term') }}</th><th>{{ __('Sections') }}</th><th>{{ __('Generated') }}</th><th>{{ __('File') }}</th></tr></thead>
                        <tbody>
                            @forelse ($packs as $pack)
                                <tr wire:key="bp-{{ $pack->id }}">
                                    <td>{{ $termNames[$pack->term_id] ?? '' }}</td>
                                    <td class="small">{{ collect($pack->sections_included)->map(fn ($section) => $sectionLabels[$section] ?? $section)->implode(', ') }}</td>
                                    <td class="small">{{ $pack->generated_at?->toDayDateTimeString() }}</td>
                                    <td class="small">{{ $files[$pack->document_id] ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('No board packs yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-footer small text-body-secondary">{{ __('Open the file in the') }} <a href="{{ route('files.index', $school) }}" wire:navigate>{{ __('file vault') }}</a>.</div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">{{ __('Generate a pack') }}</div>
                <div class="card-body">
                    <select class="form-select mb-2" wire:model="termId">
                        <option value="">{{ __('Term…') }}</option>
                        @foreach ($terms as $term) <option value="{{ $term->id }}">{{ $term->name }}</option> @endforeach
                    </select>
                    @error('termId') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                    @foreach ($sectionLabels as $value => $label)
                        <div class="form-check"><input class="form-check-input" type="checkbox" id="bp-{{ $value }}" value="{{ $value }}" wire:model="sections"><label class="form-check-label small" for="bp-{{ $value }}">{{ __($label) }}</label></div>
                    @endforeach
                    @error('sections') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    <button type="button" class="btn btn-primary btn-sm mt-3" wire:click="generate">{{ __('Generate') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
