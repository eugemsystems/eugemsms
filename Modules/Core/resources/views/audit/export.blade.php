<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="{{ route('audit.explorer', $school) }}" class="btn btn-icon btn-outline-secondary btn-sm" wire:navigate>
            <i class="ri ri-arrow-left-line"></i>
        </a>
        <div>
            <h4 class="mb-1">{{ __('Audit export') }}</h4>
            <p class="text-body-secondary mb-0">{{ __('Export the activity log as CSV for an external auditor.') }}</p>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <form wire:submit="export">
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <div class="form-floating form-floating-outline">
                            <input type="date" class="form-control @error('fromDate') is-invalid @enderror" id="fromDate" wire:model.live="fromDate" placeholder=" ">
                            <label for="fromDate">{{ __('From') }}</label>
                            @error('fromDate') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-floating form-floating-outline">
                            <input type="date" class="form-control @error('toDate') is-invalid @enderror" id="toDate" wire:model.live="toDate" placeholder=" ">
                            <label for="toDate">{{ __('To') }}</label>
                            @error('toDate') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <p class="mb-0 text-body-secondary">{{ __(':count record(s) match this range.', ['count' => $previewCount]) }}</p>
                    </div>
                </div>
                <div class="mt-4">
                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                        <i class="ri ri-download-line me-1"></i>{{ __('Export CSV') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
