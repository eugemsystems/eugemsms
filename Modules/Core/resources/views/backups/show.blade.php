<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="{{ route('backups.index') }}" class="btn btn-icon btn-outline-secondary btn-sm" wire:navigate>
            <i class="ri ri-arrow-left-line"></i>
        </a>
        <div class="flex-grow-1">
            <h4 class="mb-1">{{ __(':type backup', ['type' => \Illuminate\Support\Str::headline($backup->type)]) }}</h4>
            <p class="text-body-secondary mb-0">{{ __('Started :when.', ['when' => $backup->started_at->format('d M Y H:i')]) }}</p>
        </div>
        @php
            $badge = match ($backup->status) {
                'completed', 'verified' => 'success',
                'failed', 'expired' => 'danger',
                default => 'warning',
            };
        @endphp
        <span class="badge text-bg-{{ $badge }} fs-6">{{ \Illuminate\Support\Str::headline($backup->status) }}</span>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-3"><div class="card text-center"><div class="card-body"><div class="small text-body-secondary">{{ __('Scope') }}</div><div class="fw-medium">{{ \Illuminate\Support\Str::headline($backup->scope) }}{{ $backup->scope_id ? " #{$backup->scope_id}" : '' }}</div></div></div></div>
        <div class="col-md-3"><div class="card text-center"><div class="card-body"><div class="small text-body-secondary">{{ __('Size') }}</div><div class="fw-medium">{{ number_format($backup->size_bytes / (1024 * 1024), 2) }} MB</div></div></div></div>
        <div class="col-md-3"><div class="card text-center"><div class="card-body"><div class="small text-body-secondary">{{ __('Encrypted') }}</div><div class="fw-medium">{{ $backup->is_encrypted ? __('Yes') : __('No') }}</div></div></div></div>
        <div class="col-md-3"><div class="card text-center"><div class="card-body"><div class="small text-body-secondary">{{ __('Verified') }}</div><div class="fw-medium">{{ $backup->isVerified() ? $backup->verified_at->format('d M Y') : __('Not yet') }}</div></div></div></div>
    </div>

    @if ($backup->verification_notes)
        <div class="alert alert-secondary">{{ $backup->verification_notes }}</div>
    @endif

    <div class="d-flex gap-2 mb-3">
        @if (in_array($backup->status, ['completed', 'verified'], true))
            <button type="button" class="btn btn-outline-primary" wire:click="runRestoreTest" wire:loading.attr="disabled">
                <i class="ri ri-shield-check-line me-1"></i>{{ __('Run restore test') }}
            </button>
            <button type="button" class="btn btn-outline-danger" wire:click="$set('showRequestModal', true)">
                <i class="ri ri-alarm-warning-line me-1"></i>{{ __('Request production restore') }}
            </button>
        @endif
    </div>

    <div class="card">
        <div class="card-header"><h6 class="mb-0">{{ __('Restore tests') }}</h6></div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Environment') }}</th>
                        <th>{{ __('Requested by') }}</th>
                        <th>{{ __('Approved by') }}</th>
                        <th>{{ __('Tested') }}</th>
                        <th class="text-end">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($restoreTests as $test)
                        <tr wire:key="restore-test-{{ $test->id }}">
                            @php
                                $testBadge = match ($test->status) {
                                    'passed', 'approved' => 'success',
                                    'failed' => 'danger',
                                    'pending_approval' => 'warning',
                                    default => 'secondary',
                                };
                            @endphp
                            <td><span class="badge text-bg-{{ $testBadge }}">{{ \Illuminate\Support\Str::headline($test->status) }}</span></td>
                            <td>{{ \Illuminate\Support\Str::headline($test->target_environment) }}</td>
                            <td>{{ $test->requestedBy?->name ?? '—' }}</td>
                            <td>{{ $test->approvedBy?->name ?? '—' }}</td>
                            <td>{{ $test->tested_at?->format('d M Y H:i') ?? '—' }}</td>
                            <td class="text-end">
                                @if ($test->status === 'pending_approval' && $test->requested_by !== auth()->id())
                                    <button type="button" class="btn btn-sm btn-outline-success" wire:click="approveProductionRestore({{ $test->id }})" wire:confirm="{{ __('Approve this production restore request?') }}">
                                        {{ __('Approve') }}
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-body-secondary py-4">{{ __('This backup has never been restore-tested.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($showRequestModal)
        <div class="modal show d-block" tabindex="-1" style="background: rgba(0, 0, 0, .5);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form wire:submit="requestProductionRestore">
                        <div class="modal-header">
                            <h5 class="modal-title">{{ __('Request production restore') }}</h5>
                            <button type="button" class="btn-close" wire:click="$set('showRequestModal', false)" aria-label="{{ __('Close') }}"></button>
                        </div>
                        <div class="modal-body">
                            <p class="text-body-secondary">{{ __('A different person must approve this before any restore runbook proceeds.') }}</p>
                            <div class="form-floating form-floating-outline">
                                <textarea class="form-control @error('reason') is-invalid @enderror" id="reason" wire:model="reason" style="height: 100px" placeholder=" "></textarea>
                                <label for="reason">{{ __('Reason') }}</label>
                                @error('reason') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" wire:click="$set('showRequestModal', false)">{{ __('Cancel') }}</button>
                            <button type="submit" class="btn btn-danger" wire:loading.attr="disabled">{{ __('Submit request') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
