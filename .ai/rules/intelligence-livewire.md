---
paths:
  - 'Modules/Intelligence/Livewire/**'
---

# Intelligence Livewire

## Intelligence Livewire screens live under Insights/ — "reports.*" component names collide
Livewire derives component names from the class path under the addLocation() namespace, and Farm/Finance/Payroll/Fiscal already own reports.* names. Put Intelligence screens under Livewire/Insights/ (e.g. Insights/Reports/Builder → insights.reports.builder) and `find Modules -ipath "*Livewire/<Dir>*"` before naming a new top-level directory. Book J §0.2: INT screens are school-facing; the vendor-facing SAA modules must never share a route group, guard, permission or sidebar with them.
