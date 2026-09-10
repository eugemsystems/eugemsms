# sERP — Enterprise School Management Platform
## Volume 2 · Detailed Functional & Technical Specification
### Book I — Domain G: Communication & Portals (`COM-01` → `COM-08`)

| Field | Value |
|---|---|
| Document | Volume 2, Book I of 10 |
| Covers | Messaging Gateways · Automation Rules · Parent/Learner/Staff Portal Services · Calendar & Notices · Virtual Meetings · Feedback & Complaints |
| Status | Build-ready specification |
| Version | 1.0 |
| Date | September 2026 |
| Prerequisites | **Books A–H3 complete.** This book is a consumer of `CORE-09`, not a replacement for it — see §0.1 before writing any code. |
| Next book | Book J — Intelligence & SaaS Control (final book) |

---

## Part 0 — Read This First

### 0.1 ⭐ The boundary with `CORE-09` — get this wrong and you build it twice

Book A already built the notification bus: recipient resolution, template rendering, quiet hours, budget caps, deduplication, opt-out, delivery tracking, the in-app inbox. That machinery is not repeated here. If you find yourself writing a second recipient-resolution algorithm or a second budget cap in this book, stop — you are duplicating `CORE-09` and the two will drift apart within a year.

**What `CORE-09` owns (Book A):** the pipe. Every module in the platform calls `NotificationDispatcher::send($key, $context)` and the bus handles who receives it, on what channel, with what template, subject to what budget.

**What this book owns:** the two things `CORE-09` deliberately left as pluggable.

```
CORE-09 (Book A)                          COM-01 / COM-02 (this book)
─────────────────                          ─────────────────────────
"Send fee.payment_received                 The ACTUAL SMS aggregator, WhatsApp
 to the fee-responsible guardian"          Business API, and email provider that
                                            physically move the message
        ↕                                          ↕
 recipient resolution                      WhatsApp template approval status,
 template rendering                        SMS sender ID registration,
 budget & quiet hours                      per-segment cost calculation
 delivery tracking                         provider webhook ingestion
 in-app inbox                              gateway health and failover

 "WHICH events trigger WHICH               COM-02: the rule that says
  notifications, for whom,                 "balance overdue 30 days AND
  under what condition"                    no active payment plan
                                            → fee.overdue.stage_2
                                            → whatsapp then sms fallback
                                            → skip if sent in last 7 days"
```

`COM-01` is the **driver layer** `CORE-09`'s `NotificationChannelDriver` interface was written against. `COM-02` is the **decision layer** that turns a raw domain event, or a scheduled scan of the data, into a specific dispatch call. Neither re-implements recipient resolution, rendering, or budget enforcement — both call into `CORE-09` for all of it.

### 0.2 The portal API philosophy — this book does not re-list forty endpoints

Every module from `PPL-01` to `FIN-14` already specified its own API surface: `GET /students/{ulid}/results`, `GET /me/children`, `GET /students/{ulid}/balance`, and so on. `COM-03`, `COM-04` and `COM-05` do not repeat that catalogue. They specify:

1. **Dashboard aggregation** — the single low-latency call that composes many of those granular endpoints into one screen load, because a parent on a 2G connection in rural Manicaland should not make eleven requests to see their child's status.
2. **The app shell** — offline cache strategy, first-login onboarding, widget configuration, deep linking.
3. **Genuinely portal-owned data** — the things that have no other natural home, like which dashboard widgets a school has enabled.

If a screen just displays data another module already exposes, this book links to that module's section rather than reprinting it.

### 0.3 Build order

```
COM-01  Messaging Gateways         ← implements the CORE-09 driver interface
   ↓
COM-02  Automation Rules           ← decides when COM-01/CORE-09 fires
   ↓
COM-03/04/05  Portal Services      ← the app shell; needs COM-01/02 for push
   ↓
COM-06  Calendar & Notices    ─┐
COM-07  Virtual Meetings       ├─ independent; parallel
COM-08  Feedback & Complaints ─┘
```

---

# COM-01 · Messaging Gateways & Delivery 🇿🇼

### 1. Scope

**In scope.** Gateway/provider registry and credentials, WhatsApp Business template registration and approval tracking, SMS sender ID registration, message segmentation and cost calculation, webhook ingestion, provider health checks and failover, cost reconciliation.

**Out of scope.** Recipient resolution, template *content* rendering, budget enforcement, quiet hours, deduplication, the in-app inbox — all `CORE-09`.

### 2. Data model

```sql
message_gateways
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
channel                 VARCHAR(20)  NOT NULL   -- sms|whatsapp|email|push
driver                  VARCHAR(30)  NOT NULL   -- africas_talking|bulksms_zw|
                                                -- econet_bulk|twilio|
                                                -- whatsapp_cloud_api|
                                                -- whatsapp_360dialog|smtp|
                                                -- ses|fcm|apns
name                    VARCHAR(120) NOT NULL
credentials             TEXT         NOT NULL   -- ENCRYPTED
webhook_secret          VARCHAR(200) NULL       -- ENCRYPTED
is_default              TINYINT(1)   NOT NULL DEFAULT 0
is_sandbox               TINYINT(1)   NOT NULL DEFAULT 0
priority                SMALLINT     NOT NULL DEFAULT 0   -- failover order
last_health_check_at    TIMESTAMP    NULL
health_status           VARCHAR(20)  NULL       -- up|degraded|down
is_active               TINYINT(1)   NOT NULL DEFAULT 0
created_by, created_at, updated_at
  UNIQUE (school_id, channel, driver)
  INDEX  (school_id, channel, is_active, priority)

sender_ids                            -- 🇿🇼 SMS alphanumeric ID per network
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
gateway_id              BIGINT       FK INDEX
sender_id                VARCHAR(20)  NOT NULL   -- 'SGCollege' (max 11 GSM chars)
network                 VARCHAR(20)  NULL        -- econet|netone|telecel|all
registration_reference  VARCHAR(80)  NULL
status                  VARCHAR(20)  NOT NULL    -- pending|approved|
                                                 -- rejected|expired
submitted_at            TIMESTAMP    NULL
approved_at             TIMESTAMP    NULL
expires_on              DATE         NULL
rejection_reason        VARCHAR(255) NULL
  UNIQUE (school_id, gateway_id, sender_id, network)

whatsapp_business_accounts
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
gateway_id              BIGINT       FK
waba_id                 VARCHAR(60)  NOT NULL   -- Meta-assigned account ID
display_phone_number    VARCHAR(30)  NOT NULL
display_name            VARCHAR(120) NOT NULL
display_name_status     VARCHAR(20)  NULL       -- approved|pending|rejected
quality_rating          VARCHAR(20)  NULL       -- green|yellow|red — ⭐ throttles sends
messaging_limit_tier    VARCHAR(30)  NULL       -- 250|1k|10k|100k|unlimited per 24h
verified_at             TIMESTAMP    NULL
status                  VARCHAR(20)  NOT NULL   -- active|restricted|banned
  UNIQUE (school_id, waba_id)

whatsapp_templates                    -- ⭐ Meta pre-approval required
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
waba_id                 BIGINT       FK
notification_key        VARCHAR(80)  NULL       -- links to CORE-09 template key
meta_template_name      VARCHAR(120) NOT NULL   -- lowercase_snake, Meta's rules
category                VARCHAR(20)  NOT NULL   -- utility|marketing|authentication
language                VARCHAR(10)  NOT NULL   -- en|sn|nd
header_type             VARCHAR(20)  NULL       -- none|text|image|document
body_text                TEXT         NOT NULL   -- with {{1}} {{2}} placeholders
footer_text              VARCHAR(120) NULL
buttons                 JSON         NULL
submitted_at            TIMESTAMP    NULL
review_status            VARCHAR(20)  NOT NULL   -- draft|pending|approved|
                                                 -- rejected|paused|disabled
rejection_reason        VARCHAR(255) NULL
approved_at              TIMESTAMP    NULL
meta_template_id        VARCHAR(60)  NULL
  UNIQUE (school_id, meta_template_name, language)
  INDEX  (school_id, review_status)

message_conversations                 -- ⭐ WhatsApp 24-hour session window
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
waba_id                 BIGINT       FK
contact_phone           VARCHAR(30)  NOT NULL
last_inbound_at         TIMESTAMP    NULL       -- last message FROM the contact
session_expires_at      TIMESTAMP    NULL       -- last_inbound_at + 24h
conversation_category   VARCHAR(20)  NULL       -- service|utility|marketing
opened_by_template_id   BIGINT       NULL FK
  UNIQUE (school_id, waba_id, contact_phone)
  INDEX  (school_id, session_expires_at)

message_segments                      -- ⭐ SMS cost calculation, per send
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK
notification_id         BIGINT       FK INDEX   -- CORE-09 notifications.id
encoding                VARCHAR(10)  NOT NULL    -- gsm7|ucs2
character_count         SMALLINT     NOT NULL
segment_count           SMALLINT     NOT NULL
rate_card_id            BIGINT       NULL FK
cost_minor              BIGINT       NULL
currency                CHAR(3)      NULL

provider_rate_cards
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       NULL FK    -- null = system default
gateway_id              BIGINT       FK INDEX
destination_prefix      VARCHAR(10)  NOT NULL   -- '263' (Zimbabwe), '27', 'intl'
rate_per_segment_minor  BIGINT       NOT NULL
whatsapp_utility_rate_minor BIGINT   NULL
whatsapp_marketing_rate_minor BIGINT NULL
currency                CHAR(3)      NOT NULL
effective_from          DATE         NOT NULL
effective_to            DATE         NULL
  INDEX (gateway_id, destination_prefix, effective_from)

gateway_webhooks                      -- APPEND-ONLY, mirrors FIN-05's pattern
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       NULL FK
gateway_id              BIGINT       NULL FK
driver                  VARCHAR(30)  NOT NULL
event_type              VARCHAR(60)  NULL       -- delivered|read|failed|
                                                -- inbound_message|template_status
raw_headers             JSON         NOT NULL
raw_payload              LONGTEXT     NOT NULL
payload_hash            CHAR(64)     NOT NULL
signature_valid          TINYINT(1)   NOT NULL
notification_id          BIGINT       NULL FK
processing_status        VARCHAR(20)  NOT NULL   -- received|processed|
                                                 -- ignored|failed|duplicate
received_at              TIMESTAMP(6) NOT NULL
processed_at             TIMESTAMP    NULL
  UNIQUE (driver, payload_hash)
  INDEX  (processing_status, received_at)

gateway_cost_reconciliation
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
gateway_id              BIGINT       FK
period_month            CHAR(7)      NOT NULL
system_recorded_minor   BIGINT       NOT NULL   -- sum of message_segments cost
provider_invoiced_minor BIGINT       NULL       -- from provider statement
variance_minor          BIGINT       NULL
provider_statement_file_id BIGINT    NULL FK
status                  VARCHAR(20)  NOT NULL   -- pending|reconciled|
                                                -- variance|disputed
reconciled_by           BIGINT       NULL FK
  UNIQUE (school_id, gateway_id, period_month)
```

