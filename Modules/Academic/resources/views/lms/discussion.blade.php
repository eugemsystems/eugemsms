<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="{{ route('academic.lms.space', [$school, $space->id]) }}" class="btn btn-sm btn-outline-secondary" wire:navigate><i class="ri ri-arrow-left-line"></i></a>
        <div><h4 class="mb-0">{{ __('Discussion') }}</h4><p class="text-body-secondary small mb-0">{{ __('A hidden post is kept for audit and is invisible to learners; hiding needs a reason.') }}</p></div>
    </div>
    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card mb-3"><ul class="list-group list-group-flush small">
                @forelse ($threads as $thread)
                    <li class="list-group-item d-flex justify-content-between align-items-center {{ $threadId === $thread->id ? 'active' : '' }}" wire:key="th-{{ $thread->id }}">
                        <a href="javascript:void(0)" class="{{ $threadId === $thread->id ? 'text-white' : '' }}" wire:click="openThread({{ $thread->id }})">@if ($thread->is_pinned) <i class="ri ri-pushpin-line"></i> @endif{{ $thread->title }}</a>
                        @if ($thread->is_locked) <i class="ri ri-lock-line"></i> @endif
                    </li>
                @empty <li class="list-group-item text-body-secondary">{{ __('No threads.') }}</li> @endforelse
            </ul></div>
            <div class="card"><div class="card-body">
                <input type="text" class="form-control form-control-sm mb-2" wire:model="newTitle" placeholder="{{ __('New thread title') }}">
                @error('newTitle') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                <button type="button" class="btn btn-primary btn-sm" wire:click="createThread">{{ __('Start thread') }}</button>
            </div></div>
        </div>
        <div class="col-lg-8">
            @if ($current)
                <div class="card">
                    <div class="card-header d-flex flex-wrap gap-2 align-items-center"><strong>{{ $current->title }}</strong>
                        @if ($canModerate) <span class="ms-auto d-flex gap-1">
                            <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="setState({{ $current->id }}, '{{ $current->is_locked ? 'unlock' : 'lock' }}')">{{ $current->is_locked ? __('Unlock') : __('Lock') }}</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="setState({{ $current->id }}, '{{ $current->is_pinned ? 'unpin' : 'pin' }}')">{{ $current->is_pinned ? __('Unpin') : __('Pin') }}</button>
                        </span> @endif
                    </div>
                    <ul class="list-group list-group-flush small">
                        @forelse ($posts as $post)
                            <li class="list-group-item {{ $post->is_hidden ? 'bg-body-tertiary' : '' }}" wire:key="po-{{ $post->id }}">
                                <div class="d-flex justify-content-between"><strong>{{ $post->posted_by_type === 'staff' ? ($staffNames->get($post->posted_by_id)?->fullName() ?? __('Teacher')) : ($studentNames->get($post->posted_by_id)?->fullName() ?? __('Learner')) }}</strong><span class="text-body-secondary">{{ $post->posted_at?->diffForHumans() }}</span></div>
                                @if ($post->is_hidden) <div class="text-body-secondary fst-italic">{{ __('Hidden by a moderator: :reason', ['reason' => $post->hidden_reason]) }}</div> <div style="white-space: pre-line">{{ $post->content }}</div>
                                @else <div style="white-space: pre-line">{{ $post->content }}</div> @if ($canModerate) <button type="button" class="btn btn-sm btn-link text-danger p-0" wire:click="beginHide({{ $post->id }})">{{ __('Hide') }}</button> @endif @endif
                                @if ($hidingId === $post->id) <div class="row g-2 mt-1"><div class="col-8"><input type="text" class="form-control form-control-sm" wire:model="hideReason" placeholder="{{ __('Reason (required)') }}">@error('hideReason') <div class="text-danger small">{{ $message }}</div> @enderror</div><div class="col-4"><button type="button" class="btn btn-danger btn-sm" wire:click="hide">{{ __('Hide post') }}</button></div></div> @endif
                            </li>
                        @empty <li class="list-group-item text-body-secondary">{{ __('No posts yet.') }}</li> @endforelse
                    </ul>
                    @if ($isTeacher && ! $current->is_locked)
                        <div class="card-footer"><textarea class="form-control form-control-sm mb-2" rows="2" wire:model="message" placeholder="{{ __('Reply as the course teacher') }}"></textarea>@error('message') <div class="text-danger small mb-2">{{ $message }}</div> @enderror<button type="button" class="btn btn-primary btn-sm" wire:click="reply">{{ __('Post') }}</button></div>
                    @elseif ($current->is_locked) <div class="card-footer text-body-secondary small">{{ __('This thread is locked.') }}</div> @endif
                </div>
            @else
                <div class="text-body-secondary">{{ __('Choose a thread, or start one.') }}</div>
            @endif
        </div>
    </div>
</div>
