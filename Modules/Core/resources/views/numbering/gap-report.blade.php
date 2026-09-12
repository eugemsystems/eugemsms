<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="{{ route('numbering.index', $school) }}" class="btn btn-icon btn-outline-secondary btn-sm" wire:navigate>
            <i class="ri ri-arrow-left-line"></i>
        </a>
        <div>
            <h4 class="mb-1">{{ __('Numbering gap report') }}</h4>
            <p class="text-body-secondary mb-0">{{ __('Every voided number and the reason it was voided — part of the audit pack.') }}</p>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <div class="form-floating form-floating-outline" style="max-width: 22rem;">
                <select class="form-select" wire:model.live="seriesId">
                    <option value="">{{ __('All series') }}</option>
                    @foreach ($series as $item)
                        <option value="{{ $item->id }}">{{ $item->document_type }}</option>
                    @endforeach
                </select>
                <label>{{ __('Series') }}</label>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Number') }}</th>
                        <th>{{ __('Sequence') }}</th>
                        <th>{{ __('Reason') }}</th>
                        <th>{{ __('Voided at') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($entries as $entry)
                        <tr>
                            <td>{{ $entry->formattedNumber }}</td>
                            <td>{{ $entry->sequence }}</td>
                            <td>{{ $entry->reason }}</td>
                            <td>{{ $entry->voidedAt->format('d M Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-body-secondary py-4">{{ __('No voided numbers — no gaps.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
