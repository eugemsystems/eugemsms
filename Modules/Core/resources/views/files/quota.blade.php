<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="{{ route('files.index', $school) }}" class="btn btn-icon btn-outline-secondary btn-sm" wire:navigate>
            <i class="ri ri-arrow-left-line"></i>
        </a>
        <div class="flex-grow-1">
            <h4 class="mb-1">{{ __('Storage quota') }}</h4>
            <p class="text-body-secondary mb-0">{{ __('Usage against :school\'s storage allowance.', ['school' => $school->name]) }}</p>
        </div>
        <button type="button" class="btn btn-primary" wire:click="openEditModal">
            <i class="ri ri-settings-3-line me-1"></i>{{ __('Edit quota') }}
        </button>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            @if ($quota)
                @php $percent = $quota->quota_bytes > 0 ? min(100, (int) round($quota->used_bytes / $quota->quota_bytes * 100)) : 0; @endphp
                <p class="mb-1">
                    {{ number_format($quota->used_bytes / (1024 ** 3), 2) }} / {{ number_format($quota->quota_bytes / (1024 ** 3), 2) }} GB
                    ({{ $percent }}%)
                </p>
                <div class="progress" style="height: 8px;">
                    <div class="progress-bar {{ $percent >= $quota->warn_at_percent ? 'bg-warning' : '' }}" style="width: {{ $percent }}%"></div>
                </div>
                @if ($percent >= 100)
                    <p class="small text-danger mt-2 mb-0">{{ __('Quota reached — new uploads are blocked until it is raised. Existing files remain accessible.') }}</p>
                @elseif ($percent >= $quota->warn_at_percent)
                    <p class="small text-warning mt-2 mb-0">{{ __('Approaching quota.') }}</p>
                @endif
            @else
                <p class="text-body-secondary mb-0">{{ __('No storage quota has been set for this school yet — uploads are currently unlimited.') }}</p>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h6 class="mb-0">{{ __('Usage by category') }}</h6></div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Category') }}</th>
                        <th>{{ __('Files') }}</th>
                        <th>{{ __('Size') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($byCategory as $row)
                        <tr>
                            <td>{{ \Illuminate\Support\Str::headline($row->category) }}</td>
                            <td>{{ $row->total_count }}</td>
                            <td>{{ number_format($row->total_bytes / (1024 * 1024), 1) }} MB</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-center text-body-secondary py-4">{{ __('No files uploaded yet.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($showEditModal)
        <div class="modal show d-block" tabindex="-1" style="background: rgba(0, 0, 0, .5);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form wire:submit="save">
                        <div class="modal-header">
                            <h5 class="modal-title">{{ __('Edit storage quota') }}</h5>
                            <button type="button" class="btn-close" wire:click="$set('showEditModal', false)" aria-label="{{ __('Close') }}"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="form-floating form-floating-outline">
                                        <input type="number" step="0.01" min="0.01" class="form-control @error('quotaGb') is-invalid @enderror" id="quotaGb" wire:model="quotaGb" placeholder=" ">
                                        <label for="quotaGb">{{ __('Quota (GB)') }}</label>
                                        @error('quotaGb') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating form-floating-outline">
                                        <input type="number" min="1" max="100" class="form-control @error('warnAtPercent') is-invalid @enderror" id="warnAtPercent" wire:model="warnAtPercent" placeholder=" ">
                                        <label for="warnAtPercent">{{ __('Warn at %') }}</label>
                                        @error('warnAtPercent') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" wire:click="$set('showEditModal', false)">{{ __('Cancel') }}</button>
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">{{ __('Save') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
