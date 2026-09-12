<div>
    <div class="d-flex align-items-center gap-2 mb-4 flex-wrap">
        <a href="{{ route('schools.index') }}" class="btn btn-icon btn-outline-secondary btn-sm" wire:navigate>
            <i class="ri ri-arrow-left-line"></i>
        </a>
        <div class="flex-grow-1">
            <h4 class="mb-1">{{ __('Contract-exit export') }}</h4>
            <p class="text-body-secondary mb-0">{{ __('Every :school record and file, in open formats (CSV, JSON, original files) — no other school\'s data is included.', ['school' => $school->name]) }}</p>
        </div>
        <button type="button" class="btn btn-primary" wire:click="generate" wire:confirm="{{ __('Generate a fresh export of all of this school\'s data now?') }}" wire:loading.attr="disabled">
            <i class="ri ri-archive-line me-1"></i>{{ __('Generate export') }}
        </button>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>{{ __('File') }}</th>
                        <th>{{ __('Size') }}</th>
                        <th>{{ __('Scan') }}</th>
                        <th>{{ __('Generated') }}</th>
                        <th class="text-end">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($exports as $export)
                        <tr wire:key="export-{{ $export->id }}">
                            <td>{{ $export->original_name }}</td>
                            <td>{{ number_format($export->size_bytes / (1024 * 1024), 2) }} MB</td>
                            <td>
                                @php
                                    $scanBadge = match ($export->scan_status) {
                                        'clean', 'skipped' => 'success',
                                        'infected' => 'danger',
                                        default => 'secondary',
                                    };
                                @endphp
                                <span class="badge text-bg-{{ $scanBadge }}">{{ \Illuminate\Support\Str::headline($export->scan_status) }}</span>
                            </td>
                            <td>{{ $export->created_at?->format('d M Y H:i') }}</td>
                            <td class="text-end">
                                <button type="button" class="btn btn-icon btn-sm btn-outline-primary" wire:click="download({{ $export->id }})" title="{{ __('Download') }}" aria-label="{{ __('Download') }}">
                                    <i class="icon-base ri ri-download-line icon-22px"></i>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-body-secondary py-4">{{ __('No export has been generated yet.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
