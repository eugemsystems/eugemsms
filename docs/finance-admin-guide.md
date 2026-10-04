# Finance module — a plain-English guide for bursars

This is a working guide to every screen under **Finance** in the admin panel: what
each screen is for, when you'd actually open it, and what to put in every field —
including the ones that aren't obvious just from the label. It's written for a
bursar or school administrator, not a developer, so accounting and system terms
are explained the first time they come up.

The guide is organised the same way the **Finance** menu on the left is grouped,
so you can follow along section by section as you work. This file is updated as
new Finance screens are finished — if a screen you're looking for isn't here yet,
it hasn't been built.

**Two ideas worth understanding before anything else**, because they come up on
almost every screen below:

- **Debit and credit.** Every financial transaction in the system moves money
  from one "account" to another — always in matching pairs. A debit (`DR`)
  increases what an asset or expense account holds; a credit (`CR`) increases
  what a liability, income, or equity account holds. For every transaction the
  total debits must equal the total credits — that's what "the books balance"
  means. You'll see `DR`/`CR` on the Journals and Chart of Accounts screens.
- **Control account / subledger.** A "control account" (like *Fee Debtors*) is a
  single ledger account that represents money owed by *many* different people —
  every learner's fee balance, added together. Rather than one row per learner
  in the main ledger, each transaction against a control account is tagged with
  a "subledger" (which learner, guardian, or supplier it actually belongs to).
  This is how the system can show you both "total fees owed across the whole
  school" and "what this one learner owes" from the same underlying numbers.

---

## General ledger

_(Chart of accounts, cost centres, journals, posting rules, trial balance, balance integrity — the school's core set of books.)_

### Chart of accounts

**What it's for.** This is the master list of every account the school's books
are built from — things like "Cash at Bank", "School Fees Income", "Debtors
Control", "Salaries Expense". It shows them in a tree (headings and
sub-accounts) together with each account's live running balance.

**When to use it.** Open this when you need to see the full account structure,
check an account's current balance, or add a new account (e.g. a new expense
line the school hasn't tracked before, such as "Sports Equipment Expense").

**Fields (New/Edit account form):**
- **Type** — one of Asset, Liability, Equity, Income, or Expense. This is the
  account's basic accounting classification and cannot be changed after the
  account is created.
- **Code** — a short reference code for the account (max 20 characters), e.g.
  `1000` or `EXP-TRAVEL`. Must be unique for the school. Cannot be changed
  after creation.
- **Name** — the account's full descriptive name, e.g. "Tuition Fees Income"
  (max 150 characters).
- **Description (optional)** — free-text notes about what the account is used
  for (max 255 characters).
- **Parent account (heading)** — lets you nest this account under a broader
  heading account, for a tidy, grouped chart of accounts (e.g. "Bank Accounts"
  as a heading with "USD Current Account" and "ZWG Current Account"
  underneath). Only non-postable "heading" accounts can be picked as a parent
  — see "Postable" below.
- **Subledger type** — set this to **Learner**, **Guardian**, **Supplier**, or
  **Staff** if this account needs to track balances for *individual* people or
  organisations rather than one lump sum. This matters most for **control
  accounts** (see below): a control account like "Debtors Control" needs to
  know which specific learner or guardian each line belongs to, so every
  transaction posted to it must carry a subledger reference. Leave this as
  "None" for ordinary accounts (e.g. straightforward expense or bank accounts)
  that don't need that breakdown.
- **Restrict to currency** — leave as "Any currency" to let the account be
  used in any currency the school transacts in, or lock it to one specific
  currency (e.g. force a specific bank account to only ever hold USD). If set,
  any transaction in a different currency will be rejected.
- **System key (optional, create only)** — leave this blank in almost all
  cases. It marks an account as a special "system" account that other parts of
  the software look up automatically by name — for example `rounding`
  (absorbs tiny rounding differences), `suspense` (holding account for
  unresolved transactions), `uncleared_cheque`, `cash_over_short`, or
  `credit_balance`. Only set this if you're deliberately creating one of these
  reserved accounts; getting it wrong can break automatic postings elsewhere
  in the system.
- **Postable (can receive journal lines directly)** — switch on for an
  account that actual transactions get posted to (e.g. "Stationery Expense").
  Switch off for a "heading" account that exists only to group other accounts
  together in the chart (e.g. "Total Expenses") — headings can't receive
  transactions directly, only their children can.
- **Control account (requires a subledger on every line, create only)** —
  switch this on for an account that represents a *total* of many individual
  balances that must be tracked separately underneath it — most commonly a
  debtors or creditors control account. When switched on, you must also
  choose a **Subledger type** (above), because every transaction posted to
  this account will require the system to record exactly which
  learner/guardian/supplier/staff member it belongs to. This is what lets the
  system produce a single "Debtors Control" figure on the trial balance while
  still being able to tell you exactly what each family owes.
- **Requires a cost centre on every line** — switch on if every transaction
  posted to this account must also specify a cost centre (see Cost Centres
  below). Useful for accounts you want to always analyse by department/
  section, e.g. a "Boarding Expenses" account you want split by boarding house
  every time.

### Cost centres

**What it's for.** Cost centres let you tag transactions with *where* in the
school they belong — a department, section, or activity — so you can see
spending and income broken down that way, separately from the account
structure. For example, the same "Stationery Expense" account might be split
across "Primary School", "Secondary School", and "Boarding" cost centres.

**When to use it.** Set these up once, early on, to match how the school
wants to analyse its finances (by campus, section, or department), then use
them whenever you post journals or configure accounts that require cost
centre tracking.

**Fields (New cost centre form):**
- **Code** — a short reference code for the cost centre (max 20 characters),
  must be unique.
- **Name** — the descriptive name, e.g. "Boarding Department" (max 150
  characters).
- **Parent (optional)** — nest this cost centre under a broader one, to build
  a hierarchy (e.g. "Junior Section" under "Primary School").
- **Section (optional)** — tie this cost centre to one specific school
  section (e.g. Primary, Secondary). Leave as "Whole school" if the cost
  centre applies across the whole institution rather than one section.
- **Profit centre** — switch on if this cost centre is treated as a
  revenue-generating unit in its own right (e.g. a school shop or boarding
  facility that both earns and spends money), rather than a pure cost/
  overhead area. This is mainly used for reporting emphasis.

Once created, cost centres in the list show whether they are currently
**Active** or **Inactive**, but this screen only supports creating new cost
centres — there's no edit form, so get the code and structure right before
saving.

### Journals

**What it's for.** A journal is a single accounting entry — a set of debit
and credit lines that must balance. Most journals in the system are created
automatically by everyday actions (invoicing, receipting, etc.), but this
section lets you view all journals, create a manual one by hand, review one
in detail, approve it, or reverse it.

**When to use it.**
- **Journals list** — to browse or search every journal posted or drafted for
  the school (by number, type, narration, date, or status).
- **New manual journal** — when you need to record something the system
  doesn't have a built-in transaction screen for, e.g. a correction, an
  accrual, or a one-off adjustment a bookkeeper needs to enter directly.
