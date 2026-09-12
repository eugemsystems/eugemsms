<div>
    <div class="d-flex align-items-center gap-2 mb-4 flex-wrap">
        <a href="{{ route('documents.index', $school) }}" class="btn btn-icon btn-outline-secondary btn-sm" wire:navigate>
            <i class="ri ri-arrow-left-line"></i>
        </a>
        <div class="flex-grow-1">
            <h4 class="mb-1">{{ __('Document batches') }}</h4>
            <p class="text-body-secondary mb-0">{{ __('Bulk document generation runs.') }}</p>
        </div>
        <button type="button" class="btn btn-primary" wire:click="openCreateModal">
            <i class="ri ri-add-line me-1"></i>{{ __('New batch') }}
        </button>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Type') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Progress') }}</th>
                        <th>{{ __('Requested') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($batches as $batch)
                        <tr wire:key="batch-{{ $batch->id }}">
                            <td>{{ $batch->document_type }}</td>
                            <td>
                                <span class="badge text-bg-{{ match ($batch->status) { 'completed' => 'success', 'failed' => 'danger', 'cancelled' => 'secondary', 'running' => 'info', default => 'warning' } }}">
                                    {{ \Illuminate\Support\Str::headline($batch->status) }}
                                </span>
                            </td>
                            <td>{{ $batch->completed_count }} / {{ $batch->total_count }} {{ $batch->failed_count > 0 ? '('.$batch->failed_count.' '.__('failed').')' : '' }}</td>
                            <td>{{ $batch->started_at?->format('d M Y H:i') ?? __('Not started') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-body-secondary py-4">{{ __('No batches yet.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($batches->hasPages())
            <div class="card-footer">
                {{ $batches->links() }}
            </div>
        @endif
    </div>

    @if ($showCreateModal)
        <div class="modal show d-block" tabindex="-1" style="background: rgba(0, 0, 0, .5);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form wire:submit="create">
                        <div class="modal-header">
                            <h5 class="modal-title">{{ __('New document batch') }}</h5>
                            <button type="button" class="btn-close" wire:click="$set('showCreateModal', false)" aria-label="{{ __('Close') }}"></button>
                        </div>
                        <div class="modal-body">
                            <div class="form-floating form-floating-outline mb-3">
                                <input type="text" class="form-control @error('documentType') is-invalid @enderror" id="batch-document-type" wire:model="documentType" placeholder=" ">
                                <label for="batch-document-type">{{ __('Document type') }}</label>
                                @error('documentType') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="form-floating form-floating-outline mb-3">
                                <select class="form-select @error('templateId') is-invalid @enderror" id="batch-template" wire:model="templateId">
                                    <option value="">{{ __('Select a template') }}</option>
                                    @foreach ($templates as $template)
                                        <option value="{{ $template->id }}">{{ $template->template_type }} — {{ $template->name }}</option>
                                    @endforeach
                                </select>
                                <label for="batch-template">{{ __('Template') }}</label>
                                @error('templateId') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="form-floating form-floating-outline">
                                <input type="number" min="1" class="form-control @error('totalCount') is-invalid @enderror" id="batch-total" wire:model="totalCount" placeholder=" ">
                                <label for="batch-total">{{ __('Number of documents') }}</label>
                                @error('totalCount') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" wire:click="$set('showCreateModal', false)">{{ __('Cancel') }}</button>
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">{{ __('Queue batch') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
