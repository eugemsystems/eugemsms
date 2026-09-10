# sERP — Enterprise School Management Platform
## Volume 2 · Detailed Functional & Technical Specification
### Book H2 — Domain F: Operations & Estates (`OPS-01` → `OPS-07`)

| Field | Value |
|---|---|
| Document | Volume 2, Book H2 of 10 |
| Covers | Transport & Fleet · Maintenance & Works · Estates, Farm & Production · Utilities & Energy · Facilities & Hire · Security & Access · Sport, Houses & Co-curricular |
| Status | Build-ready specification |
| Version | 1.0 |
| Date | September 2026 |
| Prerequisites | **Books A–D, F, H1 complete.** `OPS-03` requires `FIN-09` stores. |
| Next book | Book H3 — Payroll, Fiscalisation & Compliance |

---

## Part 0 — What This Book Closes

Four interfaces left open in earlier books are implemented here.

| Interface | Opened in | Closed by |
|---|---|---|
| Farm-to-kitchen produce transfer | Book F `BRD-04` §0.3 | `OPS-03` §4 |
| Work order parts issue from stores | Book H1 Appendix A | `OPS-02` |
| Units consumed for units-of-production depreciation | Book H1 `FIN-10` | `OPS-01` mileage, `OPS-04` generator hours |
| Damage repair work orders | Book F `BRD-01` | `OPS-02` |
| Roll call status `fixture` | Book F `BRD-02` §4 | `OPS-07` |
| Emergency muster roll | Book F `BRD-02` | `OPS-06` §5 |

### 0.1 🇿🇼 Why operations is different in this market

The generic international school ERP treats operations as a rounding error: a facilities booking screen and a vehicle list. For a Zimbabwean boarding school, operations is where a substantial share of the budget goes and where most of the loss occurs.

| Reality | Consequence in this book |
|---|---|
| **Fuel is the single most-stolen commodity.** | `OPS-01` computes litres per 100km per vehicle and flags deviation. Anomaly detection is a first-class feature, not a report. |
| **Load shedding is daily.** | `OPS-04` models grid, generator and solar as three sources with cost per kWh each, so the head can see what an outage actually costs. |
| **Prepaid electricity is the norm.** | `OPS-04` tracks token purchases against meter consumption. A token bought and not credited is money gone. |
| **Many schools run working farms.** | `OPS-03` treats the farm as a costed production unit whose output transfers to the kitchen at cost, so the question *"does the farm actually save us money?"* has an answer. |
| **Vehicle compliance is multi-agency and expensive.** | `OPS-01` tracks ZINARA licensing, certificate of fitness, route permits, passenger insurance and radio licence separately, each with its own expiry. |
| **Boreholes fail and water is not guaranteed.** | `OPS-04` monitors borehole yield and storage as an operational risk, not a utility line. |

### 0.2 Build order

```
OPS-02  Maintenance & Works       ← other modules raise work orders against it
   ↓
OPS-01  Transport & Fleet         ← feeds FIN-10 mileage
   ↓
OPS-04  Utilities & Energy        ← feeds FIN-10 generator hours
   ↓
OPS-03  Estates & Farm            ← closes the Book F kitchen transfer
   ↓
OPS-05  Facilities & Hire   ─┐
OPS-06  Security & Access    ├─ independent; parallel
OPS-07  Sport & Houses      ─┘
```

---

# OPS-02 · Maintenance & Works Management

> Built first because `BRD-01` damages, `OPS-01` vehicle servicing, `OPS-03` farm equipment and `OPS-04` generator maintenance all raise work orders against it.

### 1. Scope

Fault reporting from any user, job cards, preventive maintenance schedules, contractor management, parts issue from stores, labour and material costing, SLA tracking, capital works projects.

### 2. Data model

```sql
maintenance_assets                    -- maintainable things, wider than FIN-10
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
code                    VARCHAR(30)  NOT NULL
name                    VARCHAR(200) NOT NULL
asset_type              VARCHAR(30)  NOT NULL   -- building|vehicle|generator|
                                                -- borehole|pump|solar|kitchen_equip|
                                                -- ict|furniture|grounds|plumbing|
                                                -- electrical|farm_equipment
fixed_asset_id          BIGINT       NULL FK    -- FIN-10, where capitalised
vehicle_id              BIGINT       NULL FK    -- OPS-01
location                VARCHAR(150) NULL
building                VARCHAR(80)  NULL
cost_centre_id          BIGINT       FK
criticality             VARCHAR(20)  NOT NULL DEFAULT 'normal'  -- critical|high|
                                                                -- normal|low
condition               VARCHAR(20)  NOT NULL DEFAULT 'good'
commissioned_on         DATE         NULL
warranty_expires_on     DATE         NULL
service_interval_days   SMALLINT     NULL
service_interval_units  DECIMAL(12,2) NULL      -- km or hours
last_serviced_on        DATE         NULL
last_service_units      DECIMAL(12,2) NULL
next_service_due_on     DATE         NULL
next_service_due_units  DECIMAL(12,2) NULL
is_active               TINYINT(1)   NOT NULL DEFAULT 1
  UNIQUE (school_id, code)
  INDEX  (school_id, asset_type, is_active)
  INDEX  (school_id, next_service_due_on)

fault_reports                         -- low friction; anyone can raise one
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
term_id                 BIGINT       FK
report_number           VARCHAR(40)  NOT NULL
maintenance_asset_id    BIGINT       NULL FK
location                VARCHAR(150) NOT NULL
category                VARCHAR(40)  NOT NULL   -- plumbing|electrical|carpentry|
                                                -- glazing|roofing|painting|ict|
                                                -- grounds|vehicle|appliance
description             TEXT         NOT NULL
photo_file_ids          JSON         NULL
severity                VARCHAR(20)  NOT NULL   -- emergency|urgent|routine|cosmetic
affects_safety          TINYINT(1)   NOT NULL DEFAULT 0   -- ⭐ jumps the queue
affects_teaching        TINYINT(1)   NOT NULL DEFAULT 0
reported_by             BIGINT       FK → users.id
reported_at             TIMESTAMP    NOT NULL
source_type             VARCHAR(40)  NULL       -- hostel_damage|inspection|
                                                -- routine|user_report
source_id               BIGINT       NULL       -- e.g. BRD-01 damage id
status                  VARCHAR(20)  NOT NULL   -- reported|triaged|
                                                -- work_order_raised|duplicate|
                                                -- rejected|resolved
work_order_id           BIGINT       NULL FK
triaged_by              BIGINT       NULL FK
triage_note             VARCHAR(255) NULL
  UNIQUE (school_id, report_number)
  INDEX  (school_id, status, severity)
  INDEX  (school_id, affects_safety, status)

work_orders
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
academic_year_id        BIGINT       FK
term_id                 BIGINT       FK INDEX
work_order_number       VARCHAR(40)  NOT NULL
maintenance_asset_id    BIGINT       NULL FK
fault_report_id         BIGINT       NULL FK
schedule_id             BIGINT       NULL FK    -- preventive
work_type               VARCHAR(20)  NOT NULL   -- corrective|preventive|
                                                -- improvement|inspection|emergency
title                   VARCHAR(200) NOT NULL
description             TEXT         NOT NULL
location                VARCHAR(150) NULL
priority                VARCHAR(20)  NOT NULL   -- emergency|high|normal|low
assigned_team           VARCHAR(20)  NOT NULL   -- in_house|contractor
assigned_staff_id       BIGINT       NULL FK
contractor_supplier_id  BIGINT       NULL FK    -- FIN-08
cost_centre_id          BIGINT       FK
budget_line_id          BIGINT       NULL FK    -- FIN-11
scheduled_for           DATE         NULL
target_completion       DATE         NULL       -- SLA
started_at              TIMESTAMP    NULL
completed_at            TIMESTAMP    NULL
status                  VARCHAR(20)  NOT NULL   -- draft|approved|assigned|
                                                -- in_progress|awaiting_parts|
                                                -- awaiting_contractor|completed|
                                                -- verified|cancelled
approval_request_id     BIGINT       NULL FK
labour_hours            DECIMAL(6,2) NOT NULL DEFAULT 0
labour_cost_minor       BIGINT       NOT NULL DEFAULT 0
parts_cost_minor        BIGINT       NOT NULL DEFAULT 0
contractor_cost_minor   BIGINT       NOT NULL DEFAULT 0
total_cost_minor        BIGINT       NOT NULL DEFAULT 0
currency                CHAR(3)      NOT NULL
completion_notes        TEXT         NULL
completion_photo_ids    JSON         NULL
verified_by             BIGINT       NULL FK    -- requester confirms
verified_at             TIMESTAMP    NULL
sla_met                 TINYINT(1)   NULL
raised_by               BIGINT       FK → users.id
  UNIQUE (school_id, work_order_number)
  INDEX  (school_id, status, priority)
  INDEX  (school_id, maintenance_asset_id)
  INDEX  (school_id, target_completion, status)

work_order_parts                      -- ⭐ FIN-09 linkage
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK
work_order_id           BIGINT       FK INDEX
item_id                 BIGINT       NULL FK    -- inventory item
description             VARCHAR(255) NOT NULL
quantity                DECIMAL(12,4) NOT NULL
unit                    VARCHAR(20)  NOT NULL
source                  VARCHAR(20)  NOT NULL   -- store|purchase|contractor
store_requisition_id    BIGINT       NULL FK    -- FIN-09
purchase_order_id       BIGINT       NULL FK    -- FIN-08
unit_cost_minor         BIGINT       NULL
line_cost_minor         BIGINT       NULL
issued_at               TIMESTAMP    NULL

work_order_labour
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK
work_order_id           BIGINT       FK INDEX
staff_id                BIGINT       FK
work_date               DATE         NOT NULL
hours                   DECIMAL(5,2) NOT NULL
hourly_rate_minor       BIGINT       NULL
cost_minor              BIGINT       NULL
notes                   VARCHAR(255) NULL

maintenance_schedules                 -- preventive
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
maintenance_asset_id    BIGINT       FK INDEX
name                    VARCHAR(150) NOT NULL   -- 'Generator 250-hour service'
trigger_type            VARCHAR(20)  NOT NULL   -- calendar|usage|both
interval_days           SMALLINT     NULL
interval_units          DECIMAL(12,2) NULL      -- km or hours
lead_time_days          SMALLINT     NOT NULL DEFAULT 7
task_checklist          JSON         NOT NULL
estimated_hours         DECIMAL(5,2) NULL
estimated_parts         JSON         NULL
assigned_team           VARCHAR(20)  NOT NULL
next_due_on             DATE         NULL
next_due_units          DECIMAL(12,2) NULL
last_generated_wo_id    BIGINT       NULL FK
is_active               TINYINT(1)   NOT NULL DEFAULT 1
  INDEX (school_id, next_due_on, is_active)

capital_projects
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
project_number          VARCHAR(40)  NOT NULL
name                    VARCHAR(200) NOT NULL
description             TEXT         NULL
budget_minor            BIGINT       NOT NULL
currency                CHAR(3)      NOT NULL
committed_minor         BIGINT       NOT NULL DEFAULT 0
spent_minor             BIGINT       NOT NULL DEFAULT 0
budget_line_id          BIGINT       NULL FK
starts_on               DATE         NOT NULL
target_completion       DATE         NULL
actual_completion       DATE         NULL
project_manager_id      BIGINT       NULL FK
main_contractor_id      BIGINT       NULL FK
status                  VARCHAR(20)  NOT NULL   -- planning|approved|in_progress|
                                                -- on_hold|completed|cancelled
capitalise_on_completion TINYINT(1)  NOT NULL DEFAULT 1   -- → FIN-10
  UNIQUE (school_id, project_number)

project_milestones
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK
project_id              BIGINT       FK INDEX
sequence                SMALLINT     NOT NULL
name                    VARCHAR(150) NOT NULL
target_date             DATE         NOT NULL
completed_date          DATE         NULL
payment_percent         DECIMAL(5,2) NULL       -- retention/stage payment
status                  VARCHAR(20)  NOT NULL
```

### 3. Business rules