### 3. ⭐ The `NotificationChannelDriver` implementation

```php
interface NotificationChannelDriver   // defined in CORE-09, implemented here
{
    public function send(RenderedNotification $n): DispatchResult;
    public function verifyWebhook(array $headers, string $body): bool;
    public function parseWebhook(array $headers, string $body): WebhookEvent;
    public function healthCheck(): HealthStatus;
}

final class WhatsAppCloudApiDriver implements NotificationChannelDriver
{
    public function send(RenderedNotification $n): DispatchResult
    {
        $conversation = $this->conversations->for($n->waba, $n->recipientPhone);

        // ⭐ Inside the 24-hour session window → free-form allowed
        if ($conversation?->isSessionOpen()) {
            return $this->sendSessionMessage($n);
        }

        // Outside it → a pre-approved template is the ONLY option
        $template = $this->templates->approvedFor($n->key, $n->locale);
        if ($template === null) {
            throw new NoApprovedTemplateException($n->key, $n->locale);
        }

        return $this->sendTemplateMessage($n, $template);
    }
}
```

### 4. ⭐ SMS segmentation — the cost trap specific to this market

A GSM-7 SMS carries 160 characters per segment. The moment a message contains a character outside the GSM-7 alphabet, the **entire message** drops to UCS-2 encoding at 70 characters per segment — not just the offending character. Zimbabwean names, and chiShona/isiNdebele text, routinely contain characters that trigger this: curly quotes pasted from Word, certain accented letters, and em-dashes are the usual culprits.

```
"Dear Mr Moyo, Tinashe's balance is $450.00 as at 30 Sept."
   → the apostrophe in "Tinashe's" is a curly ' (U+2019), not a straight ' (U+0027)
   → forces UCS-2
   → 60 characters, but at 70/segment in UCS-2 that's still 1 segment... narrowly

"Dear Mr Moyo — Tinashe's balance is $450.00 as at 30 Sept."
   → the em-dash forces UCS-2
   → now 2 segments instead of 1, DOUBLING the SMS cost
   → across 1,400 learners' fee reminders, that is a real line item
```

| # | Rule |
|---|---|
| Every outbound SMS is normalised — curly quotes to straight, em/en-dashes to hyphens — **before** encoding is calculated, and the normalisation is applied to the rendered text, not silently to the stored template. |
| `message_segments` records the actual encoding and segment count used, so cost reporting is exact rather than estimated. |
| The template editor (`CORE-09`) shows a live character-and-segment counter as an administrator types, with a warning the moment a character would force UCS-2. |

### 5. Business rules

| ID | Rule |
|---|---|
| `BR-COM-01-001` | Every gateway's credentials are encrypted at rest, masked in the UI, and absent from every export and log. |
| `BR-COM-01-002` | A school may configure multiple gateways per channel. `priority` determines the failover order; `CORE-09`'s dispatch calls the highest-priority healthy gateway. |
| `BR-COM-01-003` ⭐ | A WhatsApp message outside the 24-hour session window **requires an approved template**. Attempting free-form text outside the window is refused before it reaches the provider, not after a rejection comes back. |
| `BR-COM-01-004` | `message_conversations.session_expires_at` updates on every inbound message from the contact, extending the free-form window. |
| `BR-COM-01-005` | A `CORE-09` notification key requiring a WhatsApp template cannot fire on that channel until its template's `review_status = approved`. Unapproved-template sends fall back to the next configured channel automatically. |
| `BR-COM-01-006` | Template category (utility, marketing, authentication) is declared at submission and cannot be changed post-approval without resubmission — Meta prices and restricts these differently, and misdeclaring one is a policy violation that risks the account. |
| `BR-COM-01-007` ⭐ | WhatsApp quality rating is monitored. A rating dropping to `red` pauses non-critical outbound sends on that account and alerts the administrator — a banned WhatsApp Business Account is a far larger loss than a paused campaign. |
| `BR-COM-01-008` | Sender ID registration status gates SMS sends per network. A pending or rejected sender ID on a given network routes to a fallback generic sender ID or a different gateway, never silently to an unregistered one. |
| `BR-COM-01-009` ⭐ | Every outbound SMS is normalised for GSM-7 compatibility before encoding is calculated, and the actual segment count is recorded, not estimated. |
| `BR-COM-01-010` | Webhook signature verification, replay protection by payload hash, and idempotent processing follow exactly the pattern established in `FIN-05` §5 — the same control, the same reason. |
| `BR-COM-01-011` | Gateway health checks run on a schedule. A gateway marked `down` is excluded from failover routing until it recovers, and `CORE-09` is notified so quiet-hours-exempt urgent sends still route correctly. |
| `BR-COM-01-012` | Cost reconciliation compares system-recorded spend against the provider's monthly statement. Variance beyond tolerance is investigated, not absorbed. |
| `BR-COM-01-013` | Push notification payloads (`FCM`/`APNs`) carry no personally identifiable content beyond what is needed to route the tap — the message body itself is fetched from the API on open, not embedded in the push payload, since push payloads are less securely transported than the API. |

### 6. Screens · API · Settings

| Screen | Component | Permission |
|---|---|---|
| Gateway configuration | `Comms\Gateways\Index` | `comms.gateway.manage` ⚠⚠ — credentials masked, health status, test send |
| **WhatsApp templates** | `Comms\WhatsApp\Templates` | `comms.template.manage` ⭐ — compose, submit, track Meta review status, live character/placeholder validation |
| Sender IDs | `Comms\Sms\SenderIds` | `comms.sender_id.manage` — per-network status |
| **Cost & segmentation** | `Comms\Reports\Cost` | `comms.report.view` — spend by channel, encoding breakdown, segment waste report (messages that could have been 1 segment but hit 2) |
| Reconciliation | `Comms\Reports\Reconciliation` | `comms.reconciliation.manage` |
| Webhook log | `Comms\Gateways\Webhooks` | `comms.gateway.view` |

```
POST /webhooks/messaging/{driver}        PUBLIC, signature-verified
GET  /api/v1/comms/gateway-health        admin dashboard
```

| Setting | Type | Default |
|---|---|---|
| `comms.whatsapp_quality_pause_threshold` | enum | `red` |
| `comms.sms_normalise_before_send` | bool | `true` (**locked**) |
| `comms.gateway_health_check_minutes` | int | `5` |
| `comms.cost_reconciliation_tolerance_percent` | int | `3` |

