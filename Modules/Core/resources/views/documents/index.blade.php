<div>
    <div class="d-flex align-items-center gap-2 mb-4 flex-wrap">
        <a href="{{ route('schools.index') }}" class="btn btn-icon btn-outline-secondary btn-sm" wire:navigate>
            <i class="ri ri-arrow-left-line"></i>
        </a>
        <div class="flex-grow-1">
            <h4 class="mb-1">{{ __('Document archive') }}</h4>
            <p class="text-body-secondary mb-0">{{ __('Every document generated for :school.', ['school' => $school->name]) }}</p>
        </div>
        <a href="{{ route('documents.batches', $school) }}" class="btn btn-outline-secondary" wire:navigate>
            <i class="ri ri-stack-line me-1"></i>{{ __('Batches') }}
        </a>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <div class="form-floating form-floating-outline" style="max-width: 24rem;">
                <input type="text" class="form-control" id="search" wire:model.live.debounce.400ms="search" placeholder=" ">
                <label for="search">{{ __('Search by type or number') }}</label>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Type') }}</th>
                        <th>{{ __('Number') }}</th>
                        <th>{{ __('Generated') }}</th>
                        <th>{{ __('Downloads') }}</th>
                        <th class="text-end">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($documents as $document)
                        <tr wire:key="document-{{ $document->id }}">
                            <td>{{ $document->document_type }}</td>
                            <td>{{ $document->number ?? '—' }}</td>
                            <td>{{ $document->generated_at?->format('d M Y H:i') }}</td>
                            <td>{{ $document->download_count }}</td>
                            <td class="text-end">
                                <button type="button" class="btn btn-icon btn-sm btn-outline-primary" wire:click="download({{ $document->id }})" title="{{ __('Download') }}" aria-label="{{ __('Download') }}">
                                    <i class="icon-base ri ri-download-line icon-22px"></i>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-body-secondary py-4">{{ __('No documents generated yet.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($documents->hasPages())
            <div class="card-footer">
                {{ $documents->links() }}
            </div>
        @endif
    </div>
</div>