| ID | Rule |
|---|---|
| `BR-OPS-02-001` | Any authenticated user may raise a fault report from web or mobile. It requires a location, a description and a severity, and nothing else. |
| `BR-OPS-02-002` ⭐ | Reports with `affects_safety = 1` jump the triage queue and alert the maintenance officer and the deputy head immediately, regardless of stated severity. |
| `BR-OPS-02-003` | Triage converts a report to a work order, marks it duplicate against an existing one, or rejects it with a reason. A report is never left untriaged beyond the configured window. |
| `BR-OPS-02-004` | Work orders above the configured value require approval and a budget line, checked against `FIN-11` availability. |
| `BR-OPS-02-005` ⭐ | Parts issued from stores create a `FIN-09` requisition against the work order's cost centre. Cost flows to the work order and to the ledger in one path. |
| `BR-OPS-02-006` | Labour cost uses the staff member's rate where recorded, or the configured default trade rate. |
| `BR-OPS-02-007` | Contractor cost enters through `FIN-08` as a supplier invoice linked to the work order. |
| `BR-OPS-02-008` | Total cost is the sum of labour, parts and contractor cost, and posts to the work order's cost centre. |
| `BR-OPS-02-009` | Preventive schedules generate work orders `lead_time_days` before the due date, by calendar, by usage, or whichever comes first. |
| `BR-OPS-02-010` | Usage-based schedules read from `OPS-01` odometer readings and `OPS-04` generator hours. |
| `BR-OPS-02-011` | Completion requires notes and, for safety-affecting work, a photograph. |
| `BR-OPS-02-012` | The original requester verifies completion. Unverified work orders after the configured window escalate. |
| `BR-OPS-02-013` | SLA is measured from triage to completion by priority, and reported by team and contractor. |
| `BR-OPS-02-014` | Work orders raised from `BRD-01` hostel damage link back, so the repair cost and the learner charge are visible together. |
| `BR-OPS-02-015` | Capital projects track budget, commitment and spend, and capitalise to `FIN-10` on completion where flagged. |
| `BR-OPS-02-016` | A maintenance asset of criticality `critical` with an overdue service alerts the head, not only the maintenance officer. |

### 4. Screens · API

| Screen | Component | Permission |
|---|---|---|
| **Report a fault** | `Ops\Maintenance\Report` | any user — mobile, photo, location picker |
| Triage queue | `Ops\Maintenance\Triage` | `maintenance.triage` — safety flagged first |
| Work orders | `Ops\Maintenance\WorkOrders` | `maintenance.view` |
| **Technician job card** | `Ops\Maintenance\JobCard` | `maintenance.execute` — mobile, checklist, parts request, hours, photos |
| Preventive schedules | `Ops\Maintenance\Schedules` | `maintenance.manage` — due, overdue, forecast |
| Asset maintenance history | `Ops\Maintenance\AssetHistory` | `maintenance.view` |
| Contractor management | `Ops\Maintenance\Contractors` | `maintenance.manage` |
| Capital projects | `Ops\Projects\Index` | `maintenance.project.manage` |
| SLA report | `Ops\Maintenance\Sla` | `maintenance.report.view` |
| Cost analysis | `Ops\Maintenance\Costs` | `maintenance.report.view` — by asset, type, cost centre |

```
POST /api/v1/maintenance/faults              any user, mobile, with photos
GET  /api/v1/maintenance/my-jobs             technician
POST /api/v1/maintenance/work-orders/{ulid}/start
POST /api/v1/maintenance/work-orders/{ulid}/parts
POST /api/v1/maintenance/work-orders/{ulid}/complete
POST /api/v1/maintenance/work-orders/{ulid}/verify   requester
```

### 5. Acceptance criteria

```gherkin
AC-OPS-02-001
  Given a fault report flagged affects_safety
  Then it jumps the triage queue
  And the maintenance officer and deputy head are alerted immediately

AC-OPS-02-002
  Given parts are issued from stores to a work order
  Then a FIN-09 requisition is created against the work order's cost centre
  And the cost appears on both the work order and the ledger

AC-OPS-02-003
  Given a generator reaches 250 hours since its last service
  Then a preventive work order is generated with the checklist attached

AC-OPS-02-004
  Given a hostel damage in BRD-01 raises a repair work order
  Then the two records link
  And the repair cost and the learner charge are visible together

AC-OPS-02-005
  Given a critical asset's service is overdue
  Then the head is alerted, not only the maintenance officer
```

---

# OPS-01 · Transport & Fleet Management 🇿🇼

### 1. Scope

Vehicle register, driver register with licensing, route and stop definition, learner assignment with zone-based fee calculation, trip scheduling and manifests, boarding capture with parent notification, fuel logging and anomaly detection, statutory compliance tracking, incident register, per-route costing.

### 2. Data model

```sql
vehicles
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
fleet_number            VARCHAR(20)  NOT NULL
registration_number     VARCHAR(20)  NOT NULL
vehicle_type            VARCHAR(30)  NOT NULL   -- bus|minibus|coaster|truck|
                                                -- pickup|tractor|car|ambulance
make                    VARCHAR(60)  NULL
model                   VARCHAR(80)  NULL
year_of_manufacture     SMALLINT     NULL
chassis_number          VARCHAR(60)  NULL
engine_number           VARCHAR(60)  NULL
seating_capacity        SMALLINT     NOT NULL
standing_capacity       SMALLINT     NOT NULL DEFAULT 0
fuel_type               VARCHAR(20)  NOT NULL   -- diesel|petrol
tank_capacity_litres    DECIMAL(8,2) NULL
expected_km_per_litre   DECIMAL(6,2) NULL       -- ⭐ anomaly baseline
current_odometer_km     DECIMAL(12,2) NOT NULL DEFAULT 0
fixed_asset_id          BIGINT       NULL FK    -- FIN-10
maintenance_asset_id    BIGINT       NULL FK    -- OPS-02
cost_centre_id          BIGINT       FK
assigned_driver_id      BIGINT       NULL FK
status                  VARCHAR(20)  NOT NULL   -- active|in_service|
                                                -- grounded|disposed
grounded_reason         VARCHAR(255) NULL
tracker_device_id       VARCHAR(80)  NULL       -- optional telemetry
is_active               TINYINT(1)   NOT NULL DEFAULT 1
  UNIQUE (school_id, fleet_number)
  UNIQUE (school_id, registration_number)
  INDEX  (school_id, status)

vehicle_compliance                    -- 🇿🇼 multi-agency ⭐
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
vehicle_id              BIGINT       FK INDEX
compliance_type         VARCHAR(40)  NOT NULL   -- vehicle_licence|zinara|
                                                -- certificate_of_fitness|
                                                -- insurance|passenger_insurance|
                                                -- route_permit|radio_licence|
                                                -- carbon_tax
reference_number        VARCHAR(60)  NULL
issued_on               DATE         NULL
expires_on              DATE         NOT NULL
cost_minor              BIGINT       NULL
currency                CHAR(3)      NULL
document_file_id        BIGINT       NULL FK
issuing_authority       VARCHAR(120) NULL
status                  VARCHAR(20)  NOT NULL   -- valid|expiring|expired|renewed
renewal_wo_id           BIGINT       NULL FK
  INDEX (school_id, vehicle_id, compliance_type)
  INDEX (school_id, expires_on, status)

drivers
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
staff_id                BIGINT       FK          -- PPL-04
licence_number          VARCHAR(40)  NOT NULL    -- ENCRYPTED
licence_classes         JSON         NOT NULL    -- ['1','2','4']
licence_expires_on      DATE         NOT NULL
defensive_driving_cert  VARCHAR(60)  NULL
defensive_expires_on    DATE         NULL
medical_certificate_on  DATE         NULL
medical_expires_on      DATE         NULL
retest_due_on           DATE         NULL
years_experience        SMALLINT     NULL
status                  VARCHAR(20)  NOT NULL    -- active|suspended|
                                                 -- expired_documents|inactive
suspension_reason       VARCHAR(255) NULL
incident_count          SMALLINT     NOT NULL DEFAULT 0
  UNIQUE (school_id, staff_id)
  INDEX  (school_id, licence_expires_on)

transport_zones                       -- ⭐ drives FIN-02 fee
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
code                    VARCHAR(20)  NOT NULL    -- 'Z1','Z2','Z3'
name                    VARCHAR(120) NOT NULL    -- 'Zone 1 — up to 10km'
max_distance_km         DECIMAL(6,2) NULL
fee_component_id        BIGINT       FK          -- FIN-02
termly_fee_minor        BIGINT       NOT NULL
currency                CHAR(3)      NOT NULL
is_active               TINYINT(1)   NOT NULL DEFAULT 1
  UNIQUE (school_id, code)

routes
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
academic_year_id        BIGINT       FK
code                    VARCHAR(20)  NOT NULL
name                    VARCHAR(150) NOT NULL
direction               VARCHAR(20)  NOT NULL    -- morning|afternoon|both
assigned_vehicle_id     BIGINT       NULL FK
assigned_driver_id      BIGINT       NULL FK
assistant_staff_id      BIGINT       NULL FK     -- conductor / monitor
total_distance_km       DECIMAL(8,2) NULL
estimated_duration_min  SMALLINT     NULL
departure_time          TIME         NULL
capacity                SMALLINT     NOT NULL
current_passengers      SMALLINT     NOT NULL DEFAULT 0
cost_centre_id          BIGINT       FK
is_active               TINYINT(1)   NOT NULL DEFAULT 1
  UNIQUE (school_id, academic_year_id, code)

route_stops
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK
route_id                BIGINT       FK INDEX
sequence                SMALLINT     NOT NULL
name                    VARCHAR(150) NOT NULL
landmark                VARCHAR(200) NULL
latitude                DECIMAL(10,7) NULL
longitude               DECIMAL(10,7) NULL
distance_from_school_km DECIMAL(6,2) NULL
zone_id                 BIGINT       NULL FK
scheduled_time          TIME         NULL
  UNIQUE (route_id, sequence)

learner_transport
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
academic_year_id        BIGINT       FK
term_id                 BIGINT       FK INDEX
student_id              BIGINT       FK INDEX
route_id                BIGINT       FK INDEX
pickup_stop_id          BIGINT       FK
dropoff_stop_id         BIGINT       NULL FK
zone_id                 BIGINT       FK          -- ⭐ FIN-02 billing driver
direction               VARCHAR(20)  NOT NULL    -- morning|afternoon|both
effective_from          DATE         NOT NULL
effective_to            DATE         NULL
status                  VARCHAR(20)  NOT NULL    -- active|suspended|ended
billing_status          VARCHAR(20)  NOT NULL DEFAULT 'pending'
fee_line_id             BIGINT       NULL FK
authorised_by_guardian  TINYINT(1)   NOT NULL DEFAULT 0
notes                   VARCHAR(255) NULL
  UNIQUE (school_id, term_id, student_id, direction, effective_from)
  INDEX  (school_id, route_id, status)

trips
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
term_id                 BIGINT       FK
trip_date               DATE         NOT NULL
trip_type               VARCHAR(20)  NOT NULL    -- route|fixture|excursion|
                                                 -- medical|staff|procurement
route_id                BIGINT       NULL FK
vehicle_id              BIGINT       FK INDEX
driver_id               BIGINT       FK
escort_staff_id         BIGINT       NULL FK
purpose                 VARCHAR(255) NULL
destination             VARCHAR(200) NULL
departure_odometer      DECIMAL(12,2) NULL
return_odometer         DECIMAL(12,2) NULL
distance_km             DECIMAL(8,2) NULL
departed_at             TIMESTAMP    NULL
returned_at             TIMESTAMP    NULL
passenger_count         SMALLINT     NULL
status                  VARCHAR(20)  NOT NULL    -- scheduled|departed|
                                                 -- completed|cancelled|incident
source_type             VARCHAR(40)  NULL        -- fixture|medical_referral
source_id               BIGINT       NULL
manifest_document_id    BIGINT       NULL FK
  INDEX (school_id, trip_date, status)
  INDEX (school_id, vehicle_id, trip_date)

trip_passengers                       -- the manifest
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK
trip_id                 BIGINT       FK INDEX
student_id              BIGINT       NULL FK
staff_id                BIGINT       NULL FK
stop_id                 BIGINT       NULL FK
boarded_at              TIMESTAMP    NULL
alighted_at             TIMESTAMP    NULL
boarding_method         VARCHAR(20)  NULL        -- rfid|qr|manual
guardian_notified_at    TIMESTAMP    NULL
status                  VARCHAR(20)  NOT NULL    -- expected|boarded|
                                                 -- alighted|absent|no_show
  UNIQUE (trip_id, student_id)
  INDEX  (school_id, student_id, trip_id)

fuel_logs                             -- ⭐ theft detection
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
term_id                 BIGINT       FK
vehicle_id              BIGINT       FK INDEX
fuelled_at              TIMESTAMP    NOT NULL
odometer_km             DECIMAL(12,2) NOT NULL
litres                  DECIMAL(8,2) NOT NULL
unit_price_minor        BIGINT       NOT NULL
total_cost_minor        BIGINT       NOT NULL
currency                CHAR(3)      NOT NULL
source                  VARCHAR(20)  NOT NULL    -- school_tank|filling_station|
                                                 -- coupon
supplier_id             BIGINT       NULL FK
store_requisition_id    BIGINT       NULL FK     -- FIN-09 school tank draw
receipt_file_id         BIGINT       NULL FK
driver_id               BIGINT       NULL FK
authorised_by           BIGINT       NULL FK
-- computed
km_since_last           DECIMAL(10,2) NULL
km_per_litre            DECIMAL(6,2) NULL
variance_percent        DECIMAL(6,2) NULL        -- vs expected_km_per_litre
is_anomaly              TINYINT(1)   NOT NULL DEFAULT 0
anomaly_reviewed_by     BIGINT       NULL FK
anomaly_explanation     TEXT         NULL
journal_id              BIGINT       NULL FK
  INDEX (school_id, vehicle_id, fuelled_at)
  INDEX (school_id, is_anomaly)

vehicle_incidents
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
vehicle_id              BIGINT       FK INDEX
driver_id               BIGINT       NULL FK
trip_id                 BIGINT       NULL FK
incident_type           VARCHAR(30)  NOT NULL    -- accident|breakdown|
                                                 -- traffic_offence|theft|
                                                 -- passenger_injury|near_miss
occurred_at             TIMESTAMP    NOT NULL
location                VARCHAR(200) NOT NULL
description             TEXT         NOT NULL
learners_involved       JSON         NULL
injuries                TINYINT(1)   NOT NULL DEFAULT 0
police_report_number    VARCHAR(60)  NULL
insurance_claim_number  VARCHAR(60)  NULL
estimated_damage_minor  BIGINT       NULL
photo_file_ids          JSON         NULL
work_order_id           BIGINT       NULL FK
reported_by             BIGINT       FK → users.id
status                  VARCHAR(20)  NOT NULL    -- reported|investigating|
                                                 -- claim_lodged|resolved|closed
```

