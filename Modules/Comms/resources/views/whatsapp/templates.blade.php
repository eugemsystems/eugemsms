<div>
    <h4 class="mb-1">{{ __('WhatsApp templates') }} 🇿🇼 ⭐</h4>
    <p class="text-body-secondary small">{{ __('Outside the 24-hour session window only an approved template can be sent. Category is fixed at submission and cannot be changed afterwards.') }}</p>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card mb-4">
                <div class="card-header">{{ __('Business accounts') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Number') }}</th><th>{{ __('Quality') }}</th><th>{{ __('Record rating') }}</th></tr></thead>
                        <tbody>
                            @forelse ($accounts as $account)
                                <tr wire:key="waba-{{ $account->id }}">
                                    <td>{{ $account->display_name }}</td>
                                    <td>{{ $account->display_phone_number }}</td>
                                    <td>
                                        <span class="badge {{ $account->quality_rating === 'red' ? 'bg-label-danger' : ($account->quality_rating === 'yellow' ? 'bg-label-warning' : 'bg-label-success') }}">{{ $account->quality_rating ?? 'unrated' }}</span>
                                        @if ($account->isQualityPaused()) <span class="text-danger small">{{ __('non-critical sends paused') }}</span> @endif
                                    </td>
                                    <td>
                                        @foreach (['green', 'yellow', 'red'] as $rating)
                                            <button type="button" class="btn btn-xs btn-outline-secondary" wire:click="recordQuality({{ $account->id }}, '{{ $rating }}')">{{ $rating }}</button>
                                        @endforeach
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('No WhatsApp Business accounts yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header">{{ __('Templates') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Category') }}</th><th>{{ __('Review') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($templates as $template)
                                <tr wire:key="tpl-{{ $template->id }}">
                                    <td>{{ $template->meta_template_name }} <span class="text-body-secondary small">({{ $template->language }})</span></td>
                                    <td>{{ $template->category }}</td>
                                    <td>
                                        <span class="badge {{ $template->review_status === 'approved' ? 'bg-label-success' : ($template->review_status === 'rejected' ? 'bg-label-danger' : 'bg-label-warning') }}">{{ $template->review_status }}</span>
                                        @if ($template->rejection_reason) <div class="small text-danger">{{ $template->rejection_reason }}</div> @endif
                                    </td>
                                    <td class="text-end">
                                        @if ($template->review_status === 'pending')
                                            <button type="button" class="btn btn-sm btn-outline-success" wire:click="recordReview({{ $template->id }}, 'approved')">{{ __('Meta approved') }}</button>
                                            <button type="button" class="btn btn-sm btn-outline-danger" wire:click="recordReview({{ $template->id }}, 'rejected', null, 'Rejected by Meta')">{{ __('Meta rejected') }}</button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('No templates submitted yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card mb-4">
                <div class="card-header">{{ __('Register business account') }}</div>
                <div class="card-body">
                    <select class="form-select mb-2" wire:model="gatewayId">
                        <option value="">{{ __('WhatsApp gateway…') }}</option>
                        @foreach ($whatsAppGateways as $gateway)
                            <option value="{{ $gateway->id }}">{{ $gateway->name }}</option>
                        @endforeach
                    </select>
                    @error('gatewayId') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                    <input type="text" class="form-control mb-2" wire:model="wabaId" placeholder="{{ __('WABA id') }}">
                    <input type="text" class="form-control mb-2" wire:model="displayPhoneNumber" placeholder="{{ __('Display phone number') }}">
                    <input type="text" class="form-control mb-2" wire:model="displayName" placeholder="{{ __('Display name') }}">
                    <button type="button" class="btn btn-primary btn-sm" wire:click="registerAccount">{{ __('Register account') }}</button>
                </div>
            </div>

            <div class="card">
                <div class="card-header">{{ __('Submit template') }}</div>
                <div class="card-body">
                    <select class="form-select mb-2" wire:model="templateWabaId">
                        <option value="">{{ __('Business account…') }}</option>
                        @foreach ($accounts as $account)
                            <option value="{{ $account->id }}">{{ $account->display_name }}</option>
                        @endforeach
                    </select>
                    @error('templateWabaId') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                    <input type="text" class="form-control mb-2" wire:model="metaTemplateName" placeholder="{{ __('template_name_in_snake_case') }}">
                    @error('metaTemplateName') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                    <select class="form-select mb-2" wire:model="category">
                        <option value="utility">{{ __('Utility') }}</option>
                        <option value="marketing">{{ __('Marketing') }}</option>
                        <option value="authentication">{{ __('Authentication') }}</option>
                    </select>
                    <input type="text" class="form-control mb-2" wire:model="language" placeholder="{{ __('Language, e.g. en') }}">
                    <textarea class="form-control mb-1" rows="4" wire:model.live.debounce.300ms="bodyText" placeholder="Hello @{{1}}, your fee balance is @{{2}}."></textarea>
                    <div class="small mb-2 {{ $bodyLength > \Modules\Comms\Livewire\Messaging\WhatsApp\Templates::BODY_LIMIT ? 'text-danger' : 'text-body-secondary' }}">{{ $bodyLength }} / {{ \Modules\Comms\Livewire\Messaging\WhatsApp\Templates::BODY_LIMIT }}</div>
                    @error('bodyText') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                    <input type="text" class="form-control mb-2" wire:model="footerText" placeholder="{{ __('Footer (optional)') }}">
                    <input type="text" class="form-control mb-2" wire:model="notificationKey" placeholder="{{ __('Notification key (optional)') }}">
                    <button type="button" class="btn btn-primary btn-sm" wire:click="submitTemplate">{{ __('Submit for review') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
