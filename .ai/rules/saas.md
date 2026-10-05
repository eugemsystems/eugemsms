---
paths:
  - 'Modules/Saas/**'
---

# Saas

## Vendor console: gate every component and mutation, audit every tenant action
The vendor realm is identity-based (users.user_type=vendor + IP allowlist + confirmed 2FA via serp.vendor / EnsureVendorGuard), never a grantable permission. Livewire update requests do not re-run the route's middleware, so every Vendor component calls authorizeVendor() (AuthorizesVendorConsole) in mount, render and each mutating method. Every mutation against a tenant calls RecordVendorConsoleActionAction (activity_log, log_name=vendor_console, BR-SAA-02-007). Vendor screens live under Livewire/Vendor/ (routes vendor.*), school-facing ones under Livewire/Tenant/ (routes account.*); vendor routes are never linked from the school sidebar. Cross-tenant reads of BelongsToSchool models (SupportTicket etc.) need an explicit withoutGlobalScope after authorizeVendor(). Subscription lifecycle moves go through assertMayTransition — never assign status freely.
