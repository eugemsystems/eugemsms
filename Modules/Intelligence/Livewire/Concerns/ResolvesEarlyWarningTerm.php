<?php

declare(strict_types=1);

namespace Modules\Intelligence\Livewire\Concerns;

use Illuminate\Database\Eloquent\Collection;
use Modules\Core\Models\Term;

/**
 * Shared term picking for the INT-03 screens. The term id is a
 * client-tamperable property, so it is always re-resolved against the
 * school here, never trusted.
 */
trait ResolvesEarlyWarningTerm
{
    public ?int $termId = null;

    protected function defaultTermId(): ?int
    {
        return Term::where('school_id', $this->school->id)
            ->where('starts_on', '<=', now())
            ->orderByDesc('starts_on')
            ->value('id');
    }

    protected function selectedTerm(): ?Term
    {
        $this->termId ??= $this->defaultTermId();

        return $this->termId === null ? null : Term::where('school_id', $this->school->id)->find($this->termId);
    }

    /**
     * @return Collection<int, Term>
     */
    protected function termChoices(): Collection
    {
        return Term::where('school_id', $this->school->id)->orderByDesc('starts_on')->limit(12)->get(['id', 'name', 'starts_on']);
    }
}
