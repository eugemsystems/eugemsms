<div>
    <h4 class="mb-1">{{ __('Subject access requests') }} 🇿🇼</h4>
    <p class="text-body-secondary small">{{ __('Identity must be verified before any disclosure. The compiled response excludes safeguarding records, medical records and third-party personal data by construction.') }}</p>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Type') }}</th><th>{{ __('Requester') }}</th><th>{{ __('Due by') }}</th><th>{{ __('Verified') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($requests as $request)
                                <tr wire:key="sar-{{ $request->id }}">
                                    <td>{{ $request->request_type }}</td>
                                    <td>{{ $request->requester_name }}</td>
                                    <td>{{ $request->due_by->toDateString() }}</td>
                                    <td>{{ $request->identity_verified ? __('Yes') : __('No') }}</td>
                                    <td><span class="badge bg-light text-dark border">{{ $request->status }}</span></td>
                                    <td class="text-end">
                                        @if (! $request->identity_verified)
                                            <input type="text" class="form-control form-control-sm d-inline-block mb-1" style="width:140px" wire:model="verificationMethod" placeholder="{{ __('Method') }}">
                                            <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="verify({{ $request->id }})">{{ __('Verify') }}</button>
                                        @else
                                            <button type="button" class="btn btn-outline-primary btn-sm" wire:click="compile({{ $request->id }})">{{ __('Compile') }}</button>
                                        @endif
                                    </td>
                                </tr>
                                @if ($selectedRequestId === $request->id && $compiledResponse)
                                    <tr>
                                        <td colspan="6">
                                            <pre class="small bg-light p-2 mb-0">{{ json_encode($compiledResponse, JSON_PRETTY_PRINT) }}</pre>
                                        </td>
                                    </tr>
                                @endif
                            @empty
                                <tr><td colspan="6" class="text-center text-body-secondary py-3">{{ __('No requests received yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card mb-3">
                <div class="card-header">{{ __('Receive request') }}</div>
                <div class="card-body">
                    <select class="form-select mb-2" wire:model="requestType">
                        <option value="access">{{ __('Access') }}</option>
                        <option value="correction">{{ __('Correction') }}</option>
                        <option value="erasure">{{ __('Erasure') }}</option>
                        <option value="portability">{{ __('Portability') }}</option>
                        <option value="objection">{{ __('Objection') }}</option>
                        <option value="restriction">{{ __('Restriction') }}</option>
                    </select>
                    <select class="form-select mb-2" wire:model="subjectType">
                        <option value="student">{{ __('Student') }}</option>
                        <option value="guardian">{{ __('Guardian') }}</option>
                        <option value="staff">{{ __('Staff') }}</option>
                    </select>
                    <input type="number" class="form-control mb-2" wire:model="subjectId" placeholder="{{ __('Subject id (optional)') }}">
                    <input type="text" class="form-control mb-2" wire:model="requesterName" placeholder="{{ __('Requester name') }}">
                    <input type="text" class="form-control mb-2" wire:model="requesterRelationship" placeholder="{{ __('Relationship (optional)') }}">
                    <textarea class="form-control mb-2" wire:model="scopeDescription" placeholder="{{ __('Scope of the request') }}"></textarea>
                    <button type="button" class="btn btn-primary btn-sm" wire:click="receive">{{ __('Receive') }}</button>
                </div>
            </div>
            <div class="card">
                <div class="card-header">{{ __('Refuse a request') }}</div>
                <div class="card-body">
                    <p class="small text-body-secondary">{{ __('Click compile or verify on a row above to select it, then refuse with grounds here if appropriate.') }}</p>
                    <textarea class="form-control mb-2" wire:model="refusalGrounds" placeholder="{{ __('Refusal grounds') }}"></textarea>
                    @if ($selectedRequestId)
                        <button type="button" class="btn btn-outline-danger btn-sm" wire:click="refuse({{ $selectedRequestId }})">{{ __('Refuse selected request') }}</button>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