### 3. ⭐ Fuel anomaly detection

```
On every fuel log entry:

  km_since_last  = odometer_km − previous entry's odometer_km
  km_per_litre   = km_since_last / litres
  variance       = (km_per_litre − expected_km_per_litre) / expected × 100

  IF |variance| > transport.fuel_variance_tolerance_percent  (default 20)
     → is_anomaly = 1
     → transport manager alerted
     → requires a recorded explanation; cannot be dismissed

  ALSO flagged as anomalies:
     • odometer reading lower than the previous entry
     • litres exceeding tank capacity
     • two fuellings within transport.min_refuel_hours
     • fuelling on a date with no recorded trip
     • cumulative consumption over 30 days beyond tolerance,
       even where individual entries pass
```

That last check matters most. A driver skimming five litres per fill passes every individual variance test and shows up clearly in a rolling thirty-day view.

### 4. Business rules

| ID | Rule |
|---|---|
| `BR-OPS-01-001` | A vehicle with any expired compliance record cannot be assigned to a trip. The block names the expired item. |
| `BR-OPS-01-002` | 🇿🇼 Compliance types are tracked separately with independent expiry: vehicle licence, ZINARA, certificate of fitness, insurance, passenger insurance, route permit and radio licence. |
| `BR-OPS-01-003` | Compliance alerts fire at 60, 30 and 7 days, and a renewal work order can be raised from the alert. |
| `BR-OPS-01-004` | A driver with an expired licence, medical certificate or defensive driving certificate cannot be assigned. |
| `BR-OPS-01-005` | Trip passenger count must not exceed the vehicle's seating capacity. Standing capacity applies only where the vehicle type and permit allow. |
| `BR-OPS-01-006` ⭐ | Learner transport assignment sets `zone_id`, which drives the `USAGE_BASED` transport fee in `FIN-02`. Changing zone mid-term prorates. |
| `BR-OPS-01-007` | Route capacity is enforced. Assignment beyond capacity requires override with a reason. |
| `BR-OPS-01-008` | Guardian authorisation is required before a learner is placed on a route, captured through the portal. |
| `BR-OPS-01-009` | Manifests generate per trip per direction, are printable, and are available on the driver's phone. |
| `BR-OPS-01-010` | Boarding and alighting capture notifies the guardian where enabled. A learner expected but not boarded raises a no-show alert to the school. |
| `BR-OPS-01-011` ⭐ | Every trip records departure and return odometer. Distance is computed, never entered directly. |
| `BR-OPS-01-012` ⭐ | Odometer readings feed `FIN-10` units-of-production depreciation and `OPS-02` usage-based service schedules. |
| `BR-OPS-01-013` | Fuel drawn from the school tank creates a `FIN-09` requisition, so tank stock and vehicle consumption reconcile. |
| `BR-OPS-01-014` | Fuel anomalies require a recorded explanation and cannot be dismissed. Repeated unexplained anomalies on the same vehicle or driver escalate to the bursar. |
| `BR-OPS-01-015` | Trips for medical referrals (`BRD-06`) and sports fixtures (`OPS-07`) are created from those modules with the source linked. |
| `BR-OPS-01-016` | Learners on a fixture trip receive roll status `fixture` in `BRD-02`. |
| `BR-OPS-01-017` | Vehicle incidents involving learners notify guardians immediately and open a `BRD-06` health incident where there are injuries. |
| `BR-OPS-01-018` | Per-route costing aggregates fuel, maintenance, driver cost, depreciation and compliance against transport fee income, answering whether a route is viable. |
| `BR-OPS-01-019` | GPS telemetry, where integrated, supplements but never replaces manifest and odometer records. |

### 5. Screens · API · Settings

| Screen | Component | Permission |
|---|---|---|
| Fleet register | `Ops\Transport\Fleet` | `transport.view` |
| **Compliance monitor** | `Ops\Transport\Compliance` | `transport.manage` 🇿🇼 — every certificate, days to expiry, grounded vehicles |
| Drivers | `Ops\Transport\Drivers` | `transport.manage` — licence and medical expiry |
| Routes & stops | `Ops\Transport\Routes` | `transport.manage` — map view, capacity, zone assignment |
| Learner assignment | `Ops\Transport\Assignment` | `transport.assign` — **shows the resulting termly fee** |
| Trip scheduling | `Ops\Transport\Trips` | `transport.trip.manage` |
| **Driver manifest** | `Ops\Transport\Manifest` | `transport.drive` — mobile, tap to board, offline capable |
| Fuel log | `Ops\Transport\Fuel` | `transport.fuel.record` |
| **Fuel anomalies** | `Ops\Transport\FuelAnomalies` | `transport.fuel.review` ⭐ |
| Incidents | `Ops\Transport\Incidents` | `transport.incident.manage` |
| Route costing | `Ops\Transport\RouteCosts` | `transport.report.view` |

```
GET  /api/v1/transport/my-trips             driver: today's trips
GET  /api/v1/transport/trips/{ulid}/manifest
POST /api/v1/transport/trips/{ulid}/board   Idempotency-Key; offline queue
POST /api/v1/transport/trips/{ulid}/odometer
GET  /api/v1/transport/my-child             guardian: route, stop, times, notifications
POST /api/v1/transport/fuel                 fuel log with receipt photo
```

| Setting | Type | Default |
|---|---|---|
| `transport.compliance_alert_days` | array | `[60,30,7]` |
| `transport.block_trip_on_expired_compliance` | bool | `true` (**locked**) |
| `transport.fuel_variance_tolerance_percent` | int | `20` |
| `transport.fuel_rolling_window_days` | int | `30` |
| `transport.min_refuel_hours` | int | `4` |
| `transport.notify_guardian_on_board` | bool | `true` |
| `transport.notify_guardian_on_alight` | bool | `true` |
| `transport.enforce_route_capacity` | bool | `true` |

Events: `VehicleComplianceExpiring` 🇿🇼 ⚠ · `VehicleGrounded` ⚠ · `DriverDocumentExpired` ⚠ · `LearnerAssignedToRoute` (→ `FIN-02`) · `TripDeparted` · `LearnerBoarded` · `LearnerNoShow` ⚠ · `FuelAnomalyDetected` ⚠ · `OdometerRecorded` (→ `FIN-10`, `OPS-02`) · `VehicleIncidentReported` ⚠

### 6. Acceptance criteria

```gherkin
AC-OPS-01-001
  Given a vehicle's certificate of fitness expired yesterday
  When a trip is scheduled for it
  Then it is refused naming the expired certificate

AC-OPS-01-002
  Given a learner is assigned to Zone 2
  Then FIN-02 bills the Zone 2 termly transport fee
  And changing to Zone 3 mid-term prorates both

AC-OPS-01-003
  Given a bus achieves 4.2 km/l against an expected 6.0
  Then the variance of 30% exceeds tolerance
  And an anomaly is raised requiring a recorded explanation

AC-OPS-01-004
  Given a driver draws 5 litres less than logged on each of six fills
  And each individual variance is within tolerance
  Then the 30-day rolling check flags the cumulative shortfall

AC-OPS-01-005
  Given trip odometer readings are recorded
  Then FIN-10 receives units consumed for depreciation
  And OPS-02 evaluates the usage-based service schedule

AC-OPS-01-006
  Given a learner is expected on the morning route and does not board
  Then a no-show alert reaches the school
  And the guardian is notified

AC-OPS-01-007
  Given fuel is drawn from the school tank
  Then a FIN-09 requisition depletes tank stock
  And tank stock reconciles to vehicle consumption

AC-OPS-01-008
  Given learners travel to an away fixture
  Then their roll call status is 'fixture'
  And they are not counted as missing
```

---

# OPS-04 · Utilities & Energy Management 🇿🇼

> In a load-shedding economy, energy is a top-three operating cost and a top-three source of unbudgeted spend. This module exists so the head can answer *"what does an outage actually cost us?"* with a number.

### 1. Scope

Utility accounts and meters, prepaid electricity token tracking, meter readings and consumption analysis, generator run-hours and diesel, solar generation, borehole and water storage, per-department cost allocation, load-shedding impact reporting.

### 2. Data model