Events: `GatewayHealthChanged` ⚠ · `TemplateSubmitted` · `TemplateApproved` · `TemplateRejected` ⚠ · `WhatsAppQualityDegraded` ⚠⚠ · `SenderIdRegistered` · `SegmentCountExceededExpected` · `CostReconciliationVariance` ⚠

### 7. Acceptance criteria

```gherkin
AC-COM-01-001
  Given a parent last messaged the school's WhatsApp number 30 hours ago
  When the school attempts a free-form WhatsApp message
  Then it is refused before reaching the provider
  And an approved template is used instead, or the send falls back to SMS

AC-COM-01-002
  Given a notification key requires a WhatsApp template not yet approved
  Then the send falls back to the next configured channel automatically

AC-COM-01-003
  Given a message body contains a curly apostrophe and an em-dash
  Then it is normalised to GSM-7-safe characters before encoding is calculated
  And the recorded segment count reflects the normalised text

AC-COM-01-004
  Given a WhatsApp Business Account's quality rating drops to red
  Then non-critical sends on that account pause
  And the administrator is alerted

AC-COM-01-005
  Given the same webhook is delivered five times by the SMS provider
  Then exactly one delivery status update is applied

AC-COM-01-006
  Given monthly system-recorded SMS spend is USD 412 and the provider
       invoice is USD 460
  Then the variance is flagged for investigation, not absorbed
```

---

# COM-02 · Event-Driven Automation Rules

### 1. Scope

**In scope.** The rule registry mapping business events and scheduled data conditions to specific `CORE-09` dispatch decisions, condition evaluation for both instant and scanned triggers, template and channel overrides per rule, throttling, cost estimation before activation, A/B testing, execution logging.

**Out of scope.** Everything `CORE-09` already owns — see §0.1.

### 2. Data model

```sql
automation_rules
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
name                    VARCHAR(150) NOT NULL   -- 'Fee overdue — 30 day notice'
notification_key        VARCHAR(80)  NOT NULL   -- the CORE-09 key it dispatches
trigger_type            VARCHAR(20)  NOT NULL   -- event | scheduled_scan
event_name              VARCHAR(80)  NULL       -- 'InvoiceOverdue' etc, if event
schedule_cron           VARCHAR(60)  NULL       -- if scheduled_scan
scan_entity              VARCHAR(40)  NULL       -- 'invoice','student','staff_document'
audience_override       VARCHAR(30)  NULL       -- null = use CORE-09 default
channel_override        JSON         NULL       -- null = use CORE-09 default
template_key_override   VARCHAR(80)  NULL
delay_minutes           INT          NOT NULL DEFAULT 0
throttle_key            VARCHAR(120) NULL       -- dedupe scope beyond CORE-09 default
throttle_window_hours   INT          NULL
is_active               TINYINT(1)   NOT NULL DEFAULT 1
estimated_monthly_cost_minor BIGINT  NULL       -- ⭐ shown before activation
estimated_monthly_currency CHAR(3)   NULL
created_by, updated_by, created_at, updated_at
  UNIQUE (school_id, name)
  INDEX  (school_id, trigger_type, is_active)
  INDEX  (school_id, event_name)

**Correction made during COM-02 implementation**: the `condition_expression JSON` column shown above in earlier drafts is not built — it would duplicate `rule_conditions` below, the normalized table that already holds the exact same condition data in queryable, field-whitelist-validatable rows (what the visual rule builder actually reads and writes). `rule_conditions` is this module's single source of truth for a rule's conditions.

rule_conditions                       -- structured, so the builder can render it visually
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
rule_id                 BIGINT       FK INDEX
group_id                SMALLINT     NOT NULL DEFAULT 1   -- AND within a group
group_logic             VARCHAR(5)   NOT NULL DEFAULT 'AND' -- groups combine by OR
field                   VARCHAR(80)  NOT NULL   -- 'invoice.days_overdue'
operator                VARCHAR(20)  NOT NULL   -- eq|neq|gt|gte|lt|lte|in|
                                                -- not_in|contains|is_null
value                   JSON         NOT NULL
sort_order              SMALLINT

rule_template_variants                -- lightweight A/B testing
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
rule_id                 BIGINT       FK INDEX
variant_key             VARCHAR(20)  NOT NULL   -- 'A','B'
template_key            VARCHAR(80)  NOT NULL
weight_percent          SMALLINT     NOT NULL DEFAULT 50
sent_count               INT          NOT NULL DEFAULT 0
opened_count             INT          NOT NULL DEFAULT 0    -- WhatsApp read receipts
response_count           INT          NOT NULL DEFAULT 0    -- e.g. payment within 48h
  UNIQUE (rule_id, variant_key)

rule_executions                       -- APPEND-ONLY
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
rule_id                 BIGINT       FK INDEX
trigger_source          VARCHAR(60)  NOT NULL   -- event name or 'scan'
subject_type            VARCHAR(60)  NULL       -- 'invoice','student'
subject_id               BIGINT       NULL
matched                 TINYINT(1)   NOT NULL
skip_reason             VARCHAR(60)  NULL       -- throttled|already_sent|
                                                -- condition_not_met|
                                                -- module_disabled
notification_id          BIGINT       NULL FK   -- CORE-09 notifications.id
variant_key              VARCHAR(20)  NULL
executed_at              TIMESTAMP(6) NOT NULL
  INDEX (school_id, rule_id, executed_at)

scan_runs                             -- for scheduled_scan rules
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
rule_id                 BIGINT       FK INDEX
ran_at                  TIMESTAMP    NOT NULL
records_scanned          INT          NOT NULL DEFAULT 0
records_matched          INT          NOT NULL DEFAULT 0
notifications_dispatched INT          NOT NULL DEFAULT 0
duration_ms               INT
status                    VARCHAR(20)  NOT NULL   -- completed|failed
error                     TEXT         NULL
```

### 3. ⭐ Two trigger shapes, one engine

**Event-triggered** — a domain event fires (`SubjectEnrolmentDropped`, `SickBayAdmission`, `ExeatOverdue`) and the rule evaluates its conditions against the event's payload immediately.

**Scan-triggered** — nothing "happens" at the relevant moment; a condition becomes true purely by time passing. *"Balance overdue 30 days"* is not an event anyone raises — it is a fact that becomes true at midnight on day 30. These rules run on a schedule, scan the relevant entity, and evaluate conditions per record.

```php
// Scan-triggered example: the rule from the worked scenario in §0.1
{
  "name": "Fee overdue — 30 day notice",
  "trigger_type": "scheduled_scan",
  "schedule_cron": "0 8 * * *",
  "scan_entity": "invoice",
  "notification_key": "fee.overdue.stage_2",
  "condition_expression": {
    "groups": [{
      "logic": "AND",
      "conditions": [
        { "field": "invoice.balance_minor",        "operator": "gt",  "value": 0 },
        { "field": "invoice.days_overdue",          "operator": "gte", "value": 30 },
        { "field": "student.has_active_payment_plan","operator": "eq",  "value": false }
      ]
    }]
  },
  "throttle_key": "invoice.id",
  "throttle_window_hours": 168
}
```

```php
final class EvaluateScanRuleAction extends Action
{
    public function execute(AutomationRule $rule): ScanResult
    {
        $candidates = $this->entityScanner->for($rule->scan_entity, $rule->school_id);
        $matched = 0; $dispatched = 0;

        foreach ($candidates->chunk(500) as $chunk) {
            foreach ($chunk as $record) {
                if (! $this->conditions->evaluate($rule, $record)) continue;
                $matched++;

                if ($this->throttle->wasRecentlySent($rule, $record)) {
                    $this->log($rule, $record, matched: true, skip: 'throttled');
                    continue;
                }

                $variant = $this->pickVariant($rule);
                $this->dispatcher->send(   // ⭐ hands off to CORE-09 entirely
                    key: $variant->template_key ?? $rule->notification_key,
                    context: $record,
                    audienceOverride: $rule->audience_override,
                    channelOverride: $rule->channel_override,
                );
                $dispatched++;
            }
        }
        return new ScanResult($candidates->count(), $matched, $dispatched);
    }
}
```

### 4. Business rules

