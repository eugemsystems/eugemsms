<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Notifications;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Concerns\InteractsWithDataTable;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\NotificationTemplate;
use Modules\Core\Models\School;

/**
 * `Core\Notifications\Templates` (Book A CORE-09 §5, `core.notification.manage_templates`)
 * — this school's own templates plus system defaults (`school_id`
 * null), matching `Templates\Index`'s (CORE-06) own "school + system"
 * listing convention.
 */
#[Title('Notification templates')]
#[Layout('layouts.app')]
final class Templates extends Component
{
    use AuthorizesPermissions;
    use InteractsWithDataTable;
    use InteractsWithSchool;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('core.notification.manage_templates');
    }

    public function render(): View
    {
        $query = NotificationTemplate::query()
            ->where(fn ($q) => $q->where('school_id', $this->school->id)->orWhereNull('school_id'));

        return view('core::notifications.templates', [
            'templates' => $this->paginateDataTable($query, $this->tableColumns()),
        ]);
    }

    /**
     * @return array<string, array{label: string, column?: string, sortable?: bool, searchable?: bool, filter?: string|null, options?: array<int|string, string>}>
     */
    protected function tableColumns(): array
    {
        return [
            'key' => ['label' => __('Key'), 'sortable' => true, 'searchable' => true],
            'channel' => [
                'label' => __('Channel'), 'sortable' => true, 'filter' => 'select',
                'options' => ['sms' => 'SMS', 'whatsapp' => 'WhatsApp', 'email' => __('Email'), 'push' => __('Push'), 'in_app' => __('In-app')],
            ],
            'locale' => ['label' => __('Locale'), 'sortable' => true],
            'school_id' => ['label' => __('Scope'), 'sortable' => true],
            'is_active' => [
                'label' => __('Status'), 'sortable' => true, 'filter' => 'select',
                'options' => ['1' => __('Active'), '0' => __('Inactive')],
            ],
        ];
    }
}