```sql
utility_accounts
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
utility_type            VARCHAR(30)  NOT NULL   -- electricity|water|refuse|
                                                -- sewerage|gas|telecoms
provider                VARCHAR(120) NOT NULL   -- 'ZESA','City Council'
account_number          VARCHAR(60)  NOT NULL
tariff_code             VARCHAR(40)  NULL
billing_mode            VARCHAR(20)  NOT NULL   -- prepaid|postpaid
cost_centre_id          BIGINT       FK
expense_account_id      BIGINT       FK
is_active               TINYINT(1)   NOT NULL DEFAULT 1
  UNIQUE (school_id, utility_type, account_number)

meters
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
utility_account_id      BIGINT       FK INDEX
meter_number            VARCHAR(60)  NOT NULL
meter_type              VARCHAR(30)  NOT NULL   -- electricity_prepaid|
                                                -- electricity_postpaid|water|
                                                -- submeter
location                VARCHAR(150) NOT NULL
serves_scope            VARCHAR(30)  NOT NULL   -- whole_school|building|
                                                -- hostel|department|farm|
                                                -- staff_housing
scope_id                BIGINT       NULL
cost_centre_id          BIGINT       NULL FK    -- ⭐ per-department allocation
unit                    VARCHAR(20)  NOT NULL   -- kWh|m3
multiplier              DECIMAL(8,4) NOT NULL DEFAULT 1
current_reading         DECIMAL(14,3) NULL
current_balance_units   DECIMAL(12,3) NULL      -- prepaid remaining
low_balance_threshold   DECIMAL(12,3) NULL
last_read_on            DATE         NULL
is_active               TINYINT(1)   NOT NULL DEFAULT 1
  UNIQUE (school_id, meter_number)
  INDEX  (school_id, meter_type, is_active)

prepaid_token_purchases               -- 🇿🇼 ⭐
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
term_id                 BIGINT       FK
meter_id                BIGINT       FK INDEX
purchased_at            TIMESTAMP    NOT NULL
token_number            VARCHAR(60)  NOT NULL   -- the 20-digit token
amount_paid_minor       BIGINT       NOT NULL
currency                CHAR(3)      NOT NULL
units_purchased         DECIMAL(12,3) NOT NULL  -- kWh
levies_minor            BIGINT       NOT NULL DEFAULT 0
effective_rate_minor    BIGINT       NULL       -- per unit, computed
vendor                  VARCHAR(120) NULL
receipt_file_id         BIGINT       NULL FK
purchased_by            BIGINT       FK → users.id
-- ⭐ the control
credited_at             TIMESTAMP    NULL       -- when actually loaded to the meter
credited_by             BIGINT       NULL FK
credit_confirmed        TINYINT(1)   NOT NULL DEFAULT 0
status                  VARCHAR(20)  NOT NULL   -- purchased|credited|
                                                -- failed|disputed|expired
failure_note            VARCHAR(255) NULL
journal_id              BIGINT       NULL FK
  UNIQUE (school_id, token_number)
  INDEX  (school_id, meter_id, purchased_at)
  INDEX  (school_id, credit_confirmed, status)

meter_readings                        -- APPEND-ONLY
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
meter_id                BIGINT       FK INDEX
read_on                 DATE         NOT NULL
reading                 DECIMAL(14,3) NOT NULL
previous_reading        DECIMAL(14,3) NULL
consumption             DECIMAL(14,3) NULL      -- computed
days_since_last         SMALLINT     NULL
daily_average           DECIMAL(12,3) NULL
reading_method          VARCHAR(20)  NOT NULL   -- manual|photo|smart|estimated
photo_file_id           BIGINT       NULL FK
read_by                 BIGINT       FK → users.id
is_anomaly              TINYINT(1)   NOT NULL DEFAULT 0
anomaly_note            TEXT         NULL
  UNIQUE (meter_id, read_on)
  INDEX  (school_id, read_on)

generators
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
code                    VARCHAR(20)  NOT NULL
name                    VARCHAR(120) NOT NULL
capacity_kva            DECIMAL(8,2) NOT NULL
fuel_type               VARCHAR(20)  NOT NULL DEFAULT 'diesel'
tank_capacity_litres    DECIMAL(8,2) NULL
expected_litres_per_hour DECIMAL(6,3) NULL      -- ⭐ anomaly baseline
serves_scope            VARCHAR(30)  NOT NULL
scope_id                BIGINT       NULL
current_hours           DECIMAL(10,2) NOT NULL DEFAULT 0
fixed_asset_id          BIGINT       NULL FK    -- FIN-10
maintenance_asset_id    BIGINT       NULL FK    -- OPS-02
cost_centre_id          BIGINT       FK
status                  VARCHAR(20)  NOT NULL   -- operational|standby|
                                                -- maintenance|faulty
  UNIQUE (school_id, code)

generator_runs                        -- ⭐ feeds FIN-10 and OPS-02
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
term_id                 BIGINT       FK
generator_id            BIGINT       FK INDEX
run_date                DATE         NOT NULL
started_at              TIMESTAMP    NOT NULL
stopped_at              TIMESTAMP    NULL
hours_run               DECIMAL(6,2) NULL
start_hour_meter        DECIMAL(10,2) NOT NULL
end_hour_meter          DECIMAL(10,2) NULL
reason                  VARCHAR(30)  NOT NULL   -- load_shedding|fault|
                                                -- test|maintenance|event
load_shedding_stage     VARCHAR(20)  NULL
diesel_litres           DECIMAL(8,2) NULL
diesel_cost_minor       BIGINT       NULL
currency                CHAR(3)      NULL
litres_per_hour         DECIMAL(6,3) NULL       -- computed
is_anomaly              TINYINT(1)   NOT NULL DEFAULT 0
store_requisition_id    BIGINT       NULL FK    -- FIN-09 diesel draw
operated_by             BIGINT       NULL FK
journal_id              BIGINT       NULL FK
  INDEX (school_id, generator_id, run_date)
  INDEX (school_id, reason, run_date)

solar_installations
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
code                    VARCHAR(20)  NOT NULL
name                    VARCHAR(120) NOT NULL
capacity_kwp            DECIMAL(8,2) NOT NULL
battery_capacity_kwh    DECIMAL(8,2) NULL
serves_scope            VARCHAR(30)  NOT NULL
scope_id                BIGINT       NULL
commissioned_on         DATE         NULL
fixed_asset_id          BIGINT       NULL FK
maintenance_asset_id    BIGINT       NULL FK
status                  VARCHAR(20)  NOT NULL
  UNIQUE (school_id, code)

solar_generation
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
installation_id         BIGINT       FK INDEX
record_date             DATE         NOT NULL
kwh_generated           DECIMAL(10,3) NOT NULL
kwh_consumed            DECIMAL(10,3) NULL
battery_state_percent   DECIMAL(5,2) NULL
grid_offset_kwh         DECIMAL(10,3) NULL      -- what it saved
recorded_by             BIGINT       NULL FK
reading_method          VARCHAR(20)  NOT NULL   -- manual|inverter_api
  UNIQUE (installation_id, record_date)

water_sources
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
code                    VARCHAR(20)  NOT NULL
name                    VARCHAR(120) NOT NULL
source_type             VARCHAR(30)  NOT NULL   -- borehole|municipal|
                                                -- river|rainwater|tank
depth_metres            DECIMAL(8,2) NULL
yield_litres_per_hour   DECIMAL(10,2) NULL
pump_capacity           VARCHAR(60)  NULL
storage_capacity_litres DECIMAL(12,2) NULL
maintenance_asset_id    BIGINT       NULL FK
status                  VARCHAR(20)  NOT NULL   -- operational|reduced_yield|
                                                -- dry|faulty|standby
last_tested_on          DATE         NULL
water_quality_status    VARCHAR(20)  NULL       -- potable|treatment_required|
                                                -- not_potable
last_quality_test_on    DATE         NULL
  UNIQUE (school_id, code)

water_readings
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
water_source_id         BIGINT       FK INDEX
read_on                 DATE         NOT NULL
storage_level_percent   DECIMAL(5,2) NULL
volume_pumped_litres    DECIMAL(12,2) NULL
pump_hours              DECIMAL(6,2) NULL
yield_observed          DECIMAL(10,2) NULL
notes                   VARCHAR(255) NULL
read_by                 BIGINT       FK → users.id
  UNIQUE (water_source_id, read_on)

load_shedding_schedule                -- 🇿🇼
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
schedule_date           DATE         NOT NULL
stage                   VARCHAR(20)  NULL
starts_at               TIME         NOT NULL
ends_at                 TIME         NOT NULL
actual_outage_start     TIMESTAMP    NULL
actual_outage_end       TIMESTAMP    NULL
was_scheduled           TIMESTAMP(1) NULL
source                  VARCHAR(30)  NOT NULL   -- published|observed
impact_note             VARCHAR(255) NULL
  INDEX (school_id, schedule_date)
```

### 3. ⭐ Prepaid token reconciliation

The control that recovers real money. A token purchased for $200 and never credited to the meter is $200 gone, and nobody notices because the electricity kept working on the previous balance.

```
PURCHASE
  Token number, amount paid, units purchased recorded
  Journal: Dr Prepaid Electricity (asset) / Cr Bank
  status = purchased, credit_confirmed = 0

CREDIT
  Staff member loads the token at the meter and confirms
  credited_at, credited_by set, credit_confirmed = 1

RECONCILIATION (nightly)
  Uncredited tokens older than utilities.token_credit_window_hours
     → alert to the estates manager and bursar
  For each meter, monthly:
     Σ(units purchased and credited)  vs  Σ(consumption from readings)
     Difference beyond tolerance → investigation

CONSUMPTION EXPENSE
  Recognised on consumption, not on purchase:
  Journal: Dr Electricity Expense (by cost centre) / Cr Prepaid Electricity
```

**Expense recognises on consumption, not purchase.** Buying $2,000 of tokens in December for use through January is a prepayment, not a December expense. Getting this wrong distorts every term's utility line.

### 4. ⭐ The cost of an outage

```
For a given period:
   grid_kwh       = Σ meter consumption
   grid_cost      = Σ token spend credited + postpaid invoices
   grid_rate      = grid_cost / grid_kwh

   generator_kwh  = Σ (hours_run × capacity_kva × assumed_load_factor)
   generator_cost = Σ diesel cost + apportioned maintenance + depreciation
   generator_rate = generator_cost / generator_kwh

   solar_kwh      = Σ kwh_generated
   solar_cost     = apportioned maintenance + depreciation
   solar_rate     = solar_cost / solar_kwh

   OUTAGE COST = generator_kwh × (generator_rate − grid_rate)
                 + estimated productivity impact (optional, flagged as estimate)
```

The dashboard states it plainly: *"Load shedding cost the school USD 3,240 this term in additional generation, over 186 hours of outage."* That sentence is what justifies a solar investment to a board, and no bursar can assemble it by hand.

### 5. Business rules

| ID | Rule |
|---|---|
| `BR-OPS-04-001` ⭐ | Every prepaid token purchase records the token number, amount, units and purchaser, and remains uncredited until a person confirms it was loaded. |
| `BR-OPS-04-002` | Tokens uncredited beyond the configured window alert the estates manager and bursar. |
| `BR-OPS-04-003` | Token numbers are unique per school. A duplicate entry is refused. |
| `BR-OPS-04-004` ⭐ | Electricity expense recognises on consumption, not on purchase. Purchases create a prepayment asset. |
| `BR-OPS-04-005` | Monthly reconciliation compares credited units against metered consumption. Variance beyond tolerance requires investigation. |
| `BR-OPS-04-006` | Meter readings are append-only. A correction is a new reading with a note, never an edit. |
| `BR-OPS-04-007` | A reading lower than the previous one flags as an anomaly and requires explanation — meter replacement, rollover, or misreading. |
| `BR-OPS-04-008` | Consumption beyond the configured tolerance against the rolling average flags for investigation. |
| `BR-OPS-04-009` ⭐ | Submetered consumption allocates to the meter's cost centre, so boarding, the farm and the academic block each carry their real energy cost. |
| `BR-OPS-04-010` | Unallocated consumption — total school meter less the sum of submeters — is reported as a distinct line and investigated when it grows. |
| `BR-OPS-04-011` ⭐ | Generator hours feed `FIN-10` units-of-production depreciation and `OPS-02` service schedules. |
| `BR-OPS-04-012` | Diesel drawn from the school tank creates a `FIN-09` requisition. Litres per hour is computed and compared to the baseline; deviation flags an anomaly. |
| `BR-OPS-04-013` | Generator runs record their reason. Load-shedding runs are separable from fault and test runs for cost analysis. |
| `BR-OPS-04-014` | Solar generation offsets grid consumption and is reported as avoided cost, computed at the current grid rate. |
| `BR-OPS-04-015` | Borehole yield below the configured percentage of baseline flags as reduced yield and raises a work order. |
| `BR-OPS-04-016` | Water storage below the configured threshold alerts the estates manager and the boarding master. **Water is a boarding-viability issue, not a utility line.** |
| `BR-OPS-04-017` | Water quality tests are recorded with dates; an overdue test alerts, and a `not_potable` result alerts the nurse and the catering manager immediately. |
| `BR-OPS-04-018` | Utility costs post to their cost centres monthly and appear in `FIN-11` budget variance. |
| `BR-OPS-04-019` | Consumption is benchmarked term-on-term and year-on-year, normalised per boarder-day where the meter serves boarding. |

### 6. Screens · API · Settings

| Screen | Component | Permission |
|---|---|---|
| Utility accounts | `Ops\Utilities\Accounts` | `utilities.manage` |
| Meters | `Ops\Utilities\Meters` | `utilities.manage` — scope, cost centre, current balance |
| **Token purchases** | `Ops\Utilities\Tokens` | `utilities.token.record` 🇿🇼 ⭐ — purchase, confirm credit, uncredited queue |
| Meter readings | `Ops\Utilities\Readings` | `utilities.read` — mobile with photo capture |
| Consumption analysis | `Ops\Utilities\Consumption` | `utilities.report.view` — per meter, per cost centre, per boarder-day |
| Generators | `Ops\Utilities\Generators` | `utilities.generator.manage` |
| Generator log | `Ops\Utilities\GeneratorRuns` | `utilities.generator.record` — start, stop, diesel, reason |
| Solar | `Ops\Utilities\Solar` | `utilities.manage` — generation, offset, avoided cost |
| Water | `Ops\Utilities\Water` | `utilities.water.manage` — sources, storage, yield, quality |
| **Energy dashboard** | `Ops\Utilities\Dashboard` | `utilities.report.view` ⭐ — grid vs generator vs solar, cost of outages |
| Load shedding | `Ops\Utilities\LoadShedding` | `utilities.manage` — schedule, actual, impact |

```
POST /api/v1/utilities/readings         mobile with photo
POST /api/v1/utilities/tokens           record purchase
POST /api/v1/utilities/tokens/{ulid}/confirm-credit
POST /api/v1/utilities/generator-runs   start and stop
POST /api/v1/utilities/water-readings
```

| Setting | Type | Default |
|---|---|---|
| `utilities.token_credit_window_hours` | int | `24` |
| `utilities.token_reconciliation_tolerance_percent` | int | `5` |
| `utilities.consumption_anomaly_tolerance_percent` | int | `25` |
| `utilities.generator_load_factor` | decimal | `0.60` |
| `utilities.generator_fuel_variance_percent` | int | `20` |
| `utilities.water_storage_alert_percent` | int | `30` |
| `utilities.borehole_yield_alert_percent` | int | `70` |
| `utilities.water_quality_test_days` | int | `90` |

Events: `TokenPurchased` · `TokenUncredited` ⚠ · `TokenReconciliationVariance` ⚠ · `MeterReadingAnomaly` ⚠ · `GeneratorStarted` · `GeneratorRunRecorded` (→ `FIN-10`, `OPS-02`) · `GeneratorFuelAnomaly` ⚠ · `WaterStorageLow` ⚠ · `BoreholeYieldReduced` ⚠ · `WaterQualityFailed` ⚠⚠ · `UtilityBudgetExceeded`