| ID | Rule |
|---|---|
| `BR-COM-02-001` | Event-triggered rules subscribe to a specific domain event name; a rule referencing an event no module actually publishes fails validation at save time. |
| `BR-COM-02-002` | Scan-triggered rules run on their own schedule, independent of any other module's jobs, and are queued on a priority separate from bulk report generation. |
| `BR-COM-02-003` | Conditions evaluate against a whitelisted field set per entity, generated from that entity's registered attributes — a rule author cannot reference an arbitrary column, only fields the owning module has exposed for automation. |
| `BR-COM-02-004` | Condition groups combine by AND within a group and OR across groups, matching standard visual rule-builder semantics. |
| `BR-COM-02-005` ⭐ | Throttling here is **on top of**, not instead of, `CORE-09`'s own deduplication. A scan-triggered rule's `throttle_key` and `throttle_window_hours` stop the same invoice being flagged every day the scan runs; `CORE-09`'s dedup stops the same rendered message being sent twice regardless of source. |
| `BR-COM-02-006` | Every dispatch attempt — matched and sent, matched and throttled, or considered and not matched — writes to `rule_executions`. A rule's effectiveness is measurable, not assumed. |
| `BR-COM-02-007` | A rule referencing a `notification_key` requiring a channel or template that is not currently available (unapproved WhatsApp template, disabled module) does not silently drop — it dispatches through `CORE-09`'s normal fallback and failure path, and the skip is logged. |
| `BR-COM-02-008` ⭐ | Activating a rule requires reviewing an estimated monthly cost, computed from the expected match volume and the channel's rate card. A rule is never switched on blind. |
| `BR-COM-02-009` | A/B variants split by configured weight, deterministically per subject (the same invoice always gets the same variant across repeated scans, so a parent is not shown alternating message styles). |
| `BR-COM-02-010` | Variant performance (sent, opened, responded) is reported per variant so an administrator can retire the losing one. |
| `BR-COM-02-011` | Deactivating a rule stops future dispatch immediately; it does not retract or recall messages already sent. |
| `BR-COM-02-012` | A rule builder screen offers a **preview mode**: run the conditions against current data without dispatching anything, showing exactly who would receive what. |

### 5. Screens · API · Settings

| Screen | Component | Permission |
|---|---|---|
| **Rule library** | `Comms\Automation\Index` | `automation.view` — by trigger type, active status, monthly cost |
| **Rule builder** | `Comms\Automation\Builder` | `automation.manage` ⭐ — visual condition groups, field picker scoped to the entity, live preview, cost estimate before save |
| Execution log | `Comms\Automation\ExecutionLog` | `automation.view` — per rule, matched/sent/throttled breakdown |
| Scan history | `Comms\Automation\ScanRuns` | `automation.view` |
| A/B performance | `Comms\Automation\Variants` | `automation.view` |

```
GET  /api/v1/automation/rules/{ulid}/preview   dry run, no dispatch
POST /api/v1/automation/rules/{ulid}/toggle
```

| Setting | Type | Default |
|---|---|---|
| `automation.scan_priority_queue` | string | `automation-scan` |
| `automation.require_cost_estimate_review` | bool | `true` (**locked**) |
| `automation.default_throttle_window_hours` | int | `168` |

Events: `RuleActivated` · `RuleDeactivated` · `ScanCompleted` · `RuleMatchedZeroRecords` (three consecutive runs — likely a broken condition) ⚠

### 6. Acceptance criteria

```gherkin
AC-COM-02-001
  Given an invoice reaches 30 days overdue with no active payment plan
  When the nightly scan runs
  Then exactly one dispatch is made through CORE-09 for that invoice
  And running the scan again the same day does not re-send it

AC-COM-02-002
  Given the same invoice is still overdue the following week
  When the scan runs again
  Then it dispatches again, because the throttle window has elapsed

AC-COM-02-003
  Given a rule references a field not exposed by its scan entity
  When it is saved
  Then it is rejected naming the invalid field

AC-COM-02-004
  Given a rule builder is used in preview mode
  Then no notification is dispatched
  And the exact matched population is shown

AC-COM-02-005
  Given a rule's estimated monthly cost has not been reviewed
  Then it cannot be activated

AC-COM-02-006
  Given an A/B rule with two template variants
  Then the same invoice always receives the same variant across repeated scans

AC-COM-02-007
  Given a rule fires three consecutive scheduled scans with zero matches
  Then an alert suggests the condition may be misconfigured
```

---

# COM-03 / COM-04 / COM-05 · Portal Services (Parent, Learner, Staff)

> Per §0.2: this is the app shell and the composition layer, not a re-catalogue of the roughly 140 granular endpoints already specified across the other 18 books. Three personas, one shared architecture, documented once with the deltas called out per persona.

### 1. Scope

**In scope.** Dashboard aggregation endpoints, offline sync strategy for Flutter, first-login onboarding, push device registration lifecycle, dashboard widget configuration, deep linking, app session security, document download centre.

**Out of scope.** Any endpoint that returns data a specific module already owns — those live in that module's book and are consumed here, not redefined.

### 2. Data model

```sql
portal_devices                        -- push registration, session tracking
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
user_id                 BIGINT       FK INDEX
device_id               VARCHAR(120) NOT NULL   -- stable client identifier
platform                VARCHAR(20)  NOT NULL   -- ios|android|web
push_token              VARCHAR(255) NULL       -- FCM/APNs token
push_token_updated_at   TIMESTAMP    NULL
app_version             VARCHAR(20)  NULL
os_version              VARCHAR(30)  NULL
last_active_at          TIMESTAMP    NULL
requires_biometric_lock TINYINT(1)   NOT NULL DEFAULT 0
app_pin_hash            VARCHAR(255) NULL       -- separate from account password
is_active               TINYINT(1)   NOT NULL DEFAULT 1
revoked_at              TIMESTAMP    NULL
  UNIQUE (user_id, device_id)
  INDEX  (push_token)

dashboard_widgets                     -- registry, seeded by each owning module
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
key                     VARCHAR(60)  NOT NULL UNIQUE  -- 'fee_balance','next_exeat'
module_code             VARCHAR(20)  NOT NULL
persona                 VARCHAR(20)  NOT NULL   -- parent|learner|staff
title                   VARCHAR(120) NOT NULL
data_endpoint           VARCHAR(200) NOT NULL   -- the owning module's endpoint
min_grade_ordinal       SMALLINT     NULL       -- age-gating for learner persona
requires_module         VARCHAR(20)  NULL       -- hidden if module disabled
default_enabled         TINYINT(1)   NOT NULL DEFAULT 1
default_sort_order      SMALLINT     NOT NULL DEFAULT 0

school_widget_settings                -- per-school customisation
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
widget_key              VARCHAR(60)  FK
persona                 VARCHAR(20)  NOT NULL
is_enabled              TINYINT(1)   NOT NULL DEFAULT 1
sort_order              SMALLINT
  UNIQUE (school_id, widget_key, persona)

onboarding_progress
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
user_id                 BIGINT       FK UNIQUE
persona                 VARCHAR(20)  NOT NULL
steps_completed         JSON         NOT NULL   -- ['welcome','verify_children',
                                                --  'notification_prefs','tour']
completed_at            TIMESTAMP    NULL
skipped_at              TIMESTAMP    NULL

offline_sync_manifests                -- what a device should cache, and when
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
user_id                 BIGINT       FK INDEX
data_category           VARCHAR(40)  NOT NULL   -- timetable|assignments|
                                                -- results|attendance_registers|
                                                -- lms_content
last_synced_at          TIMESTAMP    NULL
sync_token              VARCHAR(120) NULL       -- opaque cursor for incremental sync
cache_ttl_hours         INT          NOT NULL DEFAULT 24
  UNIQUE (user_id, data_category)
```

### 3. ⭐ Dashboard aggregation — one call, not eleven

```php
// GET /api/v1/portal/parent/dashboard
final class BuildParentDashboardAction extends Action
{
    public function execute(User $guardian): ParentDashboard
    {
        $children = $this->guardianQuery->linkedLearners($guardian);   // PPL-03
        $widgets  = $this->widgetRegistry->enabledFor('parent', $guardian->school);

        // Each widget resolver is a thin call into the OWNING module —
        // this action does not compute fee balances, results, or exeat
        // status itself. It parallel-fetches and assembles.
        $panels = $widgets->map(fn ($w) => $this->resolvers
            ->for($w->key)                     // e.g. FeeBalanceWidgetResolver
            ->resolve($guardian, $children));   // calls FIN-03 internally

        return new ParentDashboard(
            children: $children,
            widgets: $panels->filter(fn ($p) => $p->hasData()),
            unreadNotifications: $this->notifications->unreadCount($guardian), // CORE-09
        );
    }
}
```

**Payload discipline.** The response returns each widget's **summary** — a balance figure, a count, a next date — never the full underlying dataset. Tapping a widget navigates to that module's own detail screen, which makes its own focused call. This is the single largest lever on data usage for a parent checking their phone on a bundle in Harare.

### 4. Offline sync — what actually gets cached

| Category | Owner module | What syncs | Cadence |
|---|---|---|---|
| Timetable | `ACA-03` | Current week, next week | Daily + on publish |
| Attendance registers to mark | `ACA-04` | 7 days ahead, teacher's own classes | Daily |
| Assignments & LMS content | `ACA-08` | Current term, enrolled subjects | On assignment publish |
| Exam papers | `ACA-07` | **Never cached ahead of release** — time-locked per `BR-ACA-07-001` | N/A |
| Boarding roll calls | `BRD-02` | Today's points, current allocation | Real-time when online, 24h cache offline |
| Fee balance | `FIN-03` | Summary figure only | On dashboard load; not background-synced |
| Results | `ACA-05` | Published only | On publish |

