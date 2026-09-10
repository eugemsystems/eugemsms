## Admin UI — no Flux

The Livewire admin panel does not use Livewire Flux. Admin components are
hand-built from the existing HTML template at `TEMPLATE/html-laravel/` in
the project root — this is the "in-house component library" option from
the tech stack table, not a deviation from it. Full rule and rationale:
`.claude/rules/livewire-admin-ui.md` (auto-loads whenever a Livewire view
or component is touched). `TEMPLATE/` also holds the Next.js template for
later — leave it untouched until that phase.
