---
paths:
  - 'Modules/Core/Domain/Actions/**'
---

# Core Domain Actions

## Vendor support sessions are read-only at the Action base class
A vendor impersonation session (impersonation_sessions.is_read_only) is enforced in Action::transaction(): any Action that writes while ImpersonationContext holds a read-only session throws IMPERSONATION_READ_ONLY. Only set $allowedDuringReadOnlyImpersonation = true on Actions that must work inside such a session (ending it, switching school/session view). A new Action that writes must call $this->transaction(); one that writes without it bypasses the guard. Vendor sessions need a SupportAccessGrant (tenant admin consent for one ticket, max 72h, revocable); revoking ends open sessions.
