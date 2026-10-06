<div>
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="{{ route('academic.lms.spaces', $school) }}" class="btn btn-sm btn-outline-secondary" wire:navigate><i class="ri ri-arrow-left-line"></i></a>
        <div><h4 class="mb-0">{{ $subject?->name }} — {{ $group?->name }}</h4><p class="text-body-secondary small mb-0">{{ __('Learners see content only while they are enrolled in this teaching group.') }}</p></div>
        <a href="{{ route('academic.lms.discussion', [$school, $space->id]) }}" class="btn btn-sm btn-outline-secondary ms-auto" wire:navigate><i class="ri ri-chat-3-line"></i> {{ __('Discussion') }} ({{ $threadCount }})</a>
    </div>
    <div class="row g-4">
        <div class="col-xl-7">
            <div class="card mb-4">
                <div class="card-header">{{ __('Content') }}</div>
                <ul class="list-group list-group-flush small">
                    @forelse ($items as $item)
                        @php($size = $item->file_size_bytes)
                        <li class="list-group-item d-flex justify-content-between align-items-center" wire:key="ci-{{ $item->id }}">
                            <span><strong>{{ $item->title }}</strong> <span class="text-body-secondary">({{ $item->content_type }})</span><br>
                                @if ($size !== null) <span>{{ $size >= 1048576 ? number_format($size / 1048576, 1).' MB' : number_format($size / 1024).' KB' }}</span> @if ($size >= 10485760) <span class="badge text-bg-warning">{{ __('Large file — Wi-Fi recommended') }}</span> @endif @endif
                                @unless ($item->is_downloadable_offline) <span class="badge text-bg-info">{{ __('Stream only') }}</span> @endunless
                                @if ($item->external_url) <span class="text-body-secondary text-break">{{ $item->external_url }}</span> @endif</span>
                            @if ($item->published_at) <span class="badge text-bg-success">{{ __('Published') }}</span> @else <button type="button" class="btn btn-sm btn-outline-primary" wire:click="publishContent({{ $item->id }})">{{ __('Publish') }}</button> @endif
                        </li>
                    @empty
                        <li class="list-group-item text-body-secondary">{{ __('No content yet.') }}</li>
                    @endforelse
                </ul>
                <div class="card-footer">
                    <div class="row g-2">
                        <div class="col-md-3"><select class="form-select form-select-sm" wire:model="contentType">@foreach (['note', 'worksheet', 'past_paper', 'audio', 'video', 'link', 'folder'] as $t) <option value="{{ $t }}">{{ str_replace('_', ' ', $t) }}</option> @endforeach</select></div>
                        <div class="col-md-5"><input type="text" class="form-control form-control-sm" wire:model="title" placeholder="{{ __('Title') }}"></div>
                        <div class="col-md-4"><select class="form-select form-select-sm" wire:model="fileId"><option value="">{{ __('File from vault…') }}</option>@foreach ($files as $file) <option value="{{ $file->id }}">{{ $file->original_name }} ({{ number_format($file->size_bytes / 1024) }} KB)</option> @endforeach</select></div>
                        <div class="col-md-8"><input type="url" class="form-control form-control-sm" wire:model="externalUrl" placeholder="{{ __('…or a link (http/https)') }}"></div>
                        <div class="col-md-4"><div class="form-check"><input class="form-check-input" type="checkbox" id="off" wire:model="offline"><label class="form-check-label small" for="off">{{ __('Allow offline download') }}</label></div></div>
                    </div>
                    @error('title') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    <button type="button" class="btn btn-primary btn-sm mt-2" wire:click="addContent">{{ __('Add content') }}</button>
                </div>
            </div>
        </div>
        <div class="col-xl-5">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center"><span>{{ __('Assignments') }}</span><a href="{{ route('academic.lms.assignment-create', [$school, $space->id]) }}" class="btn btn-sm btn-primary" wire:navigate>{{ __('New') }}</a></div>
                <ul class="list-group list-group-flush small">
                    @forelse ($assignments as $assignment)
                        <li class="list-group-item" wire:key="as-{{ $assignment->id }}">
                            <div class="d-flex justify-content-between"><strong>{{ $assignment->title }}</strong><span class="badge text-bg-{{ ['published' => 'success', 'closed' => 'secondary'][$assignment->status] ?? 'light border' }}">{{ __(ucfirst($assignment->status)) }}</span></div>
                            <div class="text-body-secondary">{{ __('Due') }} {{ $assignment->due_at?->toDayDateTimeString() }}</div>
                            <div class="mt-1 d-flex gap-1 flex-wrap">
                                @if ($assignment->status === 'draft') <button type="button" class="btn btn-sm btn-outline-primary" wire:click="publishAssignment({{ $assignment->id }})">{{ __('Publish') }}</button> @endif
                                @if ($assignment->status === 'published') <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="closeAssignment({{ $assignment->id }})" wire:confirm="{{ __('Close this assignment to new submissions?') }}">{{ __('Close') }}</button> @endif
                                <a href="{{ route('academic.lms.marking', [$school, $assignment->id]) }}" class="btn btn-sm btn-outline-secondary" wire:navigate>{{ __('Marking') }}</a>
                                <a href="{{ route('academic.lms.non-submission', [$school, $assignment->id]) }}" class="btn btn-sm btn-outline-secondary" wire:navigate>{{ __('Non-submission') }}</a>
                            </div>
                        </li>
                    @empty
                        <li class="list-group-item text-body-secondary">{{ __('No assignments yet.') }}</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</div>
