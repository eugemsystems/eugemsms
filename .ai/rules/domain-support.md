---
paths:
  - 'Modules/*/Domain/Support/*.php'
---

# Domain Support

## SettingResolver returns cast default, not null, for unconfigured settings
An int-typed setting with default_value '' resolves via SettingCaster to (int) '' = 0, never null — checking `=== null` to detect "not configured yet" silently fails and lets 0 flow through as a real ID. When a setting names a staff/user id, check by resolving the row (e.g. `Staff::find((int) $value) === null`) instead of comparing the raw setting value to null. Hit this in Modules/Welfare/Domain/Support/SafeguardingRouterImpl.php with safeguarding.lead_staff_id.
