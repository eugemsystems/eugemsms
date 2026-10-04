<div>
    <h4 class="mb-1">{{ __('Linen clearance') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Outstanding returnable items block clearance, and therefore transfer-out.') }}</p>

    <div class="card mb-4">
        <div class="card-body row g-2 align-items-end">
            <div class="col-md-5">
                <select class="form-select" wire:model="studentId">
                    <option value="">{{ __('Select learner') }}</option>
                    @foreach ($students as $student)
                        <option value="{{ $student->id }}">{{ $student->first_name }} {{ $student->last_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2"><button type="button" class="btn btn-primary w-100" wire:click="check">{{ __('Check') }}</button></div>
        </div>
    </div>

    @if ($checked)
        <div class="alert {{ $isClear ? 'alert-success' : 'alert-danger' }}">
            {{ $isClear ? __('Clear — no outstanding returnable items.') : __('Blocked — outstanding items must be returned or charged first.') }}
        </div>

        @if (! $isClear)
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Item') }}</th><th>{{ __('Status') }}</th></tr></thead>
                        <tbody>
                            @foreach ($outstanding as $item)
                                <tr><td>{{ $item->issuableItem->name }}</td><td>{{ ucfirst($item->status) }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @endif
</div>
