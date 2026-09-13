# Restore Runbook

**Book A CORE-13 · BR-CORE-13-003/009/010 · Domain A Acceptance Gate: "Restore runbook written and rehearsed once by someone who did not write it."**

> This document satisfies the "written" half of that gate item. The
> "rehearsed once by someone who did not write it" half is a human
> process step, not something a runbook document — or the person who
> wrote it — can complete on its own. Track it explicitly: pick a date,
> pick an operator who did not write this document, have them follow it
> against a disposable environment, and record the outcome (a passed
> `restore_tests` row from a genuinely different operator counts) at
> the bottom of this file.

## Rehearsal log

| Date | Operator | Environment | Outcome | Notes |
|---|---|---|---|---|
| _(none yet)_ | | | | |

---

## 1. What actually exists today

Before following this runbook, understand what this system's restore
tooling really does and does not do yet — several steps below are
manual/operational precisely because the automation for them isn't
built:

- **Backups** (`Backup` model, `CreateBackupAction`) are a logical,
  encrypted JSON dump of this application's own tables (`database`
  type), uploaded file bytes (`files`), both (`full`), or one school's
  tenant-scoped slice (`school_export`) — stored encrypted on the
  `backups` disk (`storage/app/backups` locally; point this at
  off-region object storage in production per BR-CORE-13-001).
- **Restore testing** (`RunRestoreTestAction`, `php artisan
  serp:run-restore-test`) decrypts a backup, parses it, and verifies
  every table the backup's own manifest promised is present with the
  row count it recorded at backup time. **It does not spin up a real,
  separate restore environment and physically restore into it** — that
  needs real infrastructure (a scratch database server, a scratch file
  store) this build does not provision. Section 4 below is the manual
  procedure for an actual physical restore, which the automated check
  cannot substitute for.
- **Production restores require dual authorisation**
  (`RequestProductionRestoreAction` → `ApproveProductionRestoreAction`,
  BR-CORE-13-009): a `restore_tests` row with `target_environment =
  'production'` starts at `pending_approval` and needs a second,
  different user's sign-off before anyone should proceed to Section 5.
  **Approval only clears this system's own audit gate — nothing in
  this codebase actually executes a production restore.** That is
  Section 5's manual procedure.
