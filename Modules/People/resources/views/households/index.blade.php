<div>
    <div class="mb-4"><h4 class="mb-0">{{ __('Households') }}</h4><p class="text-body-secondary small mb-0">{{ __('Group learners and guardians for sibling discounts and combined statements. Membership is dated.') }}</p></div>
    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card mb-3"><div class="card-body"><input type="text" class="form-control form-control-sm mb-2" wire:model="name" placeholder="{{ __('New household name, e.g. Moyo Family') }}">@error('name') <div class="text-danger small mb-2">{{ $message }}</div> @enderror<button type="button" class="btn btn-primary btn-sm" wire:click="create">{{ __('Create') }}</button></div></div>
            <div class="list-group">@foreach ($households as $household) <button type="button" class="list-group-item list-group-item-action d-flex justify-content-between {{ $selectedId === $household->id ? 'active' : '' }}" wire:key="h-{{ $household->id }}" wire:click="open({{ $household->id }})">{{ $household->name }} <span class="badge text-bg-light border">{{ $household->active_members }}</span></button> @endforeach</div>
        </div>
        <div class="col-lg-8">
            @if ($selectedId)
                <div class="card mb-3"><div class="card-body d-flex flex-wrap gap-3 align-items-center">
                    <div class="form-check"><input class="form-check-input" type="checkbox" id="sd" wire:model="siblingDiscountEligible"><label class="form-check-label" for="sd">{{ __('Counts for sibling discounts') }}</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" id="cs" wire:model="combinedStatement"><label class="form-check-label" for="cs">{{ __('Combined statement') }}</label></div>
                    <button type="button" class="btn btn-outline-primary btn-sm" wire:click="saveSettings">{{ __('Save') }}</button>
                </div></div>
                <div class="card mb-3"><div class="card-header">{{ __('Members') }}</div><ul class="list-group list-group-flush">
                    @forelse ($members as $member)
                        <li class="list-group-item d-flex justify-content-between" wire:key="m-{{ $member->id }}"><span>@if ($member->member_type === 'student') {{ $students->get($member->member_id)?->fullName() }} <span class="badge text-bg-light border">{{ __('learner') }}</span> @else {{ $guardians->get($member->member_id)?->displayName() }} <span class="badge text-bg-light border">{{ __('guardian') }}</span> @endif <span class="small text-body-secondary">{{ __('since') }} {{ $member->joined_on->format('d M Y') }}</span></span>
                            <button type="button" class="btn btn-sm btn-outline-danger" wire:click="removeMember('{{ $member->member_type }}', {{ $member->member_id }})">{{ __('Remove') }}</button></li>
                    @empty <li class="list-group-item text-body-secondary">{{ __('No members yet.') }}</li> @endforelse
                </ul></div>
                <div class="card"><div class="card-body">
                    <div class="btn-group btn-group-sm mb-2"><input type="radio" class="btn-check" id="mt-s" value="student" wire:model.live="memberType"><label class="btn btn-outline-secondary" for="mt-s">{{ __('Learner') }}</label><input type="radio" class="btn-check" id="mt-g" value="guardian" wire:model.live="memberType"><label class="btn btn-outline-secondary" for="mt-g">{{ __('Guardian') }}</label></div>
                    <input type="search" class="form-control form-control-sm mb-1" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search to add a member') }}">
                    @foreach ($matches as $match) <button type="button" class="list-group-item list-group-item-action small border rounded mb-1" wire:key="x-{{ $match['id'] }}" wire:click="addMember({{ $match['id'] }})">{{ $match['label'] }}</button> @endforeach
                </div></div>
            @else <div class="text-body-secondary">{{ __('Choose or create a household.') }}</div> @endif
        </div>
    </div>
</div>
