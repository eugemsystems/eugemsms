---
paths:
  - 'Modules/Comms/**'
---

# Comms

## Livewire component names collide across modules — put every Comms screen under a unique namespace
Livewire derives a component's name from its class path relative to the `Livewire::addLocation()` namespace, so `Modules\Comms\Livewire\Gateways\Index` and Finance's own `Modules\Finance\Livewire\Gateways\Index` both resolve to `gateways.index`. `Livewire::test()`/routes mount the right class, but every subsequent update request (`->call()`, `wire:click`) re-hydrates whichever class the name resolves to — here Finance's, which silently ran `finance.gateway.manage` against the Comms screen and 403'd, surfacing only as "Invalid Livewire snapshot structure".

How to apply: before naming a new Livewire directory, `find Modules -path '*Livewire/<Dir>*'`. Comms screens live under `Livewire/Messaging/` (COM-01) for this reason; give later COM modules their own unique top-level directory too (e.g. `Automation/`, `Portal/`, `Calendar/`, `Meetings/`, `Feedback/`) and check for clashes first.

## COM-01 admin screens: credentials are write-only, webhook log is metadata-only
`MessageGateway.credentials`/`webhook_secret` are `encrypted` casts and must never be selected into a view (BR-COM-01-001) — the gateway list selects an explicit non-secret column list. `messaging_gateway_webhooks.raw_headers`/`raw_payload` can carry recipient numbers and are never displayed; the log selects receipt metadata only.