### 7. Acceptance criteria

```gherkin
AC-OPS-04-001
  Given a token is purchased for USD 200 and never credited
  When the credit window elapses
  Then the estates manager and bursar are alerted
  And it appears on the uncredited tokens queue

AC-OPS-04-002
  Given tokens worth USD 2,000 are purchased in December for January use
  Then December records a prepayment asset, not an expense
  And expense recognises as January consumption is metered

AC-OPS-04-003
  Given credited units for a meter total 4,200 kWh
  And metered consumption is 5,100 kWh for the same period
  Then the variance exceeds tolerance and requires investigation

AC-OPS-04-004
  Given a meter reading lower than the previous reading is entered
  Then it flags as an anomaly requiring explanation

AC-OPS-04-005
  Given the boarding submeter consumed 12,400 kWh
  Then that cost allocates to the boarding cost centre
  And appears in FIN-11 budget variance for boarding

AC-OPS-04-006
  Given a generator ran 186 hours on load shedding this term
  Then the energy dashboard states the additional cost over grid rate

AC-OPS-04-007
  Given generator hours are recorded
  Then FIN-10 receives units consumed
  And OPS-02 evaluates the hour-based service schedule

AC-OPS-04-008
  Given borehole yield falls to 60% of baseline
  Then the source is flagged reduced_yield and a work order is raised

AC-OPS-04-009
  Given a water quality test returns not_potable
  Then the nurse and catering manager are alerted immediately
```

---

# OPS-03 · Estates, Farm & Production Units 🇿🇼

> Many large Zimbabwean boarding schools operate working farms that feed the kitchen. Almost no competing product models this. It is a genuine differentiator and, done properly, it answers a question every such school's board asks: **does the farm actually save us money, or is it a subsidy?**

### 1. Scope

Land and field register, crop planning and input costing, yield recording, livestock management, poultry cycles, irrigation, internal transfer of produce to the kitchen at cost, external sales of surplus, farm labour, per-unit profitability.

### 2. Data model

```sql
production_units                      -- the farm, divided
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
code                    VARCHAR(20)  NOT NULL   -- 'CROP-A','DAIRY','POULTRY'
name                    VARCHAR(150) NOT NULL
unit_type               VARCHAR(30)  NOT NULL   -- crop|livestock|poultry|
                                                -- dairy|orchard|vegetable_garden|
                                                -- piggery|fishery
cost_centre_id          BIGINT       FK          -- ⭐ own P&L
manager_staff_id        BIGINT       NULL FK
store_id                BIGINT       NULL FK    -- FIN-09 farm store
area_hectares           DECIMAL(10,4) NULL
is_active               TINYINT(1)   NOT NULL DEFAULT 1
  UNIQUE (school_id, code)

fields
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
production_unit_id      BIGINT       FK INDEX
code                    VARCHAR(20)  NOT NULL
name                    VARCHAR(120) NOT NULL
area_hectares           DECIMAL(10,4) NOT NULL
soil_type               VARCHAR(60)  NULL
is_irrigated            TINYINT(1)   NOT NULL DEFAULT 0
irrigation_type         VARCHAR(30)  NULL       -- drip|sprinkler|flood
water_source_id         BIGINT       NULL FK    -- OPS-04
last_soil_test_on       DATE         NULL
notes                   TEXT         NULL
  UNIQUE (school_id, code)

crop_cycles
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
academic_year_id        BIGINT       FK
production_unit_id      BIGINT       FK INDEX
field_id                BIGINT       FK
cycle_reference         VARCHAR(40)  NOT NULL
crop                    VARCHAR(120) NOT NULL   -- 'Maize','Rape','Tomatoes'
variety                 VARCHAR(120) NULL
season                  VARCHAR(30)  NOT NULL   -- summer|winter|irrigated
area_planted_hectares   DECIMAL(10,4) NOT NULL
planted_on              DATE         NULL
expected_harvest_on     DATE         NULL
actual_harvest_on       DATE         NULL
expected_yield_kg       DECIMAL(12,2) NULL
actual_yield_kg         DECIMAL(12,2) NULL
-- costing
input_cost_minor        BIGINT       NOT NULL DEFAULT 0
labour_cost_minor       BIGINT       NOT NULL DEFAULT 0
overhead_cost_minor     BIGINT       NOT NULL DEFAULT 0
total_cost_minor        BIGINT       NOT NULL DEFAULT 0
cost_per_kg_minor       BIGINT       NULL       -- ⭐ the transfer price
currency                CHAR(3)      NOT NULL
status                  VARCHAR(20)  NOT NULL   -- planned|planted|growing|
                                                -- harvesting|harvested|
                                                -- failed|abandoned
failure_reason          VARCHAR(255) NULL
  UNIQUE (school_id, cycle_reference)
  INDEX  (school_id, production_unit_id, status)

crop_inputs                           -- from FIN-09 farm store
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK
crop_cycle_id           BIGINT       FK INDEX
input_type              VARCHAR(30)  NOT NULL   -- seed|fertiliser|chemical|
                                                -- fuel|water|other
item_id                 BIGINT       NULL FK
description             VARCHAR(200) NOT NULL
quantity                DECIMAL(12,4) NOT NULL
unit                    VARCHAR(20)  NOT NULL
applied_on              DATE         NOT NULL
store_requisition_id    BIGINT       NULL FK    -- FIN-09
cost_minor              BIGINT       NOT NULL
currency                CHAR(3)      NOT NULL
applied_by              BIGINT       NULL FK

harvests
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
crop_cycle_id           BIGINT       FK INDEX
harvested_on            DATE         NOT NULL
quantity_kg             DECIMAL(12,2) NOT NULL
quality_grade           VARCHAR(20)  NULL
moisture_percent        DECIMAL(5,2) NULL
unit_cost_minor         BIGINT       NOT NULL   -- allocated cycle cost per kg
currency                CHAR(3)      NOT NULL
destination             VARCHAR(20)  NOT NULL   -- store|kitchen|sale|seed|waste
store_id                BIGINT       NULL FK
stock_lot_id            BIGINT       NULL FK    -- ⭐ FIN-09 lot created
journal_id              BIGINT       NULL FK
recorded_by             BIGINT       FK → users.id

livestock
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
production_unit_id      BIGINT       FK INDEX
tag_number              VARCHAR(40)  NULL       -- individual, e.g. cattle
species                 VARCHAR(40)  NOT NULL   -- cattle|goat|sheep|pig|poultry
breed                   VARCHAR(80)  NULL
is_herd_record          TINYINT(1)   NOT NULL DEFAULT 0   -- flock-level
head_count              INT          NOT NULL DEFAULT 1
sex                     VARCHAR(10)  NULL
date_of_birth           DATE         NULL
acquired_on             DATE         NULL
acquisition_type        VARCHAR(20)  NULL       -- born|purchased|donated
acquisition_cost_minor  BIGINT       NULL
currency                CHAR(3)      NULL
purpose                 VARCHAR(30)  NOT NULL   -- breeding|dairy|meat|
                                                -- layers|broilers|draught
status                  VARCHAR(20)  NOT NULL   -- active|sold|slaughtered|
                                                -- died|lost|culled
disposal_on             DATE         NULL
disposal_reason         VARCHAR(255) NULL
fixed_asset_id          BIGINT       NULL FK    -- breeding stock capitalised
  INDEX (school_id, production_unit_id, status)
  INDEX (school_id, species, status)

livestock_events                      -- APPEND-ONLY
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK
livestock_id            BIGINT       FK INDEX
event_type              VARCHAR(30)  NOT NULL   -- birth|death|dipping|
                                                -- vaccination|treatment|
                                                -- weighing|service|calving|
                                                -- sale|slaughter|feed
event_date              DATE         NOT NULL
head_count_affected     INT          NOT NULL DEFAULT 1
description             VARCHAR(500) NULL
medication              VARCHAR(150) NULL
dosage                  VARCHAR(60)  NULL
withdrawal_period_days  SMALLINT     NULL       -- ⭐ meat/milk withholding
withdrawal_ends_on      DATE         NULL
weight_kg               DECIMAL(8,2) NULL
cost_minor              BIGINT       NULL
performed_by            VARCHAR(150) NULL       -- vet or staff
recorded_by             BIGINT       FK → users.id
  INDEX (school_id, livestock_id, event_date)
  INDEX (school_id, event_type, event_date)

production_outputs                    -- milk, eggs, meat
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
production_unit_id      BIGINT       FK INDEX
output_date             DATE         NOT NULL
output_type             VARCHAR(30)  NOT NULL   -- milk|eggs|meat|manure
quantity                DECIMAL(12,3) NOT NULL
unit                    VARCHAR(20)  NOT NULL   -- litres|dozen|kg
unit_cost_minor         BIGINT       NULL
currency                CHAR(3)      NOT NULL
destination             VARCHAR(20)  NOT NULL   -- kitchen|store|sale|waste
store_id                BIGINT       NULL FK
stock_lot_id            BIGINT       NULL FK
journal_id              BIGINT       NULL FK
recorded_by             BIGINT       FK → users.id
  UNIQUE (production_unit_id, output_date, output_type)

internal_transfers                    -- ⭐ farm → kitchen; closes Book F
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
term_id                 BIGINT       FK
transfer_number         VARCHAR(40)  NOT NULL
production_unit_id      BIGINT       FK
from_store_id           BIGINT       FK          -- farm store
to_store_id             BIGINT       FK          -- kitchen store
transfer_date           DATE         NOT NULL
harvest_id              BIGINT       NULL FK
output_id               BIGINT       NULL FK
item_id                 BIGINT       FK
quantity                DECIMAL(12,3) NOT NULL
unit                    VARCHAR(20)  NOT NULL
unit_cost_minor         BIGINT       NOT NULL   -- ⭐ at internal cost
total_cost_minor        BIGINT       NOT NULL
currency                CHAR(3)      NOT NULL
market_price_minor      BIGINT       NULL       -- for savings reporting
journal_id              BIGINT       NULL FK
dispatched_by           BIGINT       FK → users.id
received_by             BIGINT       NULL FK
status                  VARCHAR(20)  NOT NULL   -- dispatched|received|discrepancy
  UNIQUE (school_id, transfer_number)
  INDEX  (school_id, term_id, transfer_date)

farm_sales
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
term_id                 BIGINT       FK
sale_number             VARCHAR(40)  NOT NULL
production_unit_id      BIGINT       FK
sale_date               DATE         NOT NULL
buyer_name              VARCHAR(200) NOT NULL
buyer_contact           VARCHAR(80)  NULL
item_description        VARCHAR(255) NOT NULL
quantity                DECIMAL(12,3) NOT NULL
unit                    VARCHAR(20)  NOT NULL
unit_price_minor        BIGINT       NOT NULL
total_minor             BIGINT       NOT NULL
currency                CHAR(3)      NOT NULL
cost_of_sales_minor     BIGINT       NULL
receipt_id              BIGINT       NULL FK    -- FIN-04
fiscal_receipt_id       BIGINT       NULL FK    -- 🇿🇼 FIN-13
journal_id              BIGINT       NULL FK
  UNIQUE (school_id, sale_number)
```

### 3. ⭐ Internal transfer at cost — closing the Book F interface

```
HARVEST
  600 kg tomatoes from crop cycle CROP-A/2026/T3
  Cycle total cost      USD 1,440
  Cycle total yield     1,800 kg
  Cost per kg           USD 0.80

  Journal on harvest:
     Dr Farm Inventory (farm store)     480.00     (600 × 0.80)
       Cr Farm Production (contra cost)   480.00
  FIN-09 lot created in the farm store at USD 0.80/kg

TRANSFER TO KITCHEN
  200 kg transferred

  Journal:
     Dr Kitchen Inventory               160.00
       Cr Farm Inventory                  160.00
  FIN-09: farm store lot depletes, kitchen store lot created at USD 0.80/kg

CONSUMPTION IN A MEAL
  BRD-04 issues 45 kg to a service
     Dr Catering Expense (boarding)      36.00
       Cr Kitchen Inventory                36.00

REPORTING
  Farm cost centre:      carries the production cost
  Boarding cost centre:  carries USD 0.80/kg, its true food cost
  Savings report:        market price USD 1.20/kg × 200 kg = USD 240
                         internal cost                      USD 160
                         saving                             USD  80
```

**Neither cost centre is subsidised invisibly.** The farm's P&L shows what it costs to grow; boarding's shows what it costs to feed. The board gets a defensible answer on whether the farm earns its keep.

### 4. Business rules