**The exam paper row is not an oversight — it is a hard constraint carried forward from `ACA-07`.** No offline sync mechanism may pre-fetch a time-locked resource before its release moment, regardless of how convenient that would be for a teacher's commute.

### 5. Persona-specific deltas

**Parent (`COM-03`).** Multi-child switcher on every screen. Widgets: fee balance and quick-pay, upcoming events, recent results, exeat status, transport tracking, wallet balance, unread messages. Diaspora guardians (`PPL-03.is_diaspora`) default to WhatsApp over SMS and see amounts in both the invoice currency and a reference conversion.

**Learner (`COM-04`).** Every widget is filtered through `dashboard_widgets.min_grade_ordinal` — the age-gating already established in `PPL-01` and `CORE-05`. Below the configured grade, the learner has no portal account at all (`BR-CORE-05-022`). Above it, widgets scale up gradually: a Grade 4 learner sees timetable and homework only; a Form 5 learner sees results, LMS, library, and the SBP portfolio tracker. The `BRD-08` "tell someone" entry point (`AC-BRD-08-011`) is present on every learner dashboard regardless of age band and cannot be hidden by widget configuration.

**Staff (`COM-05`).** Widgets: today's timetable with live substitution status, classes needing marks entered, pending approvals (routed generically from `CORE-07`), leave balance, payslip access, duty roster. A Head of Department's dashboard additionally surfaces department-scoped exception reports (unmarked registers, overdue mark entry) pulled from the owning modules' own reporting endpoints.

### 6. Business rules

