<div>
    <h4 class="mb-1">{{ __('Inspection readiness pack') }} 🇿🇼</h4>
    <p class="text-body-secondary small">{{ __('Assembles attendance registers, staff records, establishment posts and statutory documents into one document for an announced inspection.') }}</p>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">{{ __('Generate pack') }}</div>
                <div class="card-body">
                    <label class="form-label small mb-0">{{ __('Period start') }}</label>
                    <input type="date" class="form-control mb-2" wire:model="periodStart">
                    <label class="form-label small mb-0">{{ __('Period end') }}</label>
                    <input type="date" class="form-control mb-2" wire:model="periodEnd">
                    <button type="button" class="btn btn-primary btn-sm" wire:click="generate">{{ __('Generate inspection pack') }}</button>

                    @if ($generatedFile)
                        <div class="alert alert-success mt-3 mb-0 small">{{ __('Generated:') }} {{ $generatedFile->original_name }}</div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header">{{ __('Recent packs') }}</div>
                <ul class="list-group list-group-flush">
                    @forelse ($recentPacks as $pack)
                        <li class="list-group-item small">{{ $pack->original_name }} — {{ $pack->created_at?->toDateTimeString() }}</li>
                    @empty
                        <li class="list-group-item text-body-secondary small">{{ __('No packs generated yet.') }}</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</div>