- **Journal detail** — to inspect a specific journal's lines and audit trail,
  and to approve or reverse it from there.
- **Reverse a journal** — when a posted journal turns out to be wrong and
  needs to be undone.

**Fields — Journals list:** **Number**, **Type**, **Narration**, **Effective
date**, **Status** (Draft or Posted) — searchable/sortable columns to find the
journal you're after.

**Fields — New manual journal:**
- **Narration** — a short description of what the journal is for (required,
  max 255 characters). This is the main label people will see when reviewing
  this journal later, so make it clear (e.g. "Correct miscoded stationery
  invoice #4021").
- **Effective date** — the accounting date this entry should count as
  occurring on (defaults to today).
- **Reference (optional)** — an external reference number, e.g. a supporting
  document or invoice number (max 100 characters).
- **Journal lines table** (minimum 2 lines, add/remove as needed):
  - **Account** — which account this line posts to (only accounts marked
    "Postable" appear here).
  - **DR/CR** — whether this line is a debit or a credit.
  - **Currency** — USD or ZWG for this line.
  - **Amount** — the money amount for this line (up to 4 decimal places).
  - **Cost centre** — optional, tag this line to a specific department/
    section (required if the chosen account is set to "Requires a cost
    centre").
  - **Narration** — an optional note specific to this one line.
- Every journal must balance — total debits must equal total credits *within
  each currency* used on the journal, or it will be rejected when you save.
- **Important**: this screen has no way to specify *which* learner, guardian,
  supplier, or staff member a line belongs to. That means you cannot post a
  manual journal line directly to a **control account** (like a Debtors or
  Creditors Control account) — doing so will be rejected, because the system
  has nowhere to record whose balance it affects. Manual journals here are
  meant for general ledger–only corrections, not for changing an individual
  family's or supplier's balance (use the relevant invoice/receipt/
  credit-note screens for that).
- Saving always creates a **draft** — it is not real/posted yet. A different
  user than the one who created it must approve it (see below) before it
  affects any balances. This is a deliberate "two pairs of eyes" control, so
  the same person can't both create and approve a manual entry.

**Fields — Journal detail (view).** Shows the narration, reference, and (if
relevant) links to the journal it reverses or was reversed by; the full list
of lines with account, cost centre, DR/CR, and amount; and an audit panel
showing who posted it and who approved it. From here:
- **Approve & post** appears only if the journal is still a draft *and* you
  are not the person who created it. Approving makes it permanently real —
  after this it can only be reversed, never edited.
- **Reverse this journal** appears only if the journal is already posted and
  hasn't already been reversed.

**Fields — Reverse journal:**
- **Reason for reversal** — required, at least 15 characters. This becomes a
  permanent part of the audit trail explaining why the entry was undone, so
  write something genuinely useful (e.g. "Duplicate entry — same invoice
  posted twice on 10 Sept").
- **Post the reversal into a different, already-closed period** — leave this
  off normally. Only switch it on if you specifically need the reversal to
  land in a financial period that has already been closed/locked, which needs
  an extra level of permission because it's a bigger deal than reversing
  within the currently open period.
- Reversing never edits or deletes the original journal — it creates a
  brand-new journal with every line flipped (debits become credits and vice
  versa), so the full history is preserved.

### Posting rules

**What it's for.** Posting rules tell the system which accounts to
automatically debit and credit whenever a particular kind of financial event
happens, so staff don't need to build a manual journal for routine, repeatable
transactions.

**When to use it.** Set these up (with help from whoever is configuring the
system) when a new type of recurring transaction needs to know which accounts
it should hit automatically — for example, mapping a specific fee type to a
specific income account.

**Fields:**
- **Event key** — a short identifying code for the type of financial event
  this rule applies to (max 100 characters). This is locked once the rule is
  created, so choose it carefully — it needs to match exactly what the rest
  of the system expects to look up.
- **Debit account** — which account is automatically debited for this event.
- **Credit account** — which account is automatically credited for this
  event.
- **Cost centre (optional)** — automatically tags the resulting transaction
  with a specific cost centre.
- **Active** — switch a rule off temporarily without deleting it, if you need
  to pause it.

### Trial balance

**What it's for.** The trial balance is the classic "sanity check" report in
accounting: it lists every account's total debits and total credits as at a
given date, and confirms whether the books actually balance (total debits =
total credits). It's always calculated fresh from the underlying
transactions, never from a cached figure, so it reflects reality even if
something else looks off.

**When to use it.** Run this whenever you need to confirm the books are in
balance — before producing financial statements, before a board meeting, at
month/term end, or any time a total looks suspicious and you want to check
nothing has gone wrong structurally.

**Fields:**
- **As at** — the date to calculate balances up to (defaults to today). You
  can re-run it for any past date.
- **Term (optional)** — restrict the trial balance to one academic term, or
  leave as "All terms" to include everything to date.

The report is grouped by currency (each currency's debits/credits must
balance on their own), with an overall "Balanced" / "Out of balance"
indicator, and a final section showing everything consolidated into the
school's base currency.

### Balance integrity

**What it's for.** For speed, the system keeps a running "cached" balance for
every account so reports load quickly without recalculating from scratch
every time. This screen checks that cached figure against the real
transaction history and flags anywhere the two disagree.

**When to use it.** Use this as a periodic health check, or specifically if a
report or balance looks wrong, after a data import, after a system issue, or
if you simply want reassurance the numbers you're seeing are trustworthy.

**Fields.** There are no input fields — this is a read-only comparison. It
lists any mismatches found (account, term, currency, cached figure vs. the
true figure from source), and a single button:
- **Rebuild from source** — recalculates every account's cached balance from
  scratch using the actual transaction history, fixing any mismatches found.
  This doesn't change any transactions, only the cached summary figures.

---

## Currency

_(For schools billing and receipting in more than one currency — typically USD and ZWG.)_

### Currencies

**What it's for.** This is the registry of which currencies the school
actually deals in (e.g. USD and ZWG), and which one is the school's "home"/
reporting currency that everything else gets converted into for consolidated
reports.

**When to use it.** Set this up once when the school starts operating in a
new currency, or if you need to change settings like whether a currency can
be used for payment, or how cash payments in that currency should round.

**Fields:**
- **Currency** — pick from the available currency catalogue (e.g. USD, ZWG).
- **This is the school's base currency** — switch on for exactly one
  currency: the one all consolidated reports (like the trial balance's bottom
  summary) are converted into. Changing this is a significant decision since
  it's the anchor every other currency gets measured against.
- **Accepted for payment** — switch on if parents/customers can actually pay
  the school in this currency. A currency can exist in the system (e.g. for
  historical records) without currently being accepted for new payments.
- **Cash rounding increment (minor units)** — physical cash can't always be
  tendered in exact cents, especially with currencies like ZWG. This sets the
  smallest amount cash payments in this currency get rounded to. USD is
  typically left at `1` (no rounding); ZWG is often set higher to match the
  smallest note/coin actually in circulation.

### Exchange rates

**What it's for.** This is both the historical record of every exchange rate
ever entered (the "Exchange rates" list) and the form for capturing a new one
("Capture rate"). Rates here are never edited or deleted — a correction is
always entered as a brand-new rate, so the full history stays intact and
auditable.

**When to use it.** Open the list to check what rate was in effect on a given
date, or to review the audit trail. Use "Capture rate" whenever a new
official exchange rate needs to be recorded (e.g. a new daily bank rate, or
an updated interbank/auction rate).

**Fields — Exchange rates list.** Filter/sort by **From**, **To** currency,
**Rate**, **Effective from** date, and **Status** — Pending (awaiting
approval), Active (currently in effect), Superseded (replaced by a newer
rate), or Rejected.

**Fields — Capture rate:**
- **From** and **To** — the currency pair this rate converts between (must be
  two different currencies).
- **Rate (1 From = ? To)** — the numeric exchange rate, up to 10 decimal
  places.
- **Source** — where this rate comes from (e.g. a bank feed or a manually
  entered rate). This matters because **the source decides whether the rate
  takes effect immediately or needs approval first** — sources marked
  "(requires approval)" in the dropdown will put the new rate into a
  "Pending" state, visible on the Approve rates screen, rather than making it
  active straight away.
- **Effective from** — the date and time this rate starts applying.
- **Notes (optional)** — free text to record context, e.g. where the rate
  came from or why it was captured (max 255 characters).

### Approve rates

**What it's for.** Some exchange rate sources are configured to require a
second person's sign-off before a newly captured rate goes live. This screen
is the queue of rates waiting for that approval.

**When to use it.** Check this screen whenever someone has captured a new
rate from a source that requires approval, before that rate can actually be
used in any transaction or revaluation.

**Fields / actions:**
- Each pending rate shows the **Pair**, **Rate**, **Effective from**, and who
  **Captured** it.
- **View impact** — expands a live preview showing what would happen to total
  debtors, total creditors, and the resulting FX gain/loss if this rate were
  approved right now, calculated against real current balances. Use this to
  sanity-check a rate before committing to it — it only shows something if
  one side of the pair is the school's base currency (otherwise there's
  nothing in the ledger for it to affect).
- **Approve** — makes the rate active and available for use immediately. This
  cannot be undone from this screen, so review the impact first.
- **Reject** — requires a **Reason** (at least 10 characters) explaining why.
  The rate is marked "Rejected" and never becomes active, but stays visible
  in the history for audit purposes.

### Rate simulator

**What it's for.** A standalone "what if" tool: it lets you test the effect
of a hypothetical exchange rate — one that hasn't actually been captured or
approved anywhere — on the school's debtors, creditors, and FX gain/loss,
without changing anything in the real books.

**When to use it.** Use this to explore scenarios before committing to a rate
change — for example, checking what a rumoured new interbank rate would do to
the school's reported debtor balances, for budgeting or board discussion
purposes, before deciding to formally capture and approve it.

**Fields:**
- **Foreign currency** — which non-base currency to test (e.g. ZWG).
- **Proposed rate (1 foreign = ? base)** — the rate you want to test, up to
  10 decimal places. This does not need to match any real captured rate.
- **As at** — which date's balances to run the simulation against.
- **Preview** — runs the calculation and shows: total debtors and creditors
  before vs. after the proposed rate, the resulting FX gain or loss, and a
  line-by-line breakdown per account showing the foreign-currency balance,
  its currently recorded base-currency value, and what it would become under
  the proposed rate. Nothing here is saved.

### FX revaluation

**What it's for.** Foreign-currency balances (like debtors or creditors held
in ZWG when the school reports in USD, or vice versa) drift out of step with
reality as exchange rates move. Revaluation is the formal, periodic process
of restating those balances to the current closing rate and recording the
resulting gain or loss — this is a standard accounting step, and skipping it
causes the balance sheet to overstate or understate foreign-currency
positions over time.

**When to use it.** Run this at each period end (and before locking/closing a
financial period) so that all foreign-currency balances are accurately
restated before reporting.

**Fields:**
- **Revaluation date** — the date to restate balances to (defaults to
  today).
- **Run revaluation** — performs the restatement. If anything actually needed
  adjusting, this posts a journal entry recording the gain/loss; if nothing
  needed restating, it tells you so without posting anything.
- **History table** — shows every revaluation run: **Date**, **Term**,
  **Gain**, **Loss**, **Net** result, and **Status** (e.g. Posted, Reversed).
- **Reverse** — available only for a posted revaluation that created a
  journal entry. Requires a **Reason** (at least 15 characters), and undoes
  the revaluation via a proper reversing journal rather than deleting
  anything.

### Conversion log

**What it's for.** A permanent, read-only record of every single currency
conversion the system has ever performed — whether triggered by a journal
line, an invoice, a receipt, or a revaluation — including the exact rate used
and both the original and converted amounts.

**When to use it.** Refer to this whenever you (or an auditor, or a parent)
need to trace exactly how a converted figure was arrived at — which rate was
used, and when.

**Fields (all read-only, filterable columns):**
- **Context** — what kind of transaction triggered this conversion: Journal
  line, Invoice, Receipt, or Revaluation.
- **From** — the original currency and amount.
- **To** — the converted currency and amount.
- **Rate used** — the exact exchange rate applied to this specific
  conversion.
- **Converted at** — the date and time the conversion took place.

---

## Fees & billing

_(Setting up what the school charges, and running the billing cycle that turns those charges into real invoices.)_

### Fee components

**What it's for.** Fee components are the building blocks every fee is made
of — things like "Tuition," "Boarding," "Bus Fee," or "Exam Fee." Before you
can charge anyone anything, the item they're being charged for has to exist
here first, in the school's catalogue of chargeable items.

**When to use it.** Open this screen when the school introduces a brand-new
type of charge — a new levy, a new activity fee, a new boarding tier — that
isn't already in the catalogue. You don't come back here to edit routine
charges; the school's accounts team should already have set this up as part
of finance configuration, and a bursar typically only visits to check what a
component's settings are, or to add a genuinely new one. Note there is no
"edit" for an existing component — if one is set up wrong, the right process
is to deactivate it and create a corrected replacement, so nothing that's
already been billed silently changes underneath it.

**Fields:**
- **Code** — a short internal reference (e.g. `TUIT`, `BUS01`). Used to
  identify the component in lists and reports; keep it short and consistent.
- **Name** — the full, parent-facing name that will appear on invoices and
  statements (e.g. "Tuition Fee — Term 1").
- **Category** — a general classification (Tuition, Levy, Boarding,
  Transport, Activity, Examination, Material, Deposit, Other). This groups
  similar charges together for reporting and doesn't affect how the amount is
  calculated.
- **Default currency** — the currency (USD or ZWG) this component is
  normally charged in. Individual fee structures can still charge it in a
  specific currency, but this is the fallback.
- **Tax category** — how this charge is treated for VAT/tax purposes:
  **Exempt** (no VAT applies at all), **Zero-rated** (still a taxable supply,
  but the VAT rate is 0%), or **Standard** (VAT applies at the normal rate).
  Get this right, because it affects your VAT return, not just the invoice
  total.
- **Income account** — the ledger account that gets credited (i.e., recorded
  as revenue) when this fee is invoiced. Think of this as "which line of the
  school's income statement does this money count as."
- **Debtor account** — the ledger account that tracks what's still owed for
  this fee — the "control account" for amounts receivable. Every unpaid
  invoice for this component increases the balance in this account; every
  payment reduces it. This is what ties the fee back to the school's balance
  sheet.
- **Cost centre (optional)** — an optional tag (e.g. a department, branch, or
  campus) used purely for internal reporting, so income/costs can be sliced
  by area of the school. Leave blank if the school doesn't track by cost
  centre.
- **Allocation priority (lower settles first)** — when a parent's payment
  isn't enough to cover everything they owe, and the school uses
  "priority-based" allocation, this number decides which component gets paid
  off first. A lower number means higher priority — e.g. giving Tuition
  priority 10 and Transport priority 100 means an incoming payment clears
  Tuition arrears before Transport arrears, regardless of which is older. If
  the school always allocates payments strictly oldest-invoice-first instead,
  this number has no effect. Default is 100 (mid-priority) unless you have a
  specific reason to change it.
- **Mandatory** — switch this on if every learner the fee applies to must be
  charged it (most fees). Switch off for optional add-ons, like an optional
  after-school club, where a family opts in.
- **Refundable** — switch on if any unused/overpaid balance on this component
  can legitimately be refunded to the parent (e.g. a returnable deposit).
  Leave off for fees that are non-refundable once charged (e.g. a
  registration fee), so nobody accidentally processes a refund that shouldn't
  happen.
- **Fiscalisable** — switch on if this charge must be reported through the
  tax authority's fiscal receipting device when it's paid. This is a
  compliance flag for revenue authority reporting requirements, not something
  that changes the amount charged. If in doubt, check with your accountant
  before turning this on or off.

One thing to know that isn't on this screen yet: every fee component
automatically "counts toward the report gate" (the setting that can block a
report card from being released if a family owes too much) — there's
currently no on/off switch for this on the create screen, so assume every new
component you create will count toward that block if the school has the
report-card gate turned on.

### Fee structures

A fee structure is the rulebook that decides **who** gets charged **what**,
for a given academic year (and optionally a specific term). It's really three
connected screens.

**Fee structures list — what it's for.** Shows every fee structure the
school has ever created, every version of it, and its current status (Draft,
Active, Superseded, Archived).

**When to use it.** This is your starting point whenever you need to review
what's currently charging fees, check a past version, or start revising a
structure.

**Fields (columns):**
- **Name** — the structure's label, plus which academic year/term it applies
  to underneath.
- **Version** — structures are versioned; every change creates a new version
  rather than editing the old one in place, so nothing that's already been
  billed against version 1 can be silently altered by editing version 2.
- **Priority** — when a single learner matches more than one structure (e.g.
  a general "Day Scholars" structure and a more specific "Form 2 Day
  Scholars" structure), the one with the **lower** priority number wins. Use
  this to make more specific structures override general ones.
- **Status** — Draft (not yet used for billing), Active (currently governs
  billing), Superseded (replaced by a newer version), Archived.

**Fee structure builder (New structure / Revise) — what it's for.** This is
where you actually define a fee structure — who it applies to, and what it
charges them.

**When to use it.** Creating fees for a new year/term, or correcting/
adjusting an existing structure (which always creates a new version — the
version currently in use is left untouched, so learners already billed under
it aren't retroactively changed).

**Fields:**
- **Name** — a descriptive label, e.g. "Form 1–4 Day Scholars 2026."
- **Priority** — see above; lower number wins when a learner matches multiple
  structures.
- **Academic year / Term** — which period this structure governs. Choose a
  specific term if you want a "preview match count" to work (see below);
  leave term blank for a structure that applies across the whole year. Note:
  once a structure exists, its year/term is locked — a revision can change
  the rules and amounts, but not which year/term it belongs to.
- **Rules — who this applies to** — one or more conditions that decide which
  learners this structure charges. Each rule has:
  - **Attribute** — the learner characteristic being checked (Section, Grade
    level, Class, Enrolment type, Residency, Pathway, House, Nationality,
    Gender, Entry cohort, Subject count).
  - **Operator** — Equals, In (matches any of a list), Not in, Exists,
    Between.
  - **Value** — the value(s) to match, e.g. `FULL_TIME`, or a comma-separated
    list for "In"/"Between."

  A learner must satisfy **all** the rules on a structure to be billed under
  it. Use "Preview match count" (only works once a specific Term is chosen)
  to sanity-check how many current learners actually match your rules before
  you commit to using this structure — always do this before revising a live
  structure, so you're not surprised by who suddenly starts (or stops) being
  charged.
- **Items — what is charged** — one row per fee component this structure
  charges, with:
  - **Component** — which fee component (from the catalogue above) this line
    charges.
  - **Basis** — how the amount is calculated. The options that actually work
    today are: **Flat per term** (one fixed amount for the whole term),
    **Per subject** (rate × however many subjects the learner takes — this is
    how part-time/full-time pricing is really handled, not a separate "part
    time" setting), **Per month** (rate × months in the term), **Per day**
    (rate × teaching days in the term — used for pro-rating), and **One-off**
    (charged exactly once ever, and never charged again for that learner even
    in a later term). **Per unit, Tiered, and Usage-based currently do not
    work** — the billing engine will error out if you select one of these, so
    avoid them until told otherwise.
  - **Amount** — used for the Flat-per-term and One-off bases (a straight
    amount).
  - **Unit rate** — used for the Per subject/Per month/Per day bases (the
    amount multiplied by the count).
  - **Currency** — USD or ZWG for that specific line (can differ from the
    component's own default).

  A handful of more advanced settings (minimum/maximum caps, day-by-day
  proration, charge frequency, and "optional" line items) exist in the system
  but aren't editable from this screen yet — new items default to
  "pro-rated by day" and "termly," and if those defaults are wrong for a
  specific line, ask for a direct data fix rather than expecting to see a
  control for it here.

**Structure versions & compare — what it's for.** Lets you see every version
ever created of one structure "family" (same school/year/term/name), and
compare any two versions side-by-side — their rules and their items — so you
can see exactly what changed between them.

**When to use it.** When you need to explain to a parent (or auditor) why a
bill changed between one revision and the next, or to double-check a revision
before relying on it.

**Fields:** **Compare** / **Against** — pick the two versions you want to see
side-by-side.

### Ad hoc charge

**What it's for.** Raises a one-off charge that sits outside the normal fee
structure — a library fine, a damage bill, a uniform sale, a lost-textbook
fee.

**When to use it.** Any time you need to bill something that isn't part of
the regular termly fee structure, either for one learner or for a whole class
at once (e.g. every learner in a class needs to be charged for a shared
excursion).

**Fields:**
- **Individual / Bulk to class** (tabs) — choose whether you're charging one
  named learner, or every currently enrolled learner in a chosen class.
- **Learner** — search by admission number or name (Individual mode only).
- **Class** — the class to bulk-charge (Bulk mode only).
- **Component** — which fee component this charge is recorded against (must
  already exist in the catalogue).
- **Description** — free text explaining the charge, e.g. "Broken window —
  Dorm 3." This appears on the learner's account, so make it something a
  parent will understand.
- **Quantity** — how many units of the charge (e.g. 2 broken chairs).
- **Unit rate** — the price per unit.
- **Currency** — USD or ZWG.
- **"I am approving this charge"** — only appears if you separately hold
  approval rights. Ad hoc charges above a set threshold (a school-wide
  setting, currently $50.00) need sign-off by someone with that specific
  approval permission before they can post — the idea being that the person
  raising the charge and the person approving a large one should usually not
  be the same individual. If you don't hold approval rights and the amount is
  above the threshold, the charge is simply skipped (not silently approved)
  and you'll be told to get someone else to raise it.

### Fee simulator

**What it's for.** Answers "what would this learner pay?" before you commit
to anything — useful for quoting a prospective family or checking a scenario
without actually billing anyone.

**When to use it.** A parent asks "how much will my child pay if they move to
boarding" or "if they drop a subject" — rather than guessing, you can simulate
it against a similar real learner.

**Fields:**
- **Learner** — search and pick an existing learner. Because the simulator
  needs a real record to read section/grade/enrolment type/etc. from, you
  should pick any current learner whose profile matches the scenario you
  actually have in mind (e.g. any current Form 2 boarder), rather than
  expecting to invent a purely hypothetical student.
- **Term** — which term's fee structure to simulate against.
- **Proposed subjects (part-time only, optional)** — override the learner's
  actual subject list with a hypothetical one, useful for "what if they
  added/dropped this subject" questions on a per-subject fee.

The result shows the computed amount and a "resolution trace" — a plain
breakdown of exactly which structure and rule matched, and how the number was
built up, so you can explain it to a parent. If nothing matches, it tells you
plainly that this would be an exception (i.e. the learner has no fee
structure covering them) rather than pretending the charge is zero.

### Billing runs

Billing runs are how termly (or ad hoc scope) invoices actually get raised in
bulk. There are three screens, used one after another.

**New billing run — what it's for.** Kicks off a fresh calculation of fees
for a group of learners, for the school's current academic year/term.

**When to use it.** At the start of a term (or whenever you need to (re)bill
a specific group), this is the first step. It only calculates — nothing is
invoiced yet.

**Fields:**
- **Section**, **Grade level**, **Class**, **Enrolment type** — the "scope
  filter." Leave all four blank to bill every active learner in the school.
  Fill in one or more to narrow the run to a specific slice — e.g. just Grade
  8, or just Full-time learners in one class — useful for re-running a
  billing calculation for a smaller group without disturbing everyone else's
  invoices.

Clicking "Compute" doesn't invoice anyone yet — it just works out what
everyone in scope should be charged, and takes you to the preview.

**Billing run preview — what it's for.** Shows exactly what a billing run
calculated, learner by learner, before anything is actually posted to
accounts — your chance to catch problems before invoices go out.

**When to use it.** Immediately after computing a run, and again if the run
sits half-reviewed for a while.

**What you'll see:**
- **Learners tab** — every learner in scope, which fee structure/version was
  used to charge them, and the net amount. "Why this amount?" expands a
  line-by-line trace of how that figure was built, exactly like the
  simulator's trace.
- **Exceptions tab** — learners the system couldn't confidently bill (e.g.
  nobody's fee structure matched them) — these need manual attention; they
  will not be silently skipped or charged zero.
- **Variance tab** — compares each learner's new total to their previous
  term's total, flagging anything that swung by more than the school's
  configured variance threshold (default 25%) — a quick way to catch a
  structure that's accidentally overcharging or undercharging.
- **Approve** — confirms the numbers are correct and ready to go. This step
  requires a specific "billing approve" permission, deliberately separate
  from the "run" permission, so a run and its approval aren't necessarily
  done by the same person.
- **Commit** — the point of no return: this is what actually raises the real
  invoices and posts the accounting entries. It requires its own "billing
  commit" permission and, once done, **cannot be undone** — if something's
  wrong after committing, it's fixed with credit notes/voids afterwards, not
  by re-running.

**Billing run history — what it's for.** A permanent log of every billing run
ever computed, with its status, learner count, exception count, and total
value.

**When to use it.** To check whether a term's billing run has already been
done, to find a past run, or to see how many exceptions it produced.

---

## Invoicing & debtors

_(Once a learner has been billed: tracking what they owe, chasing late payers, and handling waivers, payment plans, and refund-adjacent adjustments.)_

### Invoices

**Invoices list — what it's for.** Every invoice the school has ever issued,
searchable and filterable.

**Fields (columns):**
- **Number** — the invoice's own reference number.
- **Type** — what kind of invoice this is (e.g. regular termly billing vs. an
  ad hoc charge).
- **Due** — the due date.
- **Balance** — how much is still unpaid on this invoice.
- **Status** — Draft, Issued, Partially paid, Paid, Overdue, Voided, or
  Written off.

An issued invoice is never edited directly — if it's wrong, it gets voided
and a corrected one issued in its place, so there's always a clean paper
trail.

**Invoice detail — what it's for.** The full breakdown of one invoice — every
fee line, how payments have been applied against it, and its accounting
entry.

**What you'll see:**
- **Lines** — each fee component charged, with a calculation note explaining
  how the amount was worked out, plus gross/discount/net for each line.
- **Allocations** — every payment (receipt) that's been applied against this
  invoice, and by which method.
- **Summary** — Gross, Discount, Paid, Credited, Written off, and the running
  Balance.
- **Journal** — a link to the underlying accounting entry, for anyone who
  needs to trace it into the general ledger.
- **Void** — a button to void the invoice (see below), only shown while the
  invoice is still open (not already paid, voided, or written off).

**Void invoice — what it's for.** Cancels an invoice that should never have
been issued (wrong learner, wrong amount, duplicate, etc.), reversing its
accounting entry.

**When to use it.** Only for a genuine error — once even a single payment has
been allocated against the invoice, the system will refuse to void it; you'd
need to unwind the payment/allocation first.

**Fields:**
- **Reason** — a required explanation (minimum 10 characters) of why the
  invoice is being voided. This is kept permanently on the record, so make it
  clear enough for someone else to understand later.

### Credit notes

**What it's for.** Reduces what a learner owes without it looking like a
payment was received — used for genuine billing corrections, not for
recording money coming in. As the screen itself notes, a credit note debits
fee income and credits the fee debtor account directly; it's never treated as
a receipt, so it never shows up in cash collections figures.

**When to use it.** A subject was dropped after billing, a learner withdrew
mid-term, a billing mistake needs correcting, a residency change means a
lower fee applies, or you're extending goodwill/correcting an overcharge.

**Fields:**
- **Learner** — search and select.
- **Against invoice (optional)** — tie the credit note to a specific existing
  invoice, or leave it as "Standalone" if it's not linked to one particular
  invoice.
- **Reason code** — Subject dropped, Withdrawal, Billing error, Residency
  change, Goodwill, Overcharge. Pick the one that best matches — this feeds
  reporting on why credits are being issued.
- **Currency** — USD or ZWG.
- **Reason (free text)** — a required explanation (minimum 10 characters) —
  kept on the record permanently.
- **Lines** — one or more rows, each with a **Component** (which fee this
  credit relates to), **Description**, and **Amount** — you can credit
  against multiple components in one go.
- **"I am approving this credit note"** — only shown if you separately hold
  approval rights, and only needed if the credit note is above the school's
  approval threshold. Same two-person principle as ad hoc charges: large
  credit notes shouldn't be requested and approved by the same person.

### Statements

**What it's for.** Produces a full transaction history (a "statement of
account") for a learner or a guardian over a chosen date range — every
charge, payment, credit, and running balance, in order. It's rebuilt fresh
from the underlying accounting records every time, so a statement for a
period that's long closed will always come out identical if you regenerate it
later.

**When to use it.** A parent asks "can I see a full breakdown of everything
on my account this term," or you need to reconcile a dispute.

**Fields:**
- **Statement for** — choose whether you're generating a statement for the
  **Learner** themselves, or for a **Guardian** (the "billed party" — a
  guardian's statement can pull together the balances of more than one of
  their children in one place, depending on how the school has things set
  up).
- **Learner/Guardian** — search and select the specific person.
- **Currency** — which currency's activity to show (USD or ZWG) — a learner
  or guardian with balances in both currencies needs a statement run
  separately for each.
- **From / To** — the date range to cover.

The result shows an **Opening balance**, a line-by-line list of every
transaction (date, reference, narration, whether it's a **DR** (debit —
something added to what's owed) or **CR** (credit — a payment or credit note
reducing what's owed), the amount, and the running balance after each), and a
**Closing balance**.

### Aged debtors

**What it's for.** The classic "who owes us money, and how overdue is it"
report — every outstanding invoice grouped by how many days it's been
unpaid.

**When to use it.** Routine debt-chasing review (e.g. weekly/monthly), or
before a governing-board finance meeting.

**Fields:**
- **Currency** — report on USD or ZWG balances (run it separately for each
  currency the school collects in).
- **Section** / **Grade level** / **Residency** (Day/Boarder) — optional
  filters to narrow the report to part of the school.

The report buckets every outstanding balance into aging bands (by default
1–30, 31–60, 61–90, 91–120+ days overdue — the exact boundaries are a
school-wide setting) so you can see at a glance how much debt is fresh versus
long overdue. Click a learner to jump straight to their account.

### Debtor workbench

**What it's for.** A prioritised, ready-to-work call list of every learner
with an outstanding balance, sorted highest balance first, with a place to
log the outcome of each chase attempt.

**When to use it.** This is your day-to-day collections tool — working the
phones/WhatsApp to chase fees, and keeping a record of who you've spoken to
and what was agreed.

**Fields:**
- **Currency** — which currency's debts to work through.
- **Log call** (per learner) opens a note with:
  - **Outcome** — Promised to pay, No answer, Disputed, Payment plan
    requested, Unreachable, Other.
  - **Note** — free text of what was discussed.
  - **Next action on (optional)** — a follow-up date, so the debt doesn't
    fall through the cracks.

  Previous notes for that learner are shown alongside, so you can see the
  chase history before calling again.

### Reminder schedules

**What it's for.** Sets up the automatic "reminder ladder" — a series of
rungs that automatically nudge parents (by SMS/email) as an invoice becomes
overdue, without a bursar having to manually chase every small balance.

**When to use it.** Set this up once per school (and adjust occasionally),
rather than something you touch daily. Each rung fires **at most once per
invoice**, and reminders are automatically suppressed for a learner who's on
an active, non-breached payment plan (since they're already being managed).

**Fields:**
- **Name** — a label for this rung, e.g. "First reminder" or "Final notice."
- **Days after due** — the grace period: how many days after an invoice's due
  date this rung should fire. A ladder typically has several schedules with
  increasing values (e.g. 7, 21, 45 days) to escalate gradually.
- **Minimum balance (minor units)** — only trigger this reminder if the
  overdue balance is at least this much. Note this is in "minor units" — i.e.
  cents, so 5000 means $50.00 — so as not to spam parents with reminders over
  trivial cent-level balances.
- **Channels** — SMS and/or Email.
- **Audience** — send to the **Fee-responsible guardian** only (the parent/
  guardian formally responsible for paying), or to **All guardians** linked
  to the learner.
- **Active** toggle (on the list) — turn a schedule on or off without
  deleting it.

### Payment plans

**What it's for.** Turns a lump-sum balance into an agreed instalment
schedule for a family struggling to pay in one go — a formal, trackable
alternative to just letting an account go overdue.

**When to use it.** A guardian requests to pay in instalments, or a
debtor-workbench chase note says "payment plan requested."

**Fields:**
- **Learner** — search and select the learner whose balance the plan covers.
- **Responsible guardian** — the guardian who's agreeing to and accountable
  for the instalments (the "billed party" for this plan).
- **Total** — the total amount being spread across instalments.
- **Currency** — USD or ZWG.
- **Instalments** — the instalment count, from 2 up to 12 — how many payments
  the total is split into.
- **First instalment due** — the due date of the first payment; later
  instalments are scheduled from there.

Once created, a plan is **Proposed** and needs to be **Approved** (a separate
permission) before it's active — again a deliberate second pair of eyes. From
the list you can also expand a plan to see/record individual **instalment
payments**, and **Cancel** a plan that's fallen through. A plan that misses a
payment beyond the school's configured grace period (a system-wide setting,
in days) is automatically marked **Breached** so it surfaces for follow-up
rather than being disguised as "still active."

### Waivers & write-offs

**What it's for.** Formally reduces or removes what a learner owes, for a
documented reason — with a required second-person approval before anything
actually changes the accounts. The screen makes the distinction explicit: a
**waiver** reduces the amount owed *before* it's chased (e.g. agreeing upfront
to discount a hardship case), while a **write-off** recognises a debt as
uncollectable *after* collection efforts have failed (e.g. a family that's
disappeared owing money nobody will ever recover).

**When to use it.** Hardship cases, staff-child discounts, an orphaned
learner, a bad debt that's been chased without success, or any other case
where the school is formally deciding not to collect part or all of a
balance.

**Fields (request):**
- **Learner** — search and select.
- **Type** — **Waiver** or **Write-off** (see distinction above — this also
  determines which approval permission and which reason codes make sense).
- **Invoice (optional)** — tie the request to a specific invoice, or leave
  general.
- **Amount** — how much is being waived/written off.
- **Currency** — USD or ZWG.
- **Reason code** — Hardship, Orphan, Staff child, Uncollectable, Deceased,
  Goodwill.
- **Reason** — required free-text explanation (minimum 10 characters),
  permanently kept on the record.

**Fields (approval, separate step).** Every request starts as **Pending** and
must be approved by someone holding the matching approval permission — and by
design that's meant to be a different person from whoever requested it, so
nobody can quietly waive their own family's fees. Approving requires
choosing:
- **Contra account** — the other side of the accounting entry, e.g. a "Bad
  Debt Expense" account for a write-off, or a discount/waiver expense
  account. This is the account that absorbs the cost of not collecting the
  money.
- **Debtor account being relieved** — the specific accounts-receivable
  account that gets reduced, i.e. which balance actually stops showing as
  owed.

A request can also be **Rejected** instead, which leaves the learner's
balance untouched.

---

## Till & receipting

_(Taking payment at the counter — opening a till, receipting cash/EcoCash/cheques/etc., cashing up at the end of a shift, and everything that flows from that.)_

### Open till

**What it's for.** This is the first thing a cashier does at the start of a
shift — it declares "I am now responsible for this till, and here is what's in
the drawer to start with." You cannot receipt any payment until you've opened a
till this way, and you can only have **one** till open at a time under your own
login.

**When to use it.** At the start of every cashiering shift, before taking any
payment.

**Fields:**
- **Till** — pick which physical till/counter you're operating (a school may
  have several — e.g. "Main Office", "Boarding House"). Each till is tied to
  its own cash and bank account behind the scenes, so money always lands in the
  right place.
- **Opening float (per currency, e.g. USD / ZWG)** — the amount of cash
  physically sitting in the drawer *before* you take any payment today. Count
  it and enter it honestly — this number is what "cashing up" at the end of the
  shift is checked against. Leave a currency blank (or 0) if that till doesn't
  hold float in that currency.

Once opened, you're taken straight to **Capture receipt** to start taking
payments.

### Capture receipt

**What it's for.** This is the actual counter screen — where you take a
payment from a parent or learner and record it. It's built to be fast: search
for the learner, see what they owe, take the money, done. If you don't know
who a payment is from (e.g. an unidentified bank deposit), you can still record
it — it goes to the **Suspense workbench** to be matched to a learner later
rather than sitting off the books.

**When to use it.** Every time you take a payment — cash, EcoCash, cheque, bank
transfer, card, or any other tender type, at an open till.

**Fields:**
- **Learner** — search by admission number or name. Once selected, you'll see
  the learner's **outstanding balance** (what they currently owe across all
  their unpaid invoices) so you know what the payment should cover. You can
  leave this blank for an unidentified/walk-in deposit — see "suspense" above.
- **Receipt type** — what kind of payment this is: *Fee* (school fees —
  the normal case), *Tuckshop*, *Hire* (e.g. hall/equipment hire), *Sundry*
  (anything else), or *Deposit*. This mostly affects how the payment is
  categorised in reports.
- **Currency** — which currency this receipt is in (USD or ZWG). The balance
  shown for the learner updates to match.
- **Payer name** — who is actually handing over the money (often the parent/
  guardian, not the learner).
- **Payer phone** — optional, useful for SMS/WhatsApp receipt confirmations
  later.
- **Narration** — optional free-text note about this receipt (e.g. "part
  payment for term fees").
- **Tenders table** — how the money is actually being paid, and you can split
  one receipt across several tender types (e.g. part cash, part EcoCash):
  - **Tender** — the payment method: Cash, Bank transfer, Card, Cheque,
    EcoCash, OneMoney, InnBucks, O'Mari, or ZIPIT.
  - **Amount** — how much of the total this tender covers.
  - **Reference** — optional; for a cheque this is the cheque number, for a
    mobile money payment it's the transaction reference — useful for tracing
    it later.
  - **Bank/settlement account** — normally leave this as "Till default" (it
    automatically uses the till's own cash or bank account). Only change it if
    this particular tender needs to land in a different bank account than the
    till's usual one.
  - A **cheque** is treated specially: it's recorded immediately, but the
    learner's balance doesn't actually go down until the cheque *clears*
    (confirmed by the bank) — this protects the school from a bounced cheque
    looking like a settled payment.

After saving, you land on the receipt itself, which you can print or void.

### Receipt (view/void)

**What it's for.** Shows everything about one receipt — the tenders that made
it up, and which invoices it was allocated against. **Void** reverses a receipt
entirely (if it was recorded in error, or a cheque bounced) — it reverses the
accounting entry and restores the learner's balance to what it was before, it
never just deletes the record. You always need a written reason to void a
receipt, since it's a serious, auditable action.

**When to use Void.** Only when a receipt was genuinely wrong and needs
undoing — a duplicate entry, a bounced cheque, money receipted to the wrong
learner. Not for correcting a typo you can fix another way.

**Fields (Void screen):**
- **Reason** — required, at least a sentence explaining why. This is kept
  permanently on the record.

### Cash up till

**What it's for.** Closing out your till at the end of a shift, in two honest
steps: first you **count** the drawer and declare what you found — *before*
the system tells you what it expects — then the system **reveals** the
difference (if any). This "count first, see the answer after" order is
deliberate: it stops a cashier from just typing in whatever number the system
expects.

**When to use it.** At the end of every cashiering shift.

**Fields (step 1 — declare):**
- **Currency counted (e.g. "USD counted")** — the actual physical cash total
  you counted in the drawer for each currency, right now. Count it before you
  look at anything else on screen.

**Fields (step 2 — reveal):**
- **Variance reason** — only fill this in if the system tells you there's a
  discrepancy beyond the school's allowed tolerance (a small mismatch, e.g.
  from rounding, is usually fine and closes automatically). If the gap is too
  large, you'll need to explain what happened, and — separately — a supervisor
  will need to sign off before the till can close (see below).

If the mismatch is too large to close yourself, the session is handed to
**Variance approval** for a supervisor to review and sign off — you don't need
to do anything further once you see that message.

### Variance approval

**What it's for.** A queue, for supervisors only, of till sessions where the
cashier's count didn't match what the system expected by more than the
allowed tolerance. A supervisor reviews each one and signs off before the
session can finally close. This is a deliberate second pair of eyes — a
cashier can never sign off their own variance.

**When to use it.** Whenever a cash-up couldn't close itself because of an
unexplained shortfall or overage — a supervisor works through this list.

**Fields:**
- **Sign-off reason** — required. Write down what you found out (e.g. "float
  was topped up mid-shift and not recorded" or "confirmed shortfall, referred
  to HR"). This becomes part of the permanent audit trail for that till
  session.

### Till sessions

**What it's for.** A history of every till session ever opened — who ran it,
when, and whether it closed clean or with a variance. Useful for spotting a
pattern (e.g. one cashier's till is often short).

**When to use it.** Reviewing cashiering activity, investigating a specific
shift, or checking whether a till is currently open.

**Fields (filters):**
- **Till** — narrow the list to one physical till.
- **Cashier** — narrow the list to one staff member.
- **Status** — Open, Declaring (mid cash-up), Closed, Reconciled, or Disputed.

### Daily banking

**What it's for.** A single day's total takings, broken down by payment
method and currency — the figure you'd use to check against what's actually
been banked.

**When to use it.** Preparing the daily banking slip, or reconciling what was
receipted against what physically went to the bank.

**Fields:**
- **Date** — the day you want totals for.

### Suspense workbench

**What it's for.** A queue of payments that came in without a clearly
identified learner — most often an unidentified bank deposit. The money is
never left "off the books" while unidentified; it's already recorded, just
waiting to be matched to the right learner.

**When to use it.** Regularly (ideally daily) — the older an item sits here
unresolved, the more it's flagged (a badge shows how many days old each item
is).

**Fields:**
- **Match to learner** — search and select which learner this payment
  actually belongs to.
- **Note** — optional; record how you figured out the match, for future
  reference.

Confirming a match here allocates the money against that learner's outstanding
invoices immediately — the system never guesses or auto-matches this for you,
a person always confirms it.

### Collections

**What it's for.** A summary report of everything receipted over a date
range, sliced three ways: by day, by payment method, and by cashier. Good for
spotting trends (e.g. a slow week) or for checking one cashier's total
takings.

**When to use it.** End-of-week/month reporting, or investigating a specific
cashier's or a specific tender type's totals.

**Fields:**
- **From / To** — the date range to report on.

---

## Gateways & reconciliation

A note before you start: only one test/sandbox payment provider ("Fake") is
wired up so far — ContiPay, Pesepay, and Paynow are the real providers the
system is designed for, but none of them is connected yet. Nothing here will
take real money until a real provider is added.

### Payment gateways

**What it's for.** Registering and configuring which online payment
providers this school accepts, and which GL accounts their settlements and
fees post to.

**When to use it.** Setting up online payments for the first time, rotating
a provider's credentials, or checking whether a provider is currently
responding ("Test connection").

**Fields:**
- **Driver** — which payment provider. Only "Fake" is available until a real
  provider is connected.
- **Name** — a label for this gateway (e.g. "ContiPay — Main").
- **Credentials** — the provider's own API key/secret. Stored encrypted;
  never shown again once saved. Leave blank when editing to keep the
  existing value.
- **Supported methods / currencies** — which payment methods (EcoCash,
  Visa, ZIPIT, …) and currencies this gateway can actually process.
- **Settlement account** — the GL account money from this gateway lands in.
- **Fee expense account** — the GL account the provider's own transaction
  fee is charged to.
- **Fee type / value / cap** — how the provider's fee is calculated, for
  your own reference (a percentage or a flat amount, optionally capped).
- **Sandbox mode** — leave this on until you have real production
  credentials. A sandbox gateway is clearly labelled everywhere it appears.
- **Default gateway** — the one offered first when more than one is active.
  Only one gateway can be the default at a time.

"Test connection" pings the provider and records whether it's currently
responding — useful before telling parents a payment method is available.

### Payment intents

**What it's for.** Every online payment attempt, successful or not — what
was initiated, by whom, for how much, and its current status.

**When to use it.** Checking on a parent's "my payment didn't go through"
query, or manually nudging a stuck payment.

**Fields (actions):**
- **Poll** — ask the provider directly what actually happened to this
  payment. Use this if a payment seems stuck in "pending" — most payments
  settle automatically, but polling forces an immediate check.
- **Force settle** — mark a payment as paid without the provider confirming
  it. This is a last resort, locked behind its own separate permission —
  only use it when you are certain the money has genuinely arrived (e.g. the
  provider's own dashboard confirms it, but the automatic notification never
  reached this system).

### Webhook log

**What it's for.** A raw record of every notification a payment provider has
sent this system — the underlying evidence behind every payment intent's
status. If a provider ever disputes what it sent, this is what settles it.

**When to use it.** Investigating why a payment didn't update automatically.

**Fields (actions):**
- **View payload** — see exactly what the provider sent, headers and body,
  unedited.
- **Reprocess** — for a webhook that failed to process (and whose signature
  was genuinely valid) — re-run it now that whatever was missing the first
  time (usually: the matching payment record) exists. This never re-sends
  anything to the provider; it only re-reads what they already sent.

### Bank accounts

**What it's for.** The school's real-world bank accounts, each linked to
its own account in the chart of accounts.

**When to use it.** Setting up a bank account before you can import its
statements.

**Fields:**
- **Bank / account name / number / branch** — exactly as they appear on the
  bank's own statement.
- **Currency** and **account type** (current, nostro, FCA, savings).
- **GL account** — the chart-of-accounts account this bank account's
  balance is tracked against.

### Import statement

**What it's for.** Bringing a bank statement (as a CSV export from your
bank) into the system, ready for matching against receipts.

**When to use it.** Whenever you have a new statement to reconcile —
typically monthly, or whenever your bank makes one available.

**Fields:**
- **Bank account**, **statement period**, and **opening/closing balance** —
  exactly as shown on the statement itself.
- **File** — the CSV export from your bank.
- **Column mapping** — once a file is uploaded, tell the system which
  column in YOUR bank's export is the date, description, reference, debit,
  and credit. Every bank formats this differently, so this has to be set
  per import (though it's usually the same for the same bank each time).

Any line whose reference exactly matches an existing receipt number is
matched automatically the moment you import. Everything else goes to the
matching workbench next.

### Matching workbench

**What it's for.** Working through a statement's lines that weren't
automatically matched — confirming by hand which receipt (if any) a bank
line corresponds to.

**When to use it.** Right after importing a statement, to clear anything the
automatic reference match missed.

**Fields (actions):**
- **Match** — shows candidate receipts of the same amount and currency,
  within a few days of the bank line's own date, each with a suggested
  match strength. You pick the right one and confirm — the system never
  matches this for you.
- **To suspense** — for a credit with no plausible matching receipt at all
  (an unidentified deposit). This creates a real suspense receipt rather
  than leaving the money off the books; it then shows up in the Suspense
  workbench (Till & receipting) to be identified later, same as a till
  deposit would.

### Reconciliation

**What it's for.** The daily cross-check between what the payment gateway
says it settled, what was actually banked, and what's on the books — the
control that turns "we think the money arrived" into "we can prove it."
Nothing here fixes itself; every mismatch becomes an exception for a person
to work through.

**When to use it.** Run it regularly (daily is the intent) for each active
gateway and bank account.

**Fields:**
- **Scope** — gateway only, bank only, or both together.
- **Run date** and **currency**.
- **Gateway settlements** — until a real payment provider with its own
  settlement report is connected, there's no automatic feed of what the
  gateway itself says it settled. Copy these rows (reference, amount, fee)
  from the provider's own portal before running a gateway-scope
  reconciliation.

A run shows as **clean** (nothing to do) or **exceptions** (see below).

### Exceptions

**What it's for.** Every mismatch a reconciliation run has found, each
needing a human decision — never auto-resolved.

**When to use it.** After every reconciliation run that comes back with
exceptions.

**Fields (actions):**
- **Convert to suspense** — only offered for an unmatched bank credit with
  no receipt behind it at all; creates a suspense receipt and closes the
  exception in one step.
- **Resolve** — close any exception with a note explaining what you found
  (e.g. "gateway reporting delay, payment confirmed arrived" or "duplicate
  entry, reversed"). This note is permanent — write enough that someone
  reading it in a year understands what happened.

A reconciliation run is only marked fully reviewed once every one of its
exceptions has been closed this way.