- The admin UI for all of this is `Core\Backups\Index`/`Show`
  (`/backups`, `/backups/{ulid}`) and the school-scoped
  `Core\Backups\ContractExitExport` (`/schools/{school}/backups/contract-exit-export`,
  BR-CORE-13-008's open-format export — a different artifact from a
  `school_export` backup; see that action's own docblock).
- Scheduled automatically (`routes/console.php`, driven by
  `ScheduledTaskRegistry`): a `full` backup nightly at 01:00, retention
  applied at 01:30, and a restore test against the latest completed
  backup weekly (Sunday 03:00). `Scheduling\Tasks`/`TaskRuns`
  (`/scheduling/tasks`) shows whether these actually ran.

## 2. Before you touch anything

1. Confirm which incident you're responding to and whether a restore
   is genuinely the right response — a bad deploy is usually better
   fixed by rolling the deploy back, not restoring the database.
2. If this is a **rehearsal**, not a real incident: use a disposable
   environment (a throwaway database + a scratch app deployment), never
   a real tenant's live database. Say so explicitly in the rehearsal
   log above.
3. If this is a **real incident**: page whoever owns the on-call
   rotation before proceeding past Section 3. A restore is one of the
   most consequential actions this system can take.

## 3. Pick the backup to restore from

1. Open `/backups` and find the most recent backup with status
   `completed` or `verified` — prefer `verified` (it has already
   passed an automated restore test; BR-CORE-13-003's "a backup that
   has never been test-restored is not a backup" is not just a slogan,
   it is a real difference in confidence).
2. Note its ulid, `type`, `scope`/`scope_id`, and `completed_at`. If
   you need a specific school's data only, look for a `school_export`
   backup scoped to that school, or fall back to a `full` backup and
   extract just that school during Section 4.
3. If the most recent backup is not `verified`, run
   `php artisan serp:run-restore-test` first (or trigger "Run restore
   test" from `/backups/{ulid}`) and read its result before proceeding
   — don't restore from a backup you have active reason to doubt.

## 4. Physical restore into an isolated environment (always do this first — even in a real incident)

This system's restore test proves the *backup's contents* are intact;
it does not prove they can actually be turned back into a running,
queryable database. Do that here, into an environment that is **not**
the environment that needs the data restored, before touching anything
that is:

1. Provision a scratch database (a throwaway MySQL/PostgreSQL instance
   or schema — never reuse a real environment's database for this).
2. Decrypt and parse the backup. There is no `artisan` command for this
   yet — do it via `tinker` or a one-off script, mirroring exactly what
   `RunRestoreTestAction` does:
   ```php
   $backup = \Modules\Core\Models\Backup::find($id);
   $encrypted = Storage::disk($backup->disk)->get($backup->path);
   $json = $backup->is_encrypted ? Crypt::decryptString($encrypted) : $encrypted;
   $payload = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
   // $payload['tables'][$tableName] is an array of row arrays.
   // $payload['files'][$fileUlid] is ['original_name', 'mime_type', 'school_id', 'contents_base64'].
   ```
3. Load `$payload['tables']` into the scratch database, one table at a
   time, in FK-safe order (tenants → schools → academic_years → terms →
   everything else is a reasonable default order; consult
   `Modules\Core\Domain\Registry\TenantModelRegistry` for the full list
   of tenant-scoped models a `school_export` backup covers).
4. If restoring `files`, base64-decode each entry's `contents_base64`
   and write it to the scratch environment's file storage at the same
   relative path the original `File` row's `path` column recorded.
5. **Verify, against the scratch environment, not by assertion:**
   - Row counts per table match `$payload['manifest']['tables']`.
   - The application boots against the scratch database and a handful
     of core screens load (a school's dashboard, a learner record, a
     recent invoice).
   - If the backup includes Finance data: the trial balance actually
     balances (`journal_lines` debits = credits) — this is the one
     check `RunRestoreTestAction`'s own docblock explicitly defers to
     FIN-01, and BR-CORE-13-003 requires it. Run FIN-01's trial-balance
     report/integrity check against the scratch environment directly.
6. Record the outcome. If this step fails, stop — do not proceed to
   Section 5 with a backup that didn't actually restore cleanly, no
   matter how urgent the incident is; escalate instead.

## 5. Restoring into production (dual authorisation — BR-CORE-13-009)

Only after Section 4 has genuinely succeeded against a scratch
environment:

1. **Request.** From `/backups/{ulid}`, click "Request production
   restore" and give a real reason — this calls
   `RequestProductionRestoreAction`, creating a `restore_tests` row at
   `pending_approval`.
2. **Approve.** A **different** person (never the requester —
   `ApproveProductionRestoreAction` enforces this) reviews the request
   and Section 4's rehearsal evidence, then approves it from the same
   screen.
3. **Execute — manually, for now.** Nothing in this codebase executes
   a production restore automatically; this build has no provisioned
   mechanism to safely swap a live production database out from under
   a running application. Until that exists:
   - Put the application into maintenance mode first
     (`/scheduling/maintenance` or `php artisan down --with-secret`) —
     BR-CORE-12-008. Nobody should be able to write to the database
     while it's mid-restore.
   - Follow Section 4's own procedure again, this time against the
     real production database, by an operator who has direct,
     supervised access to it.
   - Bring the application back up (`/scheduling/maintenance` or
     `php artisan up`) once the restored data has passed the same
     verification checks as Section 4, step 5.
4. Record the approver, the executor, and the outcome against the
   `restore_tests` row (its `checks_performed`/`error` columns, or at
   minimum in this incident's own postmortem).

## 6. After any restore (rehearsal or real)

1. Confirm `RestoreVerificationHealthCheck`/`BackupFreshnessHealthCheck`
   on `/scheduling/health` reflect the restore accurately.
2. If this was a real incident, write a postmortem: what triggered it,
   which backup was used, how long the restore actually took end to
   end (Section 3 through Section 5), and what — if anything — should
   change in this runbook or the automation around it as a result.
3. If this was a rehearsal, fill in the rehearsal log at the top of
   this document.