| ID | Rule |
|---|---|
| `BR-COM-03-001` ⭐ | A dashboard widget's resolver calls its owning module's domain layer. It never queries that module's tables directly. This is the same boundary discipline as every other cross-module interface in this specification. |
| `BR-COM-03-002` | Widget summaries never carry more data than the tile needs to render. Full detail is one tap away, one focused call away. |
| `BR-COM-03-003` | A widget hidden because its module is disabled (`CORE-04` entitlement) is invisible, not shown empty. |
| `BR-COM-03-004` | Widget order and enablement are configurable per school and per persona, and a school can turn off a widget entirely — a day school with no boarding module never sees an exeat tile to configure in the first place, since it is filtered by `requires_module` before it ever reaches the settings screen. |
| `BR-COM-03-005` | Push tokens refresh on every app foreground where the OS reports a change, and a token that fails delivery repeatedly is deactivated and the device flagged for re-registration. |
| `BR-COM-03-006` | A device's app-level PIN or biometric lock, where enabled, is independent of the account's session token — losing the phone does not expose the account if the app itself is locked, even with the underlying token still technically valid. |
| `BR-COM-03-007` | Revoking a device (`CORE-05`'s device management) immediately invalidates its push token and clears any offline cache instruction on next contact. |
| `BR-COM-03-008` | First-login onboarding for a guardian confirms their linked learner list before showing the dashboard, catching a `PPL-03` linkage error at the moment it is cheapest to fix. |
| `BR-COM-03-009` | Onboarding may be skipped but not silently lost — a skipped step remains available from settings. |
| `BR-COM-03-010` | Offline sync respects every access-control and time-lock rule the owning module already enforces. Sync is a caching strategy, not a second authorisation layer, and never grants access sync-side that the API would refuse live. |
| `BR-COM-03-011` | Deep links resolve to a specific screen and, where the linked record requires permission the current user lacks, degrade to the nearest permitted parent screen rather than erroring blankly. |

### 7. Screens · API

| Screen | Component | Notes |
|---|---|---|
| Parent dashboard | `Portal\Parent\Dashboard` | Widget grid, child switcher |
| Learner dashboard | `Portal\Learner\Dashboard` | Age-gated widget set |
| Staff dashboard | `Portal\Staff\Dashboard` | Role-scoped widget set |
| Onboarding | `Portal\Onboarding\Wizard` | Per persona, skippable |
| Widget settings | `Portal\Admin\Widgets` | `portal.widget.manage` — school-level enable/reorder |
| Device management | reuses `Core\Profile\Devices` | `CORE-05`, not duplicated here |

```
GET  /api/v1/portal/parent/dashboard
GET  /api/v1/portal/learner/dashboard
GET  /api/v1/portal/staff/dashboard
GET  /api/v1/portal/widgets                    available for the caller's persona
POST /api/v1/portal/devices                    register device + push token
PATCH /api/v1/portal/devices/{ulid}/pin        set/change app-level PIN
GET  /api/v1/portal/documents                  aggregated document centre across
                                                CORE-06-generated docs the caller may see
GET  /api/v1/portal/onboarding/status
POST /api/v1/portal/onboarding/{step}/complete
```

### 8. Permissions · Settings · Events

```
portal.widget.view              portal.widget.manage
portal.dashboard.view.parent    portal.dashboard.view.learner
portal.dashboard.view.staff
```

| Setting | Type | Default |
|---|---|---|
| `portal.app_pin_required` | bool | `false` |
| `portal.app_pin_min_length` | int | `4` |
| `portal.offline_cache_max_days` | int | `7` |
| `portal.dashboard_widget_max_per_persona` | int | `12` |

Events: `DeviceRegistered` · `DeviceRevoked` · `OnboardingCompleted` · `WidgetConfigurationChanged` · `DeepLinkResolved`

### 9. Acceptance criteria

```gherkin
AC-COM-03-001
  Given a parent's dashboard loads
  Then it completes in a single request
  And no widget's summary exceeds what that tile displays

AC-COM-03-002
  Given a school has no boarding module enabled
  Then no boarding widget appears in that school's widget configuration screen at all

AC-COM-03-003
  Given a Grade 3 learner (below the portal minimum grade)
  Then no learner portal account exists for them

AC-COM-03-004
  Given a Form 5 learner's dashboard
  Then the safeguarding "tell someone" entry point is present
  And no widget configuration can remove it

AC-COM-03-005
  Given an exam paper is not yet at its release time
  Then no offline sync mechanism has pre-fetched it to any device

AC-COM-03-006
  Given a device is revoked from account settings
  Then its push token is invalidated immediately
  And its offline cache clears on next contact

AC-COM-03-007
  Given app-level PIN lock is enabled and the phone is unlocked by someone else
  Then the account remains inaccessible until the correct PIN is entered
  Even though the underlying session token is still valid

AC-COM-03-008
  Given a guardian completes first login
  Then their linked learner list is shown for confirmation before the
       main dashboard appears
```

---

# COM-06 · Calendar, Events & Notice Board

### 1. Scope

Unified calendar aggregating dates from every module that owns one, notice board, newsletters, event RSVP and ticketing with payment linkage, iCal subscription feed.

### 2. Data model

```sql
calendar_sources                      -- what feeds the aggregate, registered by owners
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
module_code             VARCHAR(20)  NOT NULL   -- 'CORE-03','ACA-07','OPS-07','BRD-03'
source_type             VARCHAR(40)  NOT NULL   -- term_dates|holiday|examination|
                                                -- fixture|visiting_day|
                                                -- meeting|deadline
default_colour          CHAR(7)      NULL
default_audience_scope  VARCHAR(20)  NOT NULL   -- whole_school|section|
                                                -- level|class|house|staff
is_active               TINYINT(1)   NOT NULL DEFAULT 1

calendar_events                       -- materialised aggregate, rebuilt from sources
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
academic_year_id        BIGINT       FK
term_id                 BIGINT       NULL FK
source_type             VARCHAR(40)  NOT NULL
source_module_id        BIGINT       NULL       -- FK back to the owning record
title                   VARCHAR(200) NOT NULL
description             TEXT         NULL
starts_at               TIMESTAMP    NOT NULL
ends_at                 TIMESTAMP    NULL
is_all_day              TINYINT(1)   NOT NULL DEFAULT 0
location                VARCHAR(200) NULL
audience_scope          VARCHAR(20)  NOT NULL
audience_scope_id       BIGINT       NULL       -- section/level/class/house id
colour                  CHAR(7)      NULL
is_public               TINYINT(1)   NOT NULL DEFAULT 1   -- appears on public site
rebuilt_at              TIMESTAMP    NOT NULL
  UNIQUE (school_id, source_type, source_module_id)
  INDEX  (school_id, starts_at, audience_scope)

notices
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
title                   VARCHAR(200) NOT NULL
body                    TEXT         NOT NULL
priority                VARCHAR(20)  NOT NULL   -- normal|important|urgent
audience_scope          VARCHAR(20)  NOT NULL
audience_scope_id       BIGINT       NULL
is_pinned               TINYINT(1)   NOT NULL DEFAULT 0
publish_at              TIMESTAMP    NOT NULL
expires_at              TIMESTAMP    NULL
attachment_file_ids     JSON         NULL
posted_by               BIGINT       FK → users.id
status                  VARCHAR(20)  NOT NULL   -- draft|scheduled|published|expired
  INDEX (school_id, status, publish_at)

notice_reads                          -- read receipts, for important/urgent only
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK
notice_id               BIGINT       FK INDEX
user_id                 BIGINT       FK INDEX
read_at                 TIMESTAMP    NOT NULL
  UNIQUE (notice_id, user_id)

newsletters
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
issue_number             VARCHAR(20)  NOT NULL
title                    VARCHAR(200) NOT NULL
content_html             LONGTEXT     NOT NULL
audience_scope           VARCHAR(20)  NOT NULL
scheduled_for             TIMESTAMP    NULL
sent_at                   TIMESTAMP    NULL
archive_file_id           BIGINT       NULL FK
status                    VARCHAR(20)  NOT NULL   -- draft|scheduled|sent
  UNIQUE (school_id, issue_number)

event_registrations                   -- RSVP / ticketing
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
calendar_event_id        BIGINT       FK INDEX
capacity                 SMALLINT     NULL
requires_ticket           TINYINT(1)   NOT NULL DEFAULT 0
ticket_price_minor        BIGINT       NULL
ticket_currency            CHAR(3)      NULL
fee_component_id           BIGINT       NULL FK   -- FIN-02 linkage for paid events
rsvp_deadline               TIMESTAMP    NULL
registered_count             SMALLINT     NOT NULL DEFAULT 0

event_attendees
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK
registration_id          BIGINT       FK INDEX
attendee_type             VARCHAR(20)  NOT NULL   -- guardian|staff|student|external
attendee_name              VARCHAR(200) NOT NULL
guardian_id                BIGINT       NULL FK
party_size                  TINYINT      NOT NULL DEFAULT 1
ticket_receipt_id            BIGINT       NULL FK   -- FIN-04
checked_in_at                 TIMESTAMP    NULL
status                        VARCHAR(20)  NOT NULL   -- registered|paid|
                                                      -- checked_in|cancelled|
                                                      -- no_show
```

### 3. Business rules

| ID | Rule |
|---|---|
| `BR-COM-06-001` | Every owning module registers itself as a `calendar_sources` row and pushes events into `calendar_events` on create, update, and cancel. The calendar never queries another module's tables directly. |
| `BR-COM-06-002` | `calendar_events` is a rebuildable cache. A nightly job reconciles it against every registered source and reports drift. |
| `BR-COM-06-003` | Audience scoping filters what a given viewer sees — a Form 2 parent does not see A-Level examination dates cluttering their calendar. |
| `BR-COM-06-004` | Urgent and important notices generate read receipts and an unread-count badge; normal notices do not, to keep the tracking overhead proportionate. |
| `BR-COM-06-005` | An urgent notice past its publish time and still substantially unread by its audience escalates a reminder through `CORE-09`, once, not repeatedly. |
| `BR-COM-06-006` | Ticketed events raise a fee through `FIN-02`/`FIN-04` at registration, exactly like any other ad hoc charge, and check-in is gated on payment where `requires_ticket = 1`. |
| `BR-COM-06-007` | Registration beyond capacity is blocked with a waitlist offer, mirroring the pattern in `BRD-01`'s hostel waiting list. |
| `BR-COM-06-008` | The public iCal feed includes only events flagged `is_public = 1` and respects audience scope for the token it was issued against. |
| `BR-COM-06-009` | A cancelled source record (an examination date moved, a fixture cancelled) removes or updates the corresponding calendar event and notifies anyone registered. |

### 4. Screens · API

| Screen | Component | Permission |
|---|---|---|
| Calendar | `Comms\Calendar\View` | `calendar.view` — month/week/agenda, audience-filtered |
| Notice board | `Comms\Notices\Index` | `notices.view` |
| Post notice | `Comms\Notices\Compose` | `notices.post` |
| Newsletter editor | `Comms\Newsletters\Compose` | `newsletters.manage` |
| Event registration | `Comms\Events\Register` | `events.manage` — capacity, ticketing, check-in |
| Check-in | `Comms\Events\CheckIn` | `events.checkin` — mobile, scan ticket |

```
GET  /api/v1/calendar                    ?from=&to=   audience-filtered
GET  /api/v1/calendar.ics                 iCal subscription, token-scoped
GET  /api/v1/notices                      unread-first
POST /api/v1/notices/{ulid}/read
GET  /api/v1/events/{ulid}
POST /api/v1/events/{ulid}/register
```

### 5. Acceptance criteria

```gherkin
AC-COM-06-001
  Given an examination date is rescheduled in ACA-07
  Then the calendar event updates within the next rebuild
  And anyone registered for a dependent event is notified

AC-COM-06-002
  Given a Form 2 parent views the calendar
  Then A-Level-only examination dates do not appear

AC-COM-06-003
  Given a ticketed event requires payment
  Then check-in is refused until the ticket is paid

AC-COM-06-004
  Given an event is fully booked
  Then further registration offers a waitlist instead of a silent failure

AC-COM-06-005
  Given the public iCal feed token is scoped to a parent's audience
  Then staff-only events are absent from the feed it returns
```

---

# COM-07 · Virtual Meetings & Online Classes

### 1. Scope

Meeting provider integration (Zoom Server-to-Server OAuth, Google Meet), scheduled online lessons from the timetable, parent-teacher consultation booking, encrypted credential distribution, attendance capture from provider webhooks feeding `ACA-04`.

### 2. Data model

```sql
meeting_providers
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
provider                VARCHAR(20)  NOT NULL   -- zoom|google_meet|teams
credentials              TEXT         NOT NULL   -- ENCRYPTED, S2S OAuth
account_email             VARCHAR(150) NULL
webhook_secret            VARCHAR(200) NULL      -- ENCRYPTED
is_active                 TINYINT(1)   NOT NULL DEFAULT 0
  UNIQUE (school_id, provider)

scheduled_meetings
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
term_id                  BIGINT       FK
meeting_type              VARCHAR(20)  NOT NULL   -- online_lesson|
                                                  -- consultation|staff_meeting|
                                                  -- board_meeting|webinar
provider_id                BIGINT       FK
timetable_slot_id            BIGINT       NULL FK   -- ACA-03, if an online lesson
provider_meeting_id            VARCHAR(80)  NULL
join_url                        VARCHAR(500) NULL      -- ⭐ never exposed publicly
host_url                         VARCHAR(500) NULL      -- ENCRYPTED
passcode                          VARCHAR(30)  NULL      -- ENCRYPTED
starts_at                         TIMESTAMP    NOT NULL
duration_minutes                   SMALLINT     NOT NULL
host_staff_id                       BIGINT       NULL FK
waiting_room_enabled                 TINYINT(1)   NOT NULL DEFAULT 1
recording_enabled                     TINYINT(1)   NOT NULL DEFAULT 0
recording_url                          VARCHAR(500) NULL
recording_expires_on                    DATE         NULL
status                                  VARCHAR(20)  NOT NULL   -- scheduled|
                                                                -- in_progress|
                                                                -- completed|cancelled
  INDEX (school_id, starts_at, status)
  INDEX (school_id, timetable_slot_id)

consultation_windows                  -- teacher availability
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
term_id                  BIGINT       FK
staff_id                  BIGINT       FK INDEX
event_name                 VARCHAR(150) NOT NULL   -- 'Term 2 Parents Evening'
slot_duration_minutes        SMALLINT     NOT NULL DEFAULT 10
available_from                 TIMESTAMP    NOT NULL
available_to                    TIMESTAMP    NOT NULL
booking_opens_at                 TIMESTAMP    NULL
booking_closes_at                 TIMESTAMP    NULL

consultation_bookings
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK
window_id                BIGINT       FK INDEX
guardian_id                BIGINT       FK
student_id                  BIGINT       FK
slot_starts_at                TIMESTAMP    NOT NULL
meeting_id                     BIGINT       NULL FK
status                          VARCHAR(20)  NOT NULL   -- booked|attended|
                                                        -- no_show|cancelled
  UNIQUE (window_id, slot_starts_at)

meeting_webhook_events                -- APPEND-ONLY, same pattern as FIN-05/COM-01
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       NULL FK
provider                 VARCHAR(20)  NOT NULL
event_type                 VARCHAR(60)  NULL       -- meeting.started|
                                                   -- meeting.ended|
                                                   -- participant.joined|
                                                   -- participant.left
raw_payload                 LONGTEXT     NOT NULL
payload_hash                 CHAR(64)     NOT NULL
signature_valid               TINYINT(1)   NOT NULL
meeting_id                     BIGINT       NULL FK
processing_status               VARCHAR(20)  NOT NULL
received_at                      TIMESTAMP(6) NOT NULL
  UNIQUE (provider, payload_hash)

meeting_attendance                    -- ⭐ raw join/leave, before scoring
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK
meeting_id                BIGINT       FK INDEX
participant_identifier      VARCHAR(150) NOT NULL   -- email or provider user id
student_id                    BIGINT       NULL FK    -- resolved, if matched
joined_at                       TIMESTAMP    NOT NULL
left_at                          TIMESTAMP    NULL
duration_seconds                  INT          NULL
match_confidence                   VARCHAR(20)  NULL   -- exact|fuzzy|unmatched
  INDEX (school_id, meeting_id, student_id)
```

### 3. ⭐ Attendance capture — closing the interface into `ACA-04`

```
Zoom webhook: participant.joined / participant.left
        │
        ▼
meeting_attendance row created (raw, unmatched)
        │
        ▼
Match participant_identifier → student
   exact:  email matches a registered guardian/learner contact
   fuzzy:  display name matches enrolled learner, flagged for review
   none:   left unmatched, surfaced to the teacher for manual reconciliation
        │
        ▼
IF the meeting is linked to a timetable_slot_id (an online lesson):
   an ACA-04 attendance_session already exists for that slot (per ACA-03 §5)
   → duration_seconds compared against the lesson length
   → present if duration ≥ attendance.online_minimum_attendance_percent of the lesson
   → attendance_records written with device_source = 'video_conference'
```

**This never bypasses the teacher.** Automatic matching pre-populates the register exactly as `BRD-02`'s pre-population does for exeats; the teacher reviews and confirms before the register locks, and unmatched participants are surfaced rather than silently ignored or silently marked present.

### 4. Business rules

| ID | Rule |
|---|---|
| `BR-COM-07-001` ⭐ | `join_url` may be exposed to the invited audience; `host_url` and `passcode` are encrypted and exposed only to the meeting's host and co-hosts, never in a bulk notice or public calendar entry. |
| `BR-COM-07-002` | An online lesson meeting is created automatically when a timetable slot is flagged for virtual delivery, linked bidirectionally to the slot. |
| `BR-COM-07-003` | Waiting rooms are enabled by default for any meeting involving learners. Disabling one for a learner-facing meeting requires an explicit override, logged. |
| `BR-COM-07-004` | Recordings, where enabled, expire and are purged per the configured retention, consistent with `CMP-03`. |
| `BR-COM-07-005` | Consultation slots are booked on a strict first-come basis with no double-booking; a slot taken while a parent is completing the booking flow is refused with the next available offered. |
| `BR-COM-07-006` | Webhook signature verification, replay protection, and idempotent processing follow the same pattern as `FIN-05` and `COM-01`. |
| `BR-COM-07-007` ⭐ | Attendance matching is advisory. The teacher confirms the final register; nothing from a video conference webhook writes a locked attendance record without going through the normal `ACA-04` marking and lock flow. |
| `BR-COM-07-008` | A participant present for less than `attendance.online_minimum_attendance_percent` of the lesson duration is marked present-but-flagged, not simply present, so a teacher can distinguish a full attendee from someone who joined for two minutes. |
| `BR-COM-07-009` | Cancelling a scheduled meeting notifies every registered or booked participant and, for consultations, releases the slot back to the booking pool. |

### 5. Screens · API · Settings

| Screen | Component | Permission |
|---|---|---|
| Provider setup | `Comms\Meetings\Providers` | `meetings.manage` ⚠⚠ |
| Meeting schedule | `Comms\Meetings\Index` | `meetings.view` |
| Consultation windows | `Comms\Consultations\Windows` | `meetings.consultation.manage` — teacher sets availability |
| **Attendance reconciliation** | `Comms\Meetings\AttendanceReview` | `academic.attendance.mark` — unmatched participants, confirm register |
| Recordings | `Comms\Meetings\Recordings` | `meetings.recording.view` |

```
GET  /api/v1/meetings/my-schedule           staff and learner
GET  /api/v1/meetings/{ulid}/join           resolves join_url only, never host credentials
GET  /api/v1/consultations/available        guardian
POST /api/v1/consultations/{window}/book
```

| Setting | Type | Default |
|---|---|---|
| `meetings.waiting_room_default_for_learners` | bool | `true` (**locked**) |
| `attendance.online_minimum_attendance_percent` | int | `70` |
| `meetings.recording_retention_days` | int | `90` |

Events: `MeetingScheduled` · `MeetingStarted` · `RecordingAvailable` · `ConsultationBooked` · `AttendanceReconciliationNeeded` · `UnauthorisedWaitingRoomOverride` ⚠

### 6. Acceptance criteria

```gherkin
AC-COM-07-001
  Given a public calendar entry for an online lesson
  Then it shows the join link
  And never shows the host URL or passcode

AC-COM-07-002
  Given a Zoom webhook reports a participant joined and left
  Then meeting_attendance records raw duration
  And matching to a learner is attempted but does not write a locked
      ACA-04 record without teacher confirmation

AC-COM-07-003
  Given a participant attended for 40% of a 60-minute online lesson
  And the minimum threshold is 70%
  Then they are marked present-but-flagged, not simply present

AC-COM-07-004
  Given two parents attempt to book the same consultation slot simultaneously
  Then exactly one succeeds
  And the other is offered the next available slot

AC-COM-07-005
  Given a learner-facing meeting attempts to disable its waiting room
  Then the override is logged and requires explicit permission
```

---

# COM-08 · Feedback, Surveys & Complaints

### 1. Scope

Survey builder with branching logic, distribution and response analytics, complaint intake with SLA and escalation, exit interviews.

### 2. Data model

```sql
surveys
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
title                    VARCHAR(200) NOT NULL
purpose                   VARCHAR(40)  NOT NULL   -- satisfaction|feedback|
                                                  -- research|exit_interview
audience_scope             VARCHAR(20)  NOT NULL
is_anonymous                 TINYINT(1)   NOT NULL DEFAULT 0
opens_at                       TIMESTAMP    NULL
closes_at                       TIMESTAMP    NULL
status                          VARCHAR(20)  NOT NULL   -- draft|open|closed
response_count                    INT          NOT NULL DEFAULT 0
  INDEX (school_id, status)

survey_questions
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
survey_id                BIGINT       FK INDEX
sequence                   SMALLINT     NOT NULL
question_type                VARCHAR(20)  NOT NULL   -- single_choice|
                                                     -- multi_choice|scale|
                                                     -- text|nps
prompt                        VARCHAR(500) NOT NULL
options                         JSON         NULL
is_required                       TINYINT(1)   NOT NULL DEFAULT 1
skip_logic                         JSON         NULL       -- {if_answer, go_to_sequence}

survey_responses
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK
survey_id                 BIGINT       FK INDEX
respondent_type              VARCHAR(20)  NULL       -- null when anonymous ⭐
respondent_id                  BIGINT       NULL
submitted_at                     TIMESTAMP    NOT NULL
  INDEX (school_id, survey_id)

survey_response_answers
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
response_id               BIGINT       FK INDEX
question_id                  BIGINT       FK
answer_value                    JSON         NOT NULL

complaint_categories
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
code                      VARCHAR(30)  NOT NULL
name                        VARCHAR(120) NOT NULL
default_assignee_role_id      BIGINT       NULL FK
sla_hours                       INT          NOT NULL DEFAULT 72
  UNIQUE (school_id, code)

complaints
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
complaint_number          VARCHAR(40)  NOT NULL
category_id                 BIGINT       FK
raised_by_type                VARCHAR(20)  NOT NULL   -- guardian|staff|
                                                      -- learner|anonymous
raised_by_id                    BIGINT       NULL
subject                           VARCHAR(200) NOT NULL
description                         TEXT         NOT NULL
related_student_id                    BIGINT       NULL FK
severity                                VARCHAR(20)  NOT NULL   -- low|medium|high
assigned_to_staff_id                      BIGINT       NULL FK
sla_due_at                                  TIMESTAMP    NOT NULL
status                                        VARCHAR(20)  NOT NULL   -- received|
                                                                      -- acknowledged|
                                                                      -- investigating|
                                                                      -- resolved|
                                                                      -- escalated|closed
resolution                                    TEXT         NULL
satisfaction_rating                             TINYINT      NULL
closed_at                                        TIMESTAMP    NULL
  UNIQUE (school_id, complaint_number)
  INDEX  (school_id, status, sla_due_at)

complaint_updates                     -- APPEND-ONLY
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK
complaint_id               BIGINT       FK INDEX
update_type                   VARCHAR(20)  NOT NULL   -- comment|status_change|
                                                      -- reassignment|escalation
content                          TEXT         NULL
visible_to_raiser                  TINYINT(1)   NOT NULL DEFAULT 1
posted_by                            BIGINT       FK → users.id
posted_at                              TIMESTAMP    NOT NULL

exit_interviews
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
student_id                BIGINT       FK          -- PPL-01 withdrawal linkage
guardian_id                 BIGINT       NULL FK
requested_at                  TIMESTAMP    NOT NULL
completed_at                    TIMESTAMP    NULL
primary_reason                    VARCHAR(60)  NULL    -- relocation|fees|
                                                        -- academic_fit|
                                                        -- boarding_experience|
                                                        -- other_school|
                                                        -- dissatisfaction
detail                                TEXT         NULL
would_recommend                         TINYINT(1)   NULL
response_source                           VARCHAR(20)  NULL   -- survey|call|
                                                                -- declined
```

### 3. Business rules

| ID | Rule |
|---|---|
| `BR-COM-08-001` | Anonymous surveys store no respondent identity, following the same absence-not-redaction principle established in `BRD-08`'s anonymous reporting. |
| `BR-COM-08-002` | Skip logic evaluates the prior answer before rendering the next question; a respondent never sees a question their prior answer made irrelevant. |
| `BR-COM-08-003` | A complaint's SLA due date is computed from its category at intake and is visible to both the raiser and the assignee. |
| `BR-COM-08-004` | A complaint approaching its SLA alerts the assignee; one breached alerts their manager. |
| `BR-COM-08-005` | Complaint updates are append-only. Internal notes (`visible_to_raiser = 0`) are never shown to the raiser, and the boundary is enforced at the API layer, not just the UI. |
| `BR-COM-08-006` | A complaint involving a safeguarding concern is routed to `BRD-08` per the same trigger-category mechanism as `BRD-07` (`BR-BRD-07-018`), not handled as an ordinary complaint. |
| `BR-COM-08-007` | Exit interviews are offered, never mandatory, and a decline is itself recorded as data (`response_source = declined`), since the school's overall decline rate is informative. |
| `BR-COM-08-008` | Satisfaction ratings and complaint resolution times are reported in aggregate to the head each term. |

### 4. Screens · API

| Screen | Component | Permission |
|---|---|---|
| Survey builder | `Comms\Surveys\Builder` | `surveys.manage` |
| Survey results | `Comms\Surveys\Results` | `surveys.view` |
| Complaint intake | `Comms\Complaints\Submit` | any authenticated user |
| Complaint queue | `Comms\Complaints\Queue` | `complaints.manage` — SLA countdown, overdue highlighted |
| Complaint detail | `Comms\Complaints\Show` | assignee — internal notes separated from raiser-visible thread |
| Exit interviews | `Comms\ExitInterviews\Index` | `complaints.manage` |

```
POST /api/v1/surveys/{ulid}/respond
POST /api/v1/complaints                     any authenticated user
GET  /api/v1/complaints/mine                raiser's own, status visible
POST /api/v1/complaints/{ulid}/updates      assignee or raiser, per visibility
```

### 5. Acceptance criteria

```gherkin
AC-COM-08-001
  Given an anonymous survey response is submitted
  Then no respondent identity is stored anywhere

AC-COM-08-002
  Given a complaint category carries a 72-hour SLA
  Then the due date is set at intake and visible to the raiser

AC-COM-08-003
  Given an internal note is added to a complaint
  Then the raiser's own view of the thread never includes it

AC-COM-08-004
  Given a complaint's description indicates a safeguarding concern
  Then it routes to BRD-08 rather than the ordinary complaint queue

AC-COM-08-005
  Given a family declines the exit interview
  Then the decline itself is recorded
  And contributes to the school's overall response-rate reporting
```

---

## Part 3 — Book I Build Sequence

| Sprint | Deliverable | Definition of done |
|---|---|---|
| **I1** | `COM-01` gateway registry, credentials, health checks | Failover routes to next healthy gateway |
| **I2** | `COM-01` WhatsApp templates, session window, quality monitoring ⭐ | **`AC-COM-01-001` and `-002` green** |
| **I3** | `COM-01` SMS segmentation, sender IDs, webhook ingestion | Normalisation proven against real diacritic-bearing names |
| **I4** | `COM-01` cost reconciliation | Variance report matches a real provider statement in staging |
| **I5** | `COM-02` rule registry, condition engine, event-triggered rules | Field whitelist enforced per entity |
| **I6** | `COM-02` scan-triggered rules, throttling, cost estimate gate | `AC-COM-02-001/002` green |
| **I7** | `COM-02` A/B variants, execution log, preview mode | Deterministic variant assignment proven |
| **I8** | `COM-03/04/05` device registration, app-shell, PIN lock | `AC-COM-03-007` green |
| **I9** | `COM-03/04/05` dashboard aggregation, widget registry | Single-call load proven under 1s on throttled connection |
| **I10** | `COM-03/04/05` offline sync, onboarding | Exam paper exclusion from sync verified |
| **I11** | `COM-06` calendar aggregation, notices, newsletters | Rebuild reconciles against every source nightly |
| **I12** | `COM-06` event ticketing | Fee linkage to `FIN-02`/`FIN-04` correct |
| **I13** | `COM-07` provider integration, scheduled meetings, consultations | Host credentials never exposed publicly |
| **I14** | `COM-07` attendance capture ⭐ | **`ACA-04` interface closed; teacher confirmation gate proven** |
| **I15** | `COM-08` surveys, complaints, SLA, exit interviews | Safeguarding routing proven |

`COM-06`, `COM-07` and `COM-08` (I11–I15) have no dependency on each other or on `COM-03/04/05` and should run in parallel with additional developers.

---

## Part 4 — Book I Acceptance Gate

### The `CORE-09` boundary

- [ ] No recipient-resolution logic exists in this book outside `CORE-09`
- [ ] No budget-cap or quiet-hours logic exists in this book outside `CORE-09`
- [ ] Every `COM-01` driver implements the exact `NotificationChannelDriver` interface from Book A without modification
- [ ] `COM-02` rules dispatch exclusively through `CORE-09`; no rule posts a message directly

### WhatsApp and SMS correctness

- [ ] Free-form WhatsApp messages outside the 24-hour session window are refused before reaching the provider
- [ ] A notification key requiring an unapproved template falls back automatically
- [ ] Quality-rating degradation pauses non-critical sends and alerts
- [ ] SMS normalisation is applied before segment calculation, verified against real Shona/Ndebele names with diacritics
- [ ] Recorded segment counts match actual provider billing within reconciliation tolerance

### Automation

- [ ] Scan-triggered rules respect their throttle window under repeated daily runs
- [ ] Rule activation is blocked without a reviewed cost estimate
- [ ] Preview mode never dispatches
- [ ] A/B variant assignment is deterministic per subject

### Portal and offline

- [ ] Dashboard aggregation completes in one request per persona
- [ ] Widget payloads never exceed tile-display requirements
- [ ] No offline sync mechanism pre-fetches a time-locked exam paper
- [ ] Device revocation invalidates push tokens and clears cache immediately
- [ ] App-level PIN lock is independent of the underlying session token
- [ ] The safeguarding entry point is present on every learner dashboard and cannot be configured away

### Meetings

- [ ] Host URLs and passcodes are never exposed to non-host participants
- [ ] Attendance matching never writes a locked register without teacher confirmation
- [ ] Consultation double-booking is structurally prevented, not merely discouraged

### Quality

- [ ] Coverage ≥ 85%; `COM-01` webhook handling and `COM-02` throttling ≥ 90%
- [ ] Tenancy isolation suite passes for every model in this book
- [ ] Every business rule has a named test referencing its rule ID

---

## Appendix A — Interfaces Closed

| Interface | Owner | Consumer | Status |
|---|---|---|---|
| `NotificationChannelDriver` | `CORE-09` (Book A) | `COM-01` | ✅ implemented |
| Meeting attendance capture | `COM-07` | `ACA-04` | ✅ closed |
| Event ticketing → fee charge | `COM-06` | `FIN-02`, `FIN-04` | ✅ |
| Complaint safeguarding routing | `COM-08` | `BRD-08` | ✅, mirrors `BRD-07`'s pattern |
| Calendar source registration | every dated module | `COM-06` | ✅ |

---

## Appendix B — Final Book

**Book J — Intelligence & SaaS Control (`INT-01`–`INT-04`, `SAA-01`–`SAA-03`)** is the last book in the specification. It covers the custom report builder and data warehouse, executive dashboards, early warning and predictive analytics, the public API and webhooks — and the commercial layer beneath all of it: licensing and subscription entitlement, the vendor control centre, and onboarding and customer success tooling.

Everything through Book I builds the product a school runs. Book J is what lets you sell it to more than one school and keep it running.

---

*End of Volume 2, Book I.*