| ID | Rule |
|---|---|
| `BR-OPS-03-001` | Each production unit is its own cost centre with its own profit and loss. |
| `BR-OPS-03-002` | Crop cycle inputs draw from the farm store via `FIN-09` requisitions, costed FIFO. |
| `BR-OPS-03-003` | Farm labour cost allocates to cycles from `PPL-04` records or a configured daily rate for casual labour. |
| `BR-OPS-03-004` | Overhead apportions to cycles on a configured basis — hectares, or a fixed rate per cycle. |
| `BR-OPS-03-005` ⭐ | Cost per kilogram derives from total cycle cost divided by actual yield. This is the internal transfer price. |
| `BR-OPS-03-006` | Harvest creates a `FIN-09` stock lot at cost per kilogram in the farm store, posting a real journal. |
| `BR-OPS-03-007` | A failed crop cycle writes its accumulated cost to a crop failure expense account, with the reason recorded. Failure is normal in agriculture and must be visible, not hidden in general overhead. |
| `BR-OPS-03-008` ⭐ | Internal transfer to the kitchen moves stock between stores at internal cost and posts a journal. It is never a free transfer. |
| `BR-OPS-03-009` | Transfers record market price where known, so the savings report can quantify the farm's contribution. |
| `BR-OPS-03-010` | Livestock may be tracked individually (cattle) or at herd level (poultry). Both are supported on the same table. |
| `BR-OPS-03-011` | Breeding stock above the capitalisation threshold becomes a `FIN-10` fixed asset; stock held for slaughter is inventory. |
| `BR-OPS-03-012` ⭐ | Veterinary treatments record withdrawal periods. **Milk or meat from an animal within its withdrawal period cannot be transferred to the kitchen.** The block is hard, and this is a food-safety control. |
| `BR-OPS-03-013` | Dipping and vaccination schedules generate reminders; overdue records alert the farm manager. |
| `BR-OPS-03-014` | Livestock deaths are recorded with a cause and post a write-off. Unexplained losses beyond the configured rate escalate. |
| `BR-OPS-03-015` | Daily production (milk, eggs) is recorded per unit per day and per-head yield is trended. |
| `BR-OPS-03-016` | External sales raise a `FIN-04` receipt and are 🇿🇼 fiscalised through `FIN-13`, since farm produce sales are a commercial supply. |
| `BR-OPS-03-017` | Per-unit profitability reports total cost, kitchen transfer value, external sales, and net position per production unit per term. |
| `BR-OPS-03-018` | Irrigation draws are recorded against `OPS-04` water sources, so borehole load from the farm is visible. |

### 5. Screens · API · Settings

| Screen | Component | Permission |
|---|---|---|
| Production units | `Ops\Farm\Units` | `farm.manage` |
| Fields | `Ops\Farm\Fields` | `farm.manage` |
| Crop cycles | `Ops\Farm\Cycles` | `farm.crop.manage` — plan, inputs, costs, yield |
| **Input recording** | `Ops\Farm\Inputs` | `farm.record` — mobile, requisition from farm store |
| Harvest | `Ops\Farm\Harvest` | `farm.record` — quantity, grade, destination, cost per kg shown |
| Livestock register | `Ops\Farm\Livestock` | `farm.livestock.manage` |
| **Livestock events** | `Ops\Farm\LivestockEvents` | `farm.record` — mobile, withdrawal period captured |
| Production log | `Ops\Farm\Production` | `farm.record` — daily milk, eggs |
| **Kitchen transfers** | `Ops\Farm\Transfers` | `farm.transfer` ⭐ — withdrawal check enforced |
| Sales | `Ops\Farm\Sales` | `farm.sales.manage` |
| **Profitability** | `Ops\Farm\Profitability` | `farm.report.view` ⭐ — per unit, cost vs transfer value vs sales |
| Savings report | `Ops\Farm\Savings` | `farm.report.view` — internal cost vs market price |

```
POST /api/v1/farm/inputs                mobile field recording
POST /api/v1/farm/harvest
POST /api/v1/farm/livestock-events      mobile, with withdrawal period
POST /api/v1/farm/production            daily milk and egg entry
POST /api/v1/farm/transfers             to kitchen
```

| Setting | Type | Default |
|---|---|---|
| `farm.overhead_allocation_basis` | enum | `hectares` |
| `farm.casual_labour_daily_rate_minor` | int | school-set |
| `farm.capitalise_breeding_stock` | bool | `true` |
| `farm.livestock_mortality_alert_percent` | int | `5` |
| `farm.enforce_withdrawal_periods` | bool | `true` (**locked**) |
| `farm.record_market_prices` | bool | `true` |

Events: `CropCyclePlanted` · `HarvestRecorded` · `CropCycleFailed` ⚠ · `ProduceTransferredToKitchen` ⭐ (→ `BRD-04`) · `WithdrawalPeriodBlocked` ⚠ · `LivestockDeathRecorded` ⚠ · `MortalityRateExceeded` ⚠ · `FarmSaleRecorded` (→ `FIN-13`) · `TreatmentOverdue`

### 6. Acceptance criteria

```gherkin
AC-OPS-03-001
  Given a crop cycle costing USD 1,440 yielding 1,800 kg
  Then cost per kg is USD 0.80
  And harvest creates a FIN-09 farm store lot at that cost

AC-OPS-03-002
  Given 200 kg is transferred to the kitchen
  Then a journal posts Dr Kitchen Inventory / Cr Farm Inventory for USD 160
  And a kitchen store lot is created at USD 0.80/kg
  And BRD-04 costs meals using it

AC-OPS-03-003
  Given a cow was treated with a 7-day milk withdrawal on 2026-09-01
  When milk transfer to the kitchen is attempted on 2026-09-05
  Then it is blocked
  And the block names the withdrawal period and its end date

AC-OPS-03-004
  Given a crop cycle fails
  Then its accumulated cost writes off to crop failure expense
  And the reason is recorded
  And it is not absorbed into general overhead

AC-OPS-03-005
  Given the farm transferred produce worth USD 4,800 at internal cost
  And market price for the same volume is USD 7,200
  Then the savings report shows USD 2,400 saved

AC-OPS-03-006
  Given surplus vegetables are sold externally
  Then a FIN-04 receipt is raised
  And the sale is fiscalised through FIN-13

AC-OPS-03-007
  Given poultry mortality exceeds 5% in a cycle
  Then the farm manager and bursar are alerted
```

---

# OPS-05 · Facilities Booking & External Hire

### 1. Scope

Bookable resource register, internal booking with timetable clash detection, external hire with quotation, contract, deposit and fiscalised invoicing, setup and cleaning task generation, utilisation and revenue reporting.

### 2. Data model

```sql
bookable_resources
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
venue_id                BIGINT       NULL FK    -- ACA-03 venues
vehicle_id              BIGINT       NULL FK    -- OPS-01
code                    VARCHAR(20)  NOT NULL
name                    VARCHAR(150) NOT NULL
resource_type           VARCHAR(30)  NOT NULL   -- hall|field|pool|laboratory|
                                                -- boardroom|ict_lab|vehicle|
                                                -- equipment
capacity                SMALLINT     NULL
is_externally_hireable  TINYINT(1)   NOT NULL DEFAULT 0
hire_rate_minor         BIGINT       NULL
hire_rate_unit          VARCHAR(20)  NULL       -- hour|half_day|day|event
hire_currency           CHAR(3)      NULL
deposit_minor           BIGINT       NULL
requires_setup_minutes  SMALLINT     NOT NULL DEFAULT 0
requires_cleaning_minutes SMALLINT   NOT NULL DEFAULT 0
booking_lead_time_hours SMALLINT     NOT NULL DEFAULT 24
cost_centre_id          BIGINT       FK
income_account_id       BIGINT       NULL FK
is_active               TINYINT(1)   NOT NULL DEFAULT 1
  UNIQUE (school_id, code)

resource_bookings
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
term_id                 BIGINT       FK
booking_number          VARCHAR(40)  NOT NULL
resource_id             BIGINT       FK INDEX
booking_type            VARCHAR(20)  NOT NULL   -- internal|external
purpose                 VARCHAR(255) NOT NULL
starts_at               TIMESTAMP    NOT NULL
ends_at                 TIMESTAMP    NOT NULL
setup_from              TIMESTAMP    NULL       -- resource blocked from here
cleanup_until           TIMESTAMP    NULL       -- to here
expected_attendance     SMALLINT     NULL
-- internal
requested_by_staff_id   BIGINT       NULL FK
department_id           BIGINT       NULL FK
-- external
hirer_name              VARCHAR(200) NULL
hirer_contact           VARCHAR(80)  NULL
hirer_organisation      VARCHAR(200) NULL
hire_amount_minor       BIGINT       NULL
deposit_amount_minor    BIGINT       NULL
deposit_receipt_id      BIGINT       NULL FK    -- FIN-04
invoice_id              BIGINT       NULL FK    -- FIN-03
contract_file_id        BIGINT       NULL FK
deposit_refunded        TINYINT(1)   NULL
damage_deducted_minor   BIGINT       NULL
-- lifecycle
status                  VARCHAR(20)  NOT NULL   -- requested|approved|confirmed|
                                                -- in_progress|completed|
                                                -- cancelled|rejected
approval_request_id     BIGINT       NULL FK
recurrence_rule         VARCHAR(120) NULL       -- iCal RRULE for repeats
parent_booking_id       BIGINT       NULL FK
setup_work_order_id     BIGINT       NULL FK    -- OPS-02
cleanup_work_order_id   BIGINT       NULL FK
condition_before_notes  TEXT         NULL
condition_after_notes   TEXT         NULL
  UNIQUE (school_id, booking_number)
  INDEX  (school_id, resource_id, starts_at, ends_at)
  INDEX  (school_id, status, starts_at)
```

### 3. Business rules

| ID | Rule |
|---|---|
| `BR-OPS-05-001` | Bookings check for overlap against other bookings **and** against the published timetable (`ACA-03`) where the resource is a teaching venue. Teaching always wins. |
| `BR-OPS-05-002` | Setup and cleaning windows block the resource. A hall needing 90 minutes of setup is unavailable for that period, not merely noted. |
| `BR-OPS-05-003` | External hire requires approval, a signed contract, and a deposit before confirmation. |
| `BR-OPS-05-004` | 🇿🇼 Hire income is a commercial supply: the invoice is fiscalised through `FIN-13`. |
| `BR-OPS-05-005` | Deposits are refundable liabilities, not income, until the booking completes and condition is verified. |
| `BR-OPS-05-006` | Damage found after an external hire deducts from the deposit with a recorded assessment; the balance refunds. |
| `BR-OPS-05-007` | Confirmed bookings generate setup and cleaning work orders in `OPS-02`. |
| `BR-OPS-05-008` | Recurring bookings expand to individual instances so each can be cancelled independently. |
| `BR-OPS-05-009` | Utilisation and hire revenue report per resource per term. |

### 4. Screens · API

| Screen | Component | Permission |
|---|---|---|
| Resource calendar | `Ops\Facilities\Calendar` | `facilities.view` — week view, all resources, timetable overlay |
| Booking request | `Ops\Facilities\Request` | `facilities.book` — clash shown live |
| Approvals | `Ops\Facilities\Approvals` | `facilities.approve` |
| External hire | `Ops\Facilities\Hire` | `facilities.hire.manage` — quotation, contract, deposit, invoice |
| Utilisation | `Ops\Facilities\Utilisation` | `facilities.report.view` |

```
GET  /api/v1/facilities/availability     ?resource=&from=&to=
POST /api/v1/facilities/bookings         staff request
```

### 5. Acceptance criteria

```gherkin
AC-OPS-05-001
  Given the hall is timetabled for a Form 4 lesson at 10:00
  When an external hire is requested for 09:00 to 12:00
  Then the clash is reported and the booking is refused

AC-OPS-05-002
  Given a hall requires 90 minutes setup
  When it is booked for 14:00
  Then it is unavailable from 12:30

AC-OPS-05-003
  Given an external hire deposit of USD 300 is received
  Then it posts to refundable deposits, not to income
  And refunds on completion less any assessed damage

AC-OPS-05-004
  Given an external hire invoice is raised
  Then it is routed to FIN-13 for fiscalisation
```

---

# OPS-06 · Security, Gate & Access Control

### 1. Scope

Contractor site access and induction, security patrols with checkpoint scanning, the occurrence book, lost property, key and access card management, and the emergency muster roll.

**Boundary.** The gate terminal for learner collection and visitor sign-in is `BRD-03`. This module covers the security operation around it.

### 2. Data model

