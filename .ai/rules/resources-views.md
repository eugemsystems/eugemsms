---
paths:
  - 'Modules/*/resources/views/**'
---

# Resources Views

## Every view/edit screen for a specific model needs a back button
Explicit user directive (2026-09-12): any Livewire screen that shows or edits one specific model instance (a Show/Edit/Form/Editor/Detail/Profile/*Control screen reached by drilling into a record) must have a back button in its header, not just a "Cancel" link at the bottom of a form.

Established markup (see roles/editor.blade.php, schools/partials/tabs.blade.php, sessions/term-detail.blade.php, sessions/period-control.blade.php):
```blade
<div class="d-flex align-items-center gap-2 mb-4">
    <a href="{{ route('parent.index', ...) }}" class="btn btn-icon btn-outline-secondary btn-sm" wire:navigate>
        <i class="ri ri-arrow-left-line"></i>
    </a>
    <div>
        <h4 class="mb-0">{{ ... }}</h4>
    </div>
</div>
```
Top-level list/index screens reachable directly from the sidebar (Users\Index, Roles\Index, Schools\Index, FeatureFlags\Index, etc.) do NOT need this — only screens one level deeper than an index.
