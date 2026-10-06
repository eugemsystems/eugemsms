<div>
    <h4 class="mb-1">{{ __('Documents') }}</h4>
    <p class="text-body-secondary mb-4">{{ $student->fullName() }} — {{ $student->admission_number }} · <a href="{{ route('people.students.show', [$school, $student]) }}" wire:navigate>{{ __('Back to profile') }}</a></p>
    <div class="row g-4">
        <div class="col-xl-8"><div class="card"><div class="table-responsive"><table class="table table-sm align-middle mb-0">
            <thead><tr><th>{{ __('Type') }}</th><th>{{ __('Reference') }}</th><th>{{ __('Expires') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
            <tbody>
                @forelse ($documents as $document)
                    @php $expired = $document->expires_on?->isPast(); $soon = ! $expired && $document->expires_on && $document->expires_on->lte(now()->addDays(30)); @endphp
                    <tr wire:key="d-{{ $document->id }}">
                        <td>{{ str_replace('_', ' ', $document->document_type) }}</td><td class="small">{{ $document->reference_number }}</td>
                        <td>{{ $document->expires_on?->format('d M Y') ?? '—' }} @if ($expired) <span class="badge text-bg-danger">{{ __('expired') }}</span> @elseif ($soon) <span class="badge text-bg-warning">{{ __('soon') }}</span> @endif</td>
                        <td>@if ($document->is_verified) <span class="badge text-bg-success">{{ __('verified') }}</span> @if ($document->is_original_sighted) <span class="badge text-bg-light border">{{ __('original seen') }}</span> @endif @else <span class="badge text-bg-light border">{{ __('unverified') }}</span> @endif</td>
                        <td class="text-end text-nowrap">@unless ($document->is_verified || $expired)
                            <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="verify({{ $document->id }}, true)">{{ __('Verify (original seen)') }}</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="verify({{ $document->id }}, false)">{{ __('Verify (copy)') }}</button>
                        @endunless</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No documents on file.') }}</td></tr>
                @endforelse
            </tbody>
        </table></div></div></div>
        <div class="col-xl-4"><div class="card"><div class="card-header">{{ __('Attach a document') }}</div><div class="card-body">
            <select class="form-select form-select-sm mb-2" wire:model="documentType">@foreach ($types as $type) <option value="{{ $type }}">{{ ucfirst(str_replace('_', ' ', $type)) }}</option> @endforeach</select>
            <input type="file" class="form-control form-control-sm mb-2 @error('file') is-invalid @enderror" wire:model="file">
            @error('file') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
            <input type="text" class="form-control form-control-sm mb-2" wire:model="referenceNumber" placeholder="{{ __('Reference number') }}">
            <div class="row g-2 mb-2"><div class="col-6"><label class="small text-body-secondary">{{ __('Issued') }}</label><input type="date" class="form-control form-control-sm" wire:model="issuedOn"></div><div class="col-6"><label class="small text-body-secondary">{{ __('Expires') }}</label><input type="date" class="form-control form-control-sm" wire:model="expiresOn"></div></div>
            <button type="button" class="btn btn-primary btn-sm" wire:click="attach" wire:loading.attr="disabled">{{ __('Attach') }}</button>
        </div></div></div>
    </div>
</div>