```sql
contractors
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
supplier_id             BIGINT       NULL FK    -- FIN-08
company_name            VARCHAR(200) NOT NULL
contact_person          VARCHAR(150) NULL
phone                   VARCHAR(30)  NULL
work_type               VARCHAR(80)  NULL
insurance_expires_on    DATE         NULL
insurance_file_id       BIGINT       NULL FK
safety_induction_on     DATE         NULL
induction_valid_until   DATE         NULL
police_clearance_on     DATE         NULL       -- ⭐ working near children
status                  VARCHAR(20)  NOT NULL   -- pending|approved|
                                                -- suspended|expired
approved_by             BIGINT       NULL FK
  INDEX (school_id, status, induction_valid_until)

contractor_workers
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK
contractor_id           BIGINT       FK INDEX
full_name               VARCHAR(150) NOT NULL
id_number               VARCHAR(40)  NULL       -- ENCRYPTED
photo_file_id           BIGINT       NULL FK
induction_completed_on  DATE         NULL
police_clearance_on     DATE         NULL
is_cleared              TINYINT(1)   NOT NULL DEFAULT 0
badge_number            VARCHAR(30)  NULL

patrol_routes
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
code                    VARCHAR(20)  NOT NULL
name                    VARCHAR(120) NOT NULL
checkpoint_ids          JSON         NOT NULL   -- BRD-02 movement_checkpoints
expected_duration_min   SMALLINT     NULL
frequency               VARCHAR(30)  NOT NULL   -- hourly|two_hourly|
                                                -- shift_start|random
applies_at_times        JSON         NULL
is_active               TINYINT(1)   NOT NULL DEFAULT 1
  UNIQUE (school_id, code)

patrols
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
patrol_route_id         BIGINT       FK
guard_staff_id          BIGINT       FK
scheduled_at            TIMESTAMP    NOT NULL
started_at              TIMESTAMP    NULL
completed_at            TIMESTAMP    NULL
checkpoints_expected    SMALLINT     NOT NULL
checkpoints_scanned     SMALLINT     NOT NULL DEFAULT 0
status                  VARCHAR(20)  NOT NULL   -- scheduled|in_progress|
                                                -- completed|incomplete|missed
findings                TEXT         NULL
  INDEX (school_id, scheduled_at, status)

patrol_scans
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK
patrol_id               BIGINT       FK INDEX
checkpoint_id           BIGINT       FK
scanned_at              TIMESTAMP    NOT NULL
method                  VARCHAR(20)  NOT NULL   -- qr|nfc|manual
note                    VARCHAR(255) NULL
photo_file_id           BIGINT       NULL FK

occurrence_book                       -- APPEND-ONLY ⭐
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
entry_number            BIGINT       NOT NULL   -- gapless per school
occurred_at             TIMESTAMP    NOT NULL
recorded_at             TIMESTAMP    NOT NULL
shift                   VARCHAR(20)  NULL       -- day|night
category                VARCHAR(40)  NOT NULL   -- observation|incident|
                                                -- handover|visitor|
                                                -- vehicle|intrusion|
                                                -- fire|medical|other
description             TEXT         NOT NULL
location                VARCHAR(150) NULL
persons_involved        VARCHAR(500) NULL
action_taken            TEXT         NULL
escalated_to            BIGINT       NULL FK
cctv_reference          VARCHAR(120) NULL
photo_file_ids          JSON         NULL
recorded_by             BIGINT       FK → users.id
  UNIQUE (school_id, entry_number)
  INDEX  (school_id, occurred_at)
  INDEX  (school_id, category, occurred_at)
  -- No UPDATE. No DELETE. Corrections are new entries referencing the original.

keys_and_cards
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
identifier              VARCHAR(60)  NOT NULL
item_type               VARCHAR(20)  NOT NULL   -- key|access_card|fob|padlock
description             VARCHAR(200) NOT NULL
opens_location          VARCHAR(200) NULL
is_master               TINYINT(1)   NOT NULL DEFAULT 0
status                  VARCHAR(20)  NOT NULL   -- available|issued|lost|
                                                -- damaged|retired
  UNIQUE (school_id, identifier)

key_issues
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK
key_id                  BIGINT       FK INDEX
issued_to_staff_id      BIGINT       NULL FK
issued_to_contractor_id BIGINT       NULL FK
issued_at               TIMESTAMP    NOT NULL
issued_by               BIGINT       FK → users.id
due_back_on             DATE         NULL
returned_at             TIMESTAMP    NULL
received_by             BIGINT       NULL FK
status                  VARCHAR(20)  NOT NULL   -- issued|returned|overdue|lost

lost_property
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
found_on                DATE         NOT NULL
description             VARCHAR(255) NOT NULL
found_location          VARCHAR(150) NULL
found_by                BIGINT       NULL FK
photo_file_id           BIGINT       NULL FK
status                  VARCHAR(20)  NOT NULL   -- held|claimed|disposed|donated
claimed_by_student_id   BIGINT       NULL FK
claimed_at              TIMESTAMP    NULL
disposal_on             DATE         NULL

emergency_drills
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
term_id                 BIGINT       FK
drill_type              VARCHAR(30)  NOT NULL   -- fire|evacuation|lockdown|
                                                -- medical|real_incident
conducted_at            TIMESTAMP    NOT NULL
is_announced            TINYINT(1)   NOT NULL DEFAULT 0
expected_headcount      SMALLINT     NOT NULL
mustered_headcount      SMALLINT     NULL
unaccounted_count       SMALLINT     NULL
evacuation_seconds      INT          NULL
assembly_points         JSON         NULL
findings                TEXT         NULL
actions_required        TEXT         NULL
conducted_by            BIGINT       FK → users.id
  INDEX (school_id, term_id, conducted_at)
```

### 3. ⭐ The emergency muster roll

The single most operationally important screen in this module, and it must work when nothing else does.

```
MUSTER TRIGGERED (drill or real)
  │
  ▼
System assembles the expected on-site population:
  ├─ Boarders            ← BRD-01 active allocations, minus BRD-03 exeats
  ├─ Day scholars        ← ACA-04 attendance marked present today
  ├─ Staff               ← PPL-04 active, minus approved leave
  ├─ Visitors            ← BRD-03 signed in, not signed out
  ├─ Contractor workers  ← signed in, not signed out
  └─ In sick bay         ← BRD-06 current admissions (flagged: may need assistance)
  │
  ▼
PER ASSEMBLY POINT list, printable and available offline on mobile
  Marshals tap present as people arrive
  │
  ▼
UNACCOUNTED LIST updates live, sorted by category
  Sick bay occupants shown first — they may be unable to self-evacuate
```

| # | Rule |
|---|---|
| The muster list is cached on every marshal's device the moment a drill is scheduled, and refreshes every 15 minutes during school hours regardless. **A fire is not the moment to discover the network is down.** |
| Sick bay and mobility-impaired learners appear at the top of every list with an assistance flag. |
| Evacuation time and unaccounted count are recorded for every drill and trended. |
| A real incident uses the identical flow, marked `real_incident`, and the record is retained permanently. |

### 4. Business rules

| ID | Rule |
|---|---|
| `BR-OPS-06-001` | Contractors require approval, valid insurance, and completed safety induction before site access. |
| `BR-OPS-06-002` ⭐ | Contractor workers working unsupervised on a campus with children require police clearance on file. Absence blocks gate access. |
| `BR-OPS-06-003` | The occurrence book is append-only with gapless numbering. Corrections are new entries referencing the original. |
| `BR-OPS-06-004` | Patrols with fewer scans than expected checkpoints are marked incomplete and reported. |
| `BR-OPS-06-005` | Missed patrols alert the security supervisor. |
| `BR-OPS-06-006` | Master keys require higher authority to issue and are reported when outstanding. |
| `BR-OPS-06-007` | Keys not returned by their due date alert the issuer and the security supervisor. |
| `BR-OPS-06-008` ⭐ | The muster roll assembles from live data across `BRD-01`, `BRD-03`, `BRD-06`, `ACA-04` and `PPL-04`, and is cached offline on marshal devices. |
| `BR-OPS-06-009` | Sick bay occupants and learners with mobility requirements are flagged at the top of every muster list. |
| `BR-OPS-06-010` | Drills are recorded with headcount, unaccounted count and evacuation time, and trended term on term. |
| `BR-OPS-06-011` | An unaccounted person at the end of a muster opens a `BRD-02` missing-learner incident where the person is a learner. |
| `BR-OPS-06-012` | Occurrence book entries in the intrusion, fire and medical categories notify the head immediately. |

### 5. Screens · API

| Screen | Component | Permission |
|---|---|---|
| **Muster roll** | `Ops\Security\Muster` | `security.muster` ⭐ — offline-capable, per assembly point, live unaccounted |
| Occurrence book | `Ops\Security\OccurrenceBook` | `security.occurrence.record` |
| Patrols | `Ops\Security\Patrols` | `security.patrol.manage` — schedule, scans, incomplete |
| Contractors | `Ops\Security\Contractors` | `security.contractor.manage` — induction, clearance, expiry |
| Keys and cards | `Ops\Security\Keys` | `security.key.manage` — issued, overdue, masters outstanding |
| Lost property | `Ops\Security\LostProperty` | `security.manage` |
| Drills | `Ops\Security\Drills` | `security.drill.manage` — record, trend, actions |

```
GET  /api/v1/security/muster            ⭐ offline-cached; refreshes every 15 min
POST /api/v1/security/muster/{ulid}/mark
POST /api/v1/security/occurrences       mobile entry with photo
POST /api/v1/security/patrols/{ulid}/scan
```

### 6. Acceptance criteria

```gherkin
AC-OPS-06-001
  Given a fire drill is triggered
  Then the muster list includes boarders minus exeats, day scholars marked
       present, staff not on leave, signed-in visitors and contractor workers
  And sick bay occupants appear first with an assistance flag

AC-OPS-06-002
  Given the network is unavailable during a drill
  Then the cached muster list is available on marshal devices
  And marking works offline and syncs on reconnect

AC-OPS-06-003
  Given a learner is unaccounted for at the end of a muster
  Then a BRD-02 missing-learner incident opens

AC-OPS-06-004
  Given a contractor worker has no police clearance on file
  When gate access is attempted
  Then it is refused

AC-OPS-06-005
  Given an occurrence book entry exists
  When any user attempts to edit or delete it
  Then it is refused
  And a correcting entry referencing the original is the only route
```

---

# OPS-07 · Sport, Houses & Co-curricular

### 1. Scope

House registry and inter-house competition, sports and club registry with fee linkage, team selection, fixtures with venue and transport, results and standings, colours and awards, equipment issue, event management.

### 2. Data model

