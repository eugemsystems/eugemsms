<div>
    <h4 class="mb-1">{{ __('Disposal queue') }} 🇿🇼</h4>
    <p class="text-body-secondary small">
        {{ __('Metadata only — a pointer (record type and id), never the record\'s own content. A safeguarding, medical or financial record is never shown here beyond that pointer; its content stays behind its own module\'s screens.') }}
    </p>

    <button type="button" class="btn btn-outline-info btn-sm mb-3" wire:click="enqueueDue">{{ __('Enqueue due records for review') }}</button>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Record class') }}</th>
                        <th>{{ __('Record type') }}</th>
                        <th>{{ __('Record id') }}</th>
                        <th>{{ __('Eligible on') }}</th>
                        <th>{{ __('Review status') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $item)
                        <tr wire:key="disp-{{ $item->id }}">
                            <td>{{ $item->schedule?->record_class }}</td>
                            <td>{{ $item->record_type }}</td>
                            <td>#{{ $item->record_id }}</td>
                            <td>{{ $item->eligible_on->toDateString() }}</td>
                            <td>
                                <span class="badge {{ match ($item->review_status) { 'approved' => 'bg-success', 'deferred' => 'bg-warning text-dark', 'disposed' => 'bg-secondary', default => 'bg-light text-dark border' } }}">
                                    {{ $item->review_status }}
                                </span>
                                @if ($item->review_status === 'deferred')
                                    <div class="small text-body-secondary">{{ __('Until') }} {{ $item->deferred_until?->toDateString() }} — {{ $item->deferral_reason }}</div>
                                @endif
                            </td>
                            <td class="text-end">
                                @if ($item->review_status === 'pending_review')
                                    <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="startReview({{ $item->id }})">{{ __('Review') }}</button>
                                @endif
                                @if ($item->review_status === 'approved')
                                    <button type="button" class="btn btn-outline-danger btn-sm" wire:click="dispose({{ $item->id }})" wire:confirm="{{ __('Dispose of this record? This cannot be undone.') }}">{{ __('Dispose') }}</button>
                                @endif
                            </td>
                        </tr>
                        @if ($reviewingItemId === $item->id)
                            <tr>
                                <td colspan="6">
                                    <div class="row g-2 align-items-end">
                                        <div class="col-auto">
                                            <select class="form-select form-select-sm" wire:model="decision">
                                                <option value="approved">{{ __('Approve') }}</option>
                                                <option value="deferred">{{ __('Defer') }}</option>
                                            </select>
                                        </div>
                                        @if ($decision === 'deferred')
                                            <div class="col-auto"><input type="date" class="form-control form-control-sm" wire:model="deferredUntil"></div>
                                            <div class="col"><input type="text" class="form-control form-control-sm" wire:model="deferralReason" placeholder="{{ __('Deferral reason') }}"></div>
                                        @endif
                                        <div class="col-auto">
                                            <button type="button" class="btn btn-primary btn-sm" wire:click="review">{{ __('Save review') }}</button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr><td colspan="6" class="text-center text-body-secondary py-3">{{ __('Nothing queued for disposal.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
