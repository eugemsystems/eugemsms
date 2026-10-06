<div>
    <div class="mb-4"><h4 class="mb-0">{{ __('Enquiry pipeline') }}</h4><p class="text-body-secondary small mb-0">{{ __('Every enquiry by stage. Log each contact; set a follow-up date; mark lost with a reason so the funnel can say why.') }}</p></div>
    <div class="card mb-3"><div class="card-body"><div class="row g-2">
        <div class="col-md-2"><select class="form-select form-select-sm" wire:model="source">@foreach ($sources as $s) <option value="{{ $s }}">{{ ucfirst(str_replace('_', ' ', $s)) }}</option> @endforeach</select></div>
        <div class="col-md-3"><input type="text" class="form-control form-control-sm" wire:model="enquirerName" placeholder="{{ __('Enquirer') }}"></div>
        <div class="col-md-2"><input type="text" class="form-control form-control-sm" wire:model="phone" placeholder="{{ __('Phone') }}"></div>
        <div class="col-md-2"><input type="email" class="form-control form-control-sm" wire:model="email" placeholder="{{ __('Email') }}"></div>
        <div class="col-md-2"><input type="text" class="form-control form-control-sm" wire:model="learnerName" placeholder="{{ __('Learner') }}"></div>
        <div class="col-md-1"><button type="button" class="btn btn-primary btn-sm w-100" wire:click="create">{{ __('Add') }}</button></div>
    </div>@error('enquirerName') <div class="text-danger small mt-2">{{ $message }}</div> @enderror</div></div>
    <div class="d-flex gap-2 overflow-auto pb-2 mb-4">
        @foreach ($columns as $stage => $items)
            <div class="card flex-shrink-0" style="width: 15rem;" wire:key="col-{{ $stage }}"><div class="card-header small d-flex justify-content-between">{{ ucfirst(str_replace('_', ' ', $stage)) }}<span class="badge text-bg-light border">{{ $items->count() }}</span></div>
                <div class="list-group list-group-flush" style="max-height: 22rem; overflow:auto;">
                    @foreach ($items as $item)
                        <button type="button" class="list-group-item list-group-item-action small {{ $openId === $item->id ? 'active' : '' }}" wire:key="e-{{ $item->id }}" wire:click="open({{ $item->id }})">
                            <strong>{{ $item->enquirer_name }}</strong>@if ($item->learner_name)<div>{{ $item->learner_name }}</div>@endif
                            @if ($item->next_follow_up_on) <div class="{{ $item->next_follow_up_on->isPast() ? 'text-danger' : '' }}">{{ __('follow up') }} {{ $item->next_follow_up_on->format('d M') }}</div> @endif
                        </button>
                    @endforeach
                </div></div>
        @endforeach
    </div>
    @if ($open)
        <div class="card"><div class="card-header d-flex justify-content-between"><strong>{{ $open->enquirer_name }}</strong><span class="small">{{ $open->enquirer_phone }} {{ $open->enquirer_email }}</span></div><div class="card-body"><div class="row g-4">
            <div class="col-lg-6">
                <div class="row g-2 mb-2"><div class="col-4"><select class="form-select form-select-sm" wire:model="activityType">@foreach ($activityTypes as $t) <option value="{{ $t }}">{{ ucfirst($t) }}</option> @endforeach</select></div><div class="col-8"><input type="text" class="form-control form-control-sm" wire:model="summary" placeholder="{{ __('What happened?') }}"></div></div>
                <div class="row g-2 mb-2"><div class="col-6"><input type="date" class="form-control form-control-sm" wire:model="followUp"></div><div class="col-6"><button type="button" class="btn btn-primary btn-sm w-100" wire:click="log">{{ __('Log activity') }}</button></div></div>
                @error('summary') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                <div class="d-flex flex-wrap gap-1 mb-2">@foreach (['information_sent', 'visit_booked', 'visited', 'applied'] as $next) <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="advance('{{ $next }}')">→ {{ ucfirst(str_replace('_', ' ', $next)) }}</button> @endforeach</div>
                <div class="input-group input-group-sm"><select class="form-select" wire:model="lostReason"><option value="">{{ __('Lost because…') }}</option>@foreach ($lostReasons as $r) <option value="{{ $r }}">{{ ucfirst(str_replace('_', ' ', $r)) }}</option> @endforeach</select><button type="button" class="btn btn-outline-danger" wire:click="advance('lost')">{{ __('Mark lost') }}</button></div>
                @error('lostReason') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
            <div class="col-lg-6"><ul class="list-group list-group-flush small">@forelse ($activities as $a) <li class="list-group-item" wire:key="a-{{ $a->id }}"><span class="badge text-bg-light border">{{ $a->activity_type }}</span> {{ $a->summary }} <span class="text-body-secondary">{{ $a->occurred_at->format('d M H:i') }}</span></li> @empty <li class="list-group-item text-body-secondary">{{ __('No activity yet.') }}</li> @endforelse</ul></div>
        </div></div></div>
    @endif
</div>