```sql
activities                            -- sports, clubs, societies
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
code                    VARCHAR(20)  NOT NULL
name                    VARCHAR(120) NOT NULL
activity_type           VARCHAR(20)  NOT NULL   -- sport|club|society|
                                                -- cultural|service
season                  VARCHAR(20)  NULL       -- term_1|term_2|term_3|year_round
gender_scope            VARCHAR(10)  NOT NULL DEFAULT 'both'
min_grade_ordinal       SMALLINT     NULL
max_grade_ordinal       SMALLINT     NULL
coach_staff_id          BIGINT       NULL FK
fee_component_id        BIGINT       NULL FK    -- FIN-02 activity fee
requires_medical_clearance TINYINT(1) NOT NULL DEFAULT 0
requires_guardian_consent TINYINT(1) NOT NULL DEFAULT 1
max_participants        SMALLINT     NULL
venue_id                BIGINT       NULL FK
is_active               TINYINT(1)   NOT NULL DEFAULT 1
  UNIQUE (school_id, code)

activity_memberships
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
academic_year_id        BIGINT       FK
term_id                 BIGINT       FK INDEX
activity_id             BIGINT       FK INDEX
student_id              BIGINT       FK INDEX
role                    VARCHAR(30)  NULL       -- member|captain|
                                                -- vice_captain|secretary
joined_on               DATE         NOT NULL
left_on                 DATE         NULL
consent_received        TINYINT(1)   NOT NULL DEFAULT 0
medical_cleared         TINYINT(1)   NULL       -- BRD-06
billing_status          VARCHAR(20)  NOT NULL DEFAULT 'pending'
fee_line_id             BIGINT       NULL FK
status                  VARCHAR(20)  NOT NULL   -- active|withdrawn|suspended
  UNIQUE (school_id, term_id, activity_id, student_id)

teams
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
academic_year_id        BIGINT       FK
activity_id             BIGINT       FK INDEX
name                    VARCHAR(120) NOT NULL   -- '1st XI','U16 A'
age_group               VARCHAR(20)  NULL
level                   VARCHAR(20)  NULL       -- first|second|junior|development
coach_staff_id          BIGINT       NULL FK
captain_student_id      BIGINT       NULL FK
is_active               TINYINT(1)   NOT NULL DEFAULT 1

fixtures
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
term_id                 BIGINT       FK
team_id                 BIGINT       FK INDEX
opponent                VARCHAR(200) NOT NULL
fixture_type            VARCHAR(20)  NOT NULL   -- friendly|league|cup|
                                                -- tournament|inter_house
venue_type              VARCHAR(10)  NOT NULL   -- home|away|neutral
venue_id                BIGINT       NULL FK    -- home
venue_name              VARCHAR(200) NULL       -- away
fixture_date            DATE         NOT NULL
start_time              TIME         NULL
departure_time          TIME         NULL
return_time             TIME         NULL
trip_id                 BIGINT       NULL FK    -- ⭐ OPS-01
booking_id              BIGINT       NULL FK    -- OPS-05 home venue
squad_student_ids       JSON         NULL       -- ⭐ drives BRD-02 'fixture'
staff_ids               JSON         NULL
result                  VARCHAR(20)  NULL       -- won|lost|drew|
                                                -- cancelled|postponed
score_for               VARCHAR(30)  NULL
score_against           VARCHAR(30)  NULL
match_report            TEXT         NULL
status                  VARCHAR(20)  NOT NULL   -- scheduled|confirmed|
                                                -- in_progress|completed|
                                                -- cancelled|postponed
guardians_notified_at   TIMESTAMP    NULL
  INDEX (school_id, fixture_date, status)
  INDEX (school_id, team_id, fixture_date)

house_competitions
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
academic_year_id        BIGINT       FK
name                    VARCHAR(150) NOT NULL   -- 'Inter-House Athletics'
competition_type        VARCHAR(30)  NOT NULL   -- sport|academic|cultural|
                                                -- conduct|attendance|
                                                -- room_inspection
held_on                 DATE         NULL
points_scheme           JSON         NOT NULL   -- {1st:10, 2nd:7, 3rd:5}
weight                  DECIMAL(5,2) NOT NULL DEFAULT 1
status                  VARCHAR(20)  NOT NULL

house_points
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
school_id               BIGINT       FK INDEX
academic_year_id        BIGINT       FK
term_id                 BIGINT       FK
house_id                BIGINT       FK INDEX
competition_id          BIGINT       NULL FK
source_type             VARCHAR(30)  NOT NULL   -- competition|behaviour|
                                                -- inspection|academic|manual
source_id               BIGINT       NULL
points                  DECIMAL(8,2) NOT NULL
reason                  VARCHAR(255) NULL
awarded_at              TIMESTAMP    NOT NULL
awarded_by              BIGINT       FK → users.id
  INDEX (school_id, academic_year_id, house_id)

awards                                -- colours, honours
──────────────────────────────────────────────────────────────────
id                      BIGINT PK
ulid                    CHAR(26)     UNIQUE
school_id               BIGINT       FK INDEX
academic_year_id        BIGINT       FK
student_id              BIGINT       FK INDEX
award_type              VARCHAR(30)  NOT NULL   -- half_colours|full_colours|
                                                -- honours|certificate|
                                                -- trophy|scholarship
activity_id             BIGINT       NULL FK
title                   VARCHAR(200) NOT NULL
citation                TEXT         NULL
awarded_on              DATE         NOT NULL
awarded_by              BIGINT       FK → users.id
appears_on_report_card  TINYINT(1)   NOT NULL DEFAULT 1
appears_on_transcript   TINYINT(1)   NOT NULL DEFAULT 1
  INDEX (school_id, student_id, academic_year_id)
```

### 3. Business rules

| ID | Rule |
|---|---|
| `BR-OPS-07-001` | Activity membership requires guardian consent where the activity is flagged, captured through the portal. |
| `BR-OPS-07-002` | Activities flagged `requires_medical_clearance` check `BRD-06` for conditions affecting physical activity. Uncleared learners cannot be selected. |
| `BR-OPS-07-003` | Activities with a fee component bill through `FIN-02` on membership, pro-rated for mid-term joiners. |
| `BR-OPS-07-004` | Membership beyond `max_participants` requires override with a reason. |
| `BR-OPS-07-005` ⭐ | A confirmed fixture's squad sets roll call status `fixture` in `BRD-02` for the fixture window. |
| `BR-OPS-07-006` | Away fixtures create an `OPS-01` trip with the squad as passengers; home fixtures create an `OPS-05` venue booking. |
| `BR-OPS-07-007` | Selection notifies the learner and their guardians with venue, times and requirements. |
| `BR-OPS-07-008` | House points aggregate from competitions, behaviour records (`BRD-07`), room inspections (`BRD-01`) and academic results, each with its configured weight. |
| `BR-OPS-07-009` | The house leaderboard is live and visible to learners and guardians where enabled. |
| `BR-OPS-07-010` | Awards appear on the report card and transcript where flagged, and carry into the alumni record on graduation. |
| `BR-OPS-07-011` | Equipment issued for an activity is tracked through `FIN-09` and must be returned at season end. |
| `BR-OPS-07-012` | A learner injured at a fixture raises a `BRD-06` health incident linked to the fixture. |

### 4. Screens · API

| Screen | Component | Permission |
|---|---|---|
| Activities | `Ops\Activities\Index` | `activities.manage` |
| Membership | `Ops\Activities\Membership` | `activities.manage` — consent and medical clearance status |
| Teams | `Ops\Sport\Teams` | `activities.team.manage` |
| Fixtures | `Ops\Sport\Fixtures` | `activities.fixture.manage` — venue, transport, squad |
| Squad selection | `Ops\Sport\Selection` | `activities.team.manage` — medical clearance shown |
| Results | `Ops\Sport\Results` | `activities.fixture.manage` |
| **House leaderboard** | `Ops\Houses\Leaderboard` | `activities.view` — live, by competition and total |
| Awards | `Ops\Awards\Index` | `activities.award.manage` |

```
GET  /api/v1/activities                    learner: available, my memberships
POST /api/v1/activities/{ulid}/join        guardian consent flow
GET  /api/v1/fixtures/upcoming             learner and guardian
GET  /api/v1/houses/leaderboard
GET  /api/v1/me/awards
```

### 5. Acceptance criteria

```gherkin
AC-OPS-07-001
  Given a learner has a medical condition affecting physical activity
  When selection for a rugby squad is attempted
  Then it is blocked pending medical clearance

AC-OPS-07-002
  Given a squad is confirmed for an away fixture
  Then an OPS-01 trip is created with the squad as passengers
  And their BRD-02 roll status is 'fixture' for the fixture window

AC-OPS-07-003
  Given a learner joins a club with an activity fee mid-term
  Then FIN-02 raises a pro-rated charge

AC-OPS-07-004
  Given house points accrue from athletics, room inspections and behaviour
  Then the leaderboard reflects each source at its configured weight

AC-OPS-07-005
  Given a learner is injured during a fixture
  Then a BRD-06 health incident is raised linked to the fixture
  And the guardian is notified
```

---

## Part 3 — Book H2 Build Sequence

| Sprint | Deliverable | Definition of done |
|---|---|---|
| **H2-1** | `OPS-02` assets, fault reports, triage | Safety-flagged reports jump the queue |
| **H2-2** | `OPS-02` work orders, parts from stores, labour costing | `FIN-09` requisition path working |
| **H2-3** | `OPS-02` preventive schedules, SLA, capital projects | Usage-based triggers ready for `OPS-01`/`OPS-04` |
| **H2-4** | `OPS-01` fleet, compliance, drivers 🇿🇼 | Expired compliance blocks trip assignment |
| **H2-5** | `OPS-01` routes, zones, learner assignment | Zone drives `FIN-02` transport fee |
| **H2-6** | `OPS-01` trips, manifests, boarding capture | Offline manifest; guardian notification |
| **H2-7** | `OPS-01` fuel logging and anomaly detection ⭐ | Rolling 30-day check catches cumulative skimming |
| **H2-8** | `OPS-04` meters, readings, prepaid tokens 🇿🇼 ⭐ | Uncredited tokens alert; expense on consumption |
| **H2-9** | `OPS-04` generators, solar, water | Hours feed `FIN-10` and `OPS-02` |
| **H2-10** | `OPS-04` energy dashboard, cost of outage | Board-ready outage cost figure |
| **H2-11** | `OPS-03` units, fields, crop cycles, inputs | Cost per kg computes from real inputs |
| **H2-12** | `OPS-03` harvest, livestock, production | Withdrawal periods enforced |
| **H2-13** | `OPS-03` kitchen transfer ⭐ | **Book F interface closed; `AC-OPS-03-002` green** |
| **H2-14** | `OPS-05` facilities and hire | Timetable clash detection; fiscalised hire |
| **H2-15** | `OPS-06` security, occurrence book, muster ⭐ | Offline muster proven |
| **H2-16** | `OPS-07` activities, fixtures, houses, awards | Fixture status feeds `BRD-02` |

---

## Part 4 — Book H2 Acceptance Gate

### Interface closure

- [ ] Farm produce transfers to the kitchen at internal cost with a posted journal; `BRD-04` costs meals from it
- [ ] Vehicle odometer readings feed `FIN-10` units-of-production depreciation
- [ ] Generator hours feed `FIN-10` and `OPS-02` service schedules
- [ ] Work order parts issue through `FIN-09` requisitions
- [ ] `BRD-01` hostel damage raises linked work orders
- [ ] Fixture squads set `BRD-02` roll status `fixture`
- [ ] Emergency muster assembles from `BRD-01`, `BRD-03`, `BRD-06`, `ACA-04`, `PPL-04`

### 🇿🇼 Zimbabwe-specific controls

- [ ] All seven vehicle compliance types tracked with independent expiry
- [ ] Expired compliance blocks trip assignment with the item named
- [ ] Prepaid tokens tracked from purchase to confirmed credit; uncredited alerts
- [ ] Electricity expense recognises on consumption, not purchase
- [ ] Monthly token-versus-consumption reconciliation runs and reports variance
- [ ] Generator runs separable by reason so load-shedding cost is isolable
- [ ] Livestock withdrawal periods block kitchen transfer

### Loss prevention

- [ ] Fuel anomaly detection catches both single-event and rolling 30-day patterns
- [ ] Anomalies require a recorded explanation and cannot be dismissed
- [ ] Occurrence book is append-only with gapless numbering
- [ ] Master key issues require higher authority and are reported when outstanding

### Safety

- [ ] Safety-flagged faults jump triage and alert immediately
- [ ] Contractor workers without police clearance are refused gate access
- [ ] Muster roll caches offline and refreshes every 15 minutes during school hours
- [ ] Sick bay and mobility-impaired learners appear first on every muster list
- [ ] An unaccounted learner at muster end opens a `BRD-02` incident
- [ ] Water quality failure alerts the nurse and catering manager immediately

### Financial integrity

- [ ] Every farm harvest, transfer and sale posts a journal
- [ ] Crop failure writes off to a distinct account with a recorded reason
- [ ] Utility costs allocate to cost centres and appear in `FIN-11` variance
- [ ] Hire deposits are liabilities until completion; damage deducts with assessment
- [ ] Farm sales and facility hire route to `FIN-13` fiscalisation

### Quality

- [ ] Coverage ≥ 85%; `OPS-06` muster ≥ 95%
- [ ] Tenancy isolation suite passes for every model in this book
- [ ] Offline capability verified for manifests, muster, meter readings and field recording

---

## Appendix A — Interfaces Closed

| Interface | Owner | Consumer | Status |
|---|---|---|---|
| Farm-to-kitchen transfer | `OPS-03` | `BRD-04` | ✅ closed |
| Work order parts issue | `OPS-02` | `FIN-09` | ✅ |
| Vehicle units consumed | `OPS-01` | `FIN-10`, `OPS-02` | ✅ |
| Generator hours | `OPS-04` | `FIN-10`, `OPS-02` | ✅ |
| Hostel damage → work order | `BRD-01` | `OPS-02` | ✅ |
| Fixture roll status | `OPS-07` | `BRD-02` | ✅ |
| Emergency muster roll | `OPS-06` | — | ✅ |
| Farm sales, facility hire → fiscalisation | `OPS-03`, `OPS-05` | `FIN-13` | Book H3 |
| Transport, activity, damage charges | all | `FIN-02` | ✅ |

---

## Appendix B — Remaining Books

| Book | Domain | Modules |
|---|---|---|
| **H3** | Payroll, Fiscalisation & Compliance | `PPL-05` 🇿🇼 · `FIN-12` · `FIN-13` 🇿🇼 · `FIN-14` · `CMP-01`–`CMP-04` |
| **I** | Communication & Portals | `COM-01` → `COM-08` |
| **J** | Intelligence & SaaS Control | `INT-01`–`INT-04` · `SAA-01`–`SAA-03` |

Book H3 closes the last regulatory surface: ZIMRA fiscalisation for every commercial receipt raised across the platform, payroll with the full statutory stack, and the ZIMSEC and MoPSE interfaces opened in Book E.

---

*End of Volume 2, Book H2.*
