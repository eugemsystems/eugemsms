<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="{{ route('imports.history', $school) }}" class="btn btn-icon btn-outline-secondary btn-sm" wire:navigate>
            <i class="ri ri-arrow-left-line"></i>
        </a>
        <div class="flex-grow-1">
            <h4 class="mb-1">{{ __('Import batch: :label', ['label' => $definition->label ?? $batch->definition_key]) }}</h4>
            <p class="text-body-secondary mb-0">{{ __('Source file: :name', ['name' => $batch->sourceFile?->original_name ?? '—']) }}</p>
        </div>
        @php
            $statusBadge = match ($batch->status) {
                'completed' => 'success',
                'failed' => 'danger',
                'rolled_back' => 'secondary',
                'validated' => 'info',
                default => 'warning',
            };
        @endphp
        <span class="badge text-bg-{{ $statusBadge }} fs-6">{{ \Illuminate\Support\Str::headline($batch->status) }}</span>
    </div>

    @if ($batch->status === 'mapping')
        <div class="card mb-3">
            <div class="card-body d-flex justify-content-between align-items-center">
                <p class="mb-0">{{ __('This batch has not been validated yet. No rows have been read.') }}</p>
                <button type="button" class="btn btn-primary" wire:click="runValidation" wire:loading.attr="disabled">
                    {{ __('Run validation') }}
                </button>
            </div>
        </div>
    @endif

    @if (in_array($batch->status, ['validated', 'importing', 'completed'], true))
        <div class="row g-3 mb-3">
            <div class="col-6 col-md-3">
                <div class="card text-center"><div class="card-body"><div class="fs-4">{{ $batch->total_rows }}</div><div class="small text-body-secondary">{{ __('Total rows') }}</div></div></div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card text-center"><div class="card-body"><div class="fs-4 text-success">{{ $batch->valid_rows }}</div><div class="small text-body-secondary">{{ __('Valid') }}</div></div></div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card text-center"><div class="card-body"><div class="fs-4 text-danger">{{ $batch->invalid_rows }}</div><div class="small text-body-secondary">{{ __('Invalid') }}</div></div></div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card text-center"><div class="card-body"><div class="fs-4">{{ $batch->imported_rows }}</div><div class="small text-body-secondary">{{ __('Imported') }}</div></div></div>
            </div>
        </div>
    @endif

    @if ($batch->status === 'validated' && $batch->validation_report)
        <div class="card mb-3">
            <div class="card-header"><h6 class="mb-0">{{ __('Errors by type') }}</h6></div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('Error') }}</th>
                            <th>{{ __('Row numbers') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($batch->validation_report['errors_by_type'] ?? [] as $group)
                            <tr>
                                <td>{{ $group['message'] }}</td>
                                <td class="small">{{ implode(', ', $group['rows']) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="text-center text-body-secondary py-4">{{ __('No validation errors — every row is valid.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($batch->error_file_id)
                <div class="card-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="downloadCorrectionFile">
                        <i class="ri ri-download-2-line me-1"></i>{{ __('Download correction file') }}
                    </button>
                </div>
            @endif
        </div>

        <div class="card mb-3">
            <div class="card-body d-flex justify-content-between align-items-center">
                <p class="mb-0">
                    {{ __('Approving imports exactly the :count valid row(s) shown above. Invalid rows are excluded.', ['count' => $batch->valid_rows]) }}
                    @if ($batch->isDryRun())
                        <br><span class="text-warning">{{ __('Dry run — nothing will actually be written.') }}</span>
                    @endif
                </p>
                <button type="button" class="btn btn-primary" wire:click="approveAndImport" wire:confirm="{{ __('Approve and import these rows now?') }}" wire:loading.attr="disabled">
                    {{ __('Approve and import') }}
                </button>
            </div>
        </div>
    @endif

    @if ($batch->status === 'completed')
        <div class="card mb-3">
            <div class="card-body d-flex justify-content-between align-items-center">
                <p class="mb-0">
                    {{ __(':imported imported, :skipped skipped, :failed failed.', ['imported' => $batch->imported_rows, 'skipped' => $batch->skipped_rows, 'failed' => $batch->failed_rows]) }}
                    {{ __('Completed :when.', ['when' => $batch->completed_at?->format('d M Y H:i')]) }}
                </p>
                @if ($definition?->is_rollbackable)
                    <button type="button" class="btn btn-outline-danger" wire:click="rollback" wire:confirm="{{ __('Roll back this entire batch? Records that have since been modified or referenced will block the rollback.') }}" wire:loading.attr="disabled">
                        {{ __('Roll back') }}
                    </button>
                @endif
            </div>
        </div>
    @endif

    @if ($batch->status === 'rolled_back')
        <div class="alert alert-secondary mb-0">
            {{ __('This batch was rolled back on :when.', ['when' => $batch->rolled_back_at?->format('d M Y H:i')]) }}
        </div>
    @endif
</div>
