# SmartDoc — Task List and Development Roadmap

| Field | Value |
|---|---|
| Last updated | 2026-10-14 |
| Basis | Phase 1 verification under the isolated SQLite in-memory test database; no live MySQL writes or migration changes were made |
| Related | `BRD.md` (requirement IDs), `progress.md` (evidence and bug IDs) |

**Conventions**

- Priorities: `P0` critical security/data-integrity/blocking · `P1` essential or major defect · `P2` important/incomplete · `P3` optional.
- Statuses: `TODO`, `IN PROGRESS`, `BLOCKED`, `NEEDS CONFIRMATION` (requirement not yet approved), `DONE`, `DEFERRED`.
- `[ ]` not complete · `[x]` complete **and** verified (record evidence in `progress.md`).
- **Defects** (confirmed problems) and **Proposed work** (features/improvements) are listed separately.
- Safety rule for every task: never run `migrate:fresh`, `db:wipe`, `--fresh` imports, or destructive seeders against the existing database; back up first (TASK-001).

## Roadmap

| Phase | Goal | Tasks |
|---|---|---|
| 0 — Safety | Protect data, isolate tests, create rollback point | 001–004 |
| 1 — Stabilise | Fix confirmed defects; get a trustworthy test baseline | 005–010 |
| 2 — Confirm | Resolve BRD open questions | 020 |
| 3 — Core features | Request workflow and admin modules | 021–031 |
| 4 — Quality | Auth tests, authorization, UX, docs | 011, 040–045 |

---

## A. Confirmed defects and safety issues

### Security and database safety

- [ ] **TASK-001 — Back up the database(s) before further work** · **P0** · TODO
  - Description: Create and verify a backup of the SmartDoc database (and `smartdoc_legacy` if present). Risk context: B-01, unknown DB state.
  - BRD: NFR-003 · Files: none (MySQL via Laragon tools) · Dependencies: none
  - Acceptance: A dated dump exists outside the project folder and restores successfully into a scratch schema; row counts of key tables match.
  - Verification: Restore into a temporary database; compare `COUNT(*)` for `users`, `document_requests`; record result in `progress.md`.

- [ ] **TASK-002 — Create the initial Git commit** · P1 · TODO
  - Description: The repo has no commits; all work is uncommitted. Commit the current tree (excluding `.env`, `vendor`, `node_modules`, already ignored) so there is a rollback point. Review untracked/modified files first.
  - BRD: NFR-003 · Files: whole repo · Dependencies: none (do after confirming no secrets are staged)
  - Acceptance: `git log` shows ≥1 commit; `git status` clean; `.env` not tracked.
  - Verification: `git log --oneline`, `git ls-files | findstr /i "\.env$"` returns only `.env.example`.

- [x] **TASK-003 — Isolate the test database (fix B-01)** · **P0** · DONE
  - Description: Configure tests to use sqlite `:memory:` (uncomment the `phpunit.xml` env lines) or a dedicated MySQL test schema via `.env.testing`, so `RefreshDatabase` can never touch the real database. Optionally add a guard in `TestCase` that aborts if the connected database name is not a test database.
  - BRD: NFR-003, NFR-007 · Files: `phpunit.xml`, `tests/TestCase.php`, optional `.env.testing` (no secrets committed) · Dependencies: TASK-001
  - Acceptance: Running the suite does not read or write the development database; the guard aborts if misconfigured.
  - Verification: With the dev DB backed up, run the suite and compare row counts before/after (unchanged); temporarily point to the dev DB and confirm the guard aborts.

- [ ] **TASK-004 — Audit applied migration state (read-only)** · P1 · TODO
  - Description: Run `php artisan migrate:status` (read-only) and inspect the real `users` table columns to learn which migrations ran and whether the legacy import was performed. Record findings.
  - BRD: §18 Q10 · Files: none · Dependencies: TASK-001
  - Acceptance: `progress.md` §8 lists applied/pending migrations and whether `users` has `school_id`, `role`, `status`, `name`.
  - Verification: Paste `migrate:status` summary (no secrets) into `progress.md`.

- [ ] **TASK-008 — Add safety guard to `--fresh` import (B-04)** · P1 · TODO
  - Description: Require explicit confirmation (typed database name or `--force`) and refuse when the target DB already has non-seed data unless an environment flag permits; print row counts that would be erased.
  - BRD: FR-026, NFR-003 · Files: `app/Console/Commands/ImportLegacyData.php` · Dependencies: TASK-006
  - Acceptance: `--fresh` without confirmation exits without truncating; with confirmation it behaves as before.
  - Verification: Test against an isolated DB; assert tables unchanged after a declined prompt.

- [ ] **TASK-009 — Repair seeders and remove hard-coded credentials (B-05)** · P1 · TODO
  - Description: Replace stale `DatabaseSeeder`/`UserSeeder` (non-fillable `name`, missing `school_id`/`role`, default password) with seeders that set `school_id`, `role=admin`, `status=activated`, and read the initial password from configuration/prompt (not source). Seeders must be non-destructive (insert-if-missing).
  - BRD: SEC-004 · Files: `database/seeders/DatabaseSeeder.php`, `UserSeeder.php`, possibly `UserFactory.php` · Dependencies: TASK-003, TASK-007
  - Acceptance: No literal password in the repo; seeding on an isolated DB creates an admin who can log in; re-running creates no duplicates.
  - Verification: Seed an isolated DB, log in via feature test, grep repo for the old literal.

### Core system / import

- [ ] **TASK-006 — Make the legacy import runnable (B-02)** · P1 · TODO
  - Description: Add the `legacy` connection to `config/database.php` (per `config-snippet.php`, using env variables), add `DB_LEGACY_DATABASE` to `.env.example`, and move `DB::connection('legacy')` inside the `try` so a missing connection prints the friendly error.
  - BRD: FR-026 · Files: `config/database.php`, `.env.example`, `ImportLegacyData.php` · Dependencies: TASK-001 (never edit `.env` secrets; user adds `DB_LEGACY_DATABASE` locally)
  - Acceptance: With the legacy DB present, the command connects; without it, it exits with a clear message and no exception.
  - Verification: Run against an isolated target DB; check imported row counts equal legacy counts.

- [ ] **TASK-007 — Resolve `users.name` / `email` conflict (B-03)** · P1 · NEEDS CONFIRMATION
  - Description: Decide whether `users.name` is dropped (new migration), made nullable, or populated as firstname + surname; decide whether `email` must be NOT NULL/unique for legacy users. Update import code and factory accordingly.
  - BRD: §18 Q11–Q12 · Files: new migration, `ImportLegacyData::users`, `UserFactory`, `User` · Dependencies: TASK-004, owner decision
  - Acceptance: Import of the legacy users (including users with blank/duplicate email, if any) succeeds under MySQL strict mode.
  - Verification: Run import against an isolated copy; assert all legacy users present and able to log in.

### Testing and quality assurance

- [x] **TASK-005 — Establish a trustworthy test baseline (B-06, B-07)** · P1 · DONE
  - Description: After TASK-003, run the suite, diagnose the errors recorded for the student/admin dashboard tests, and update `ExampleTest` to expect the redirect from `/`. Remove or replace stock tests.
  - BRD: NFR-007 · Files: `tests/Feature/*.php`, `tests/Unit/ExampleTest.php` · Dependencies: TASK-003
  - Acceptance: All tests pass in the isolated environment; results recorded with date and command.
  - Verification: `php artisan test tests/Feature/ExampleTest.php tests/Feature/AdminProvisionCommandTest.php` passed (3 tests, 25 assertions).

- [ ] **TASK-011 — Add authentication tests** · P1 · TODO
  - Description: Cover activated login, wrong password, unknown ID, deactivated+correct password message, deactivated+wrong password generic message, throttle (429 on 6th), logout, guest redirects.
  - BRD: FR-001–FR-005, acceptance table §17 · Files: new `tests/Feature/AuthTest.php` · Dependencies: TASK-003
  - Acceptance: Tests exist for every listed case and pass.
  - Verification: Run suite; mutate `AuthController` temporarily to confirm tests fail when behaviour breaks.

- [ ] **TASK-012 — Add announcement-visibility and enum/model tests** · P2 · TODO
  - Description: Test `Announcement::visibleTo` (draft, expired, other audience, `all`), `RequestStage`/`RequestStatus` rules, `DocumentRequest::progress`/`reference`.
  - BRD: FR-010, BR-003–BR-008 · Files: new tests · Dependencies: TASK-003
  - Acceptance: Each rule has at least one passing test.
  - Verification: Run suite.

### Documentation and deployment

- [ ] **TASK-043 — Document the users-table migration path (B-08)** · P2 · TODO
  - Description: Replace the contradictory `README.txt` step ("delete default users migration") with accurate instructions for (a) existing DB and (b) fresh install, after TASK-004/TASK-007 clarify behaviour.
  - Files: `README.txt` / new project README · Dependencies: TASK-004, TASK-007
  - Acceptance: A new developer can set up both scenarios from the docs alone.
  - Verification: Dry-run the steps on a scratch machine/database.

- [ ] **TASK-044 — Verify upload storage disk and root (B-09)** · P2 · TODO
  - Description: Read `config/filesystems.php`; confirm where the `local` disk writes and correct the import command's note and any future upload code.
  - BRD: NFR-006, SEC-006 · Files: `config/filesystems.php`, `ImportLegacyData.php` · Dependencies: none
  - Acceptance: Message and code agree with the real disk root; files are not web-accessible.
  - Verification: Import one test file to an isolated environment; confirm location and that it is not under `public/`.

- [ ] **TASK-045 — Replace boilerplate docs and `.env.example` (B-10, B-11)** · P3 · TODO
  - Description: Write a SmartDoc `README.md` (setup, tests, safety rules, docs index); set `APP_NAME=SmartDoc` and add `DB_LEGACY_DATABASE` in `.env.example`.
  - Files: `README.md`, `.env.example` · Dependencies: TASK-006
  - Acceptance: README describes the real project; `.env.example` contains no secrets.
  - Verification: Read-through; grep for secrets.

### User interface

- [ ] **TASK-040 — Wire dashboard navigation (B-12)** · P3 · TODO
  - Description: Replace `#requests`/`#announcements` anchors with real routes as modules are built; hide links for roles that cannot use them.
  - Files: `dashboard.blade.php` · Dependencies: Phase 3 tasks
  - Acceptance: Every nav item opens a working page for permitted roles only.
  - Verification: Feature tests per role.

- [ ] **TASK-041 — Align login page with the asset pipeline (B-14)** · P3 · TODO
  - Description: Move login CSS to the Vite pipeline and self-host or optionally drop the Google Fonts dependency.
  - Files: `auth/login.blade.php`, `resources/css/app.css` · Dependencies: none
  - Acceptance: Login renders identically offline; no external requests.
  - Verification: Load page with network disabled.

- [ ] **TASK-042 — Run Pint formatting (B-13)** · P3 · TODO
  - Description: Normalise code style (e.g. model `$casts` indentation).
  - Files: `app/**` · Dependencies: TASK-002 (commit first)
  - Acceptance: `vendor/bin/pint --test` passes. Verification: run it.

---

## B. Proposed work (features and improvements)

Features marked **NEEDS CONFIRMATION** come from schema/legacy evidence only; confirm each with the owner (TASK-020) before building.

- [ ] **TASK-020 — Confirm requirements with the business owner** · P1 · TODO
  - Description: Walk through BRD §18, especially: registration, password reset, department scoping, fees, which legacy modules to port.
  - BRD: §18 · Files: `BRD.md` · Dependencies: none
  - Acceptance: Answers recorded in `BRD.md` with date; affected tasks re-prioritised.
  - Verification: Owner sign-off noted in `BRD.md` change log.

- [ ] **TASK-010 — Role-based authorization layer** · P1 · TODO
  - Description: Add role middleware/policies (`hasRole` exists on `User`) and apply to each new module; students may access only their own requests/uploads/messages.
  - BRD: SEC-003 · Files: `Http/Kernel.php` (alias), policies, routes · Dependencies: TASK-003
  - Acceptance: Cross-role and cross-student access returns 403/404 in tests.
  - Verification: Feature tests per role and per resource.

### Core system functionality (all need confirmation unless noted)

- [ ] **TASK-021 — Student: submit a document request** · P1 · NEEDS CONFIRMATION
  - Description: List active document types and requirements, validate input (FormRequest), create request with status Pending / stage Request Received.
  - BRD: FR-011 · Files: new controller, FormRequest, views, routes · Dependencies: TASK-010, TASK-020
  - Acceptance: Valid request creates a `REQ-###` record owned by the student; invalid input shows errors; inactive types cannot be requested.
  - Verification: Feature tests; manual walkthrough on an isolated DB.

- [ ] **TASK-022 — Requirement upload and verification** · P1 · NEEDS CONFIRMATION
  - Description: Students upload one file per requirement (type/size validation, private disk); staff verify. Authorized download only.
  - BRD: FR-012, FR-013, SEC-006 · Dependencies: TASK-021, TASK-044
  - Acceptance: Unique upload per requirement; non-owner non-staff cannot download; verification flag toggled by staff only.
  - Verification: Feature tests incl. unauthorized access.

- [ ] **TASK-023 — Staff request queue and detail view** · P1 · NEEDS CONFIRMATION
  - Description: Paginated queue (open requests), detail page, filters.
  - BRD: FR-008, FR-025 · Dependencies: TASK-010, TASK-021
  - Acceptance: Staff see all open requests; students cannot access the queue. Verification: tests.

- [ ] **TASK-024 — Approve and advance stages** · P1 · NEEDS CONFIRMATION
  - Description: Use `RequestStage::next()`; record `approved_by/at`, `current_processed_by`, estimated completion date (from `processing_days`).
  - BRD: FR-014, BR-006 · Dependencies: TASK-023
  - Acceptance: Only valid transitions succeed; timestamps/actors recorded. Verification: unit + feature tests.

- [ ] **TASK-025 — Reject request** · P1 · NEEDS CONFIRMATION
  - Description: Enforce `canBeRejected()`; require reason; record actor/time.
  - BRD: FR-015, BR-005 · Dependencies: TASK-023
  - Acceptance: Rejection blocked for Completed/Rejected/Ready for Pickup. Verification: tests.

- [ ] **TASK-026 — Release / complete request** · P1 · NEEDS CONFIRMATION
  - Description: Mark Ready for Pickup → Released/Completed with `released_by/at`, `completed_at`.
  - BRD: FR-016 · Dependencies: TASK-024
  - Acceptance: Status/stage/timestamps consistent. Verification: tests.

- [ ] **TASK-027 — Student request tracking page** · P2 · NEEDS CONFIRMATION
  - Description: List/detail with progress bar from `progress` accessor.
  - BRD: FR-017 · Dependencies: TASK-021
  - Acceptance: Student sees only own requests with correct progress. Verification: tests.

- [ ] **TASK-028 — Admin: document types and requirements** · P2 · NEEDS CONFIRMATION
  - BRD: FR-018, FR-019, BR-009, BR-010 · Dependencies: TASK-010
  - Acceptance: CRUD with validation (unique names, decimal fee); deactivated types hidden from students. Verification: tests.

- [ ] **TASK-029 — Admin: departments and users** · P2 · NEEDS CONFIRMATION
  - BRD: FR-020, FR-021, BR-012, BR-013 · Dependencies: TASK-010, TASK-007
  - Acceptance: Admin can create/edit/activate/deactivate; activation affects login. Verification: tests.

- [ ] **TASK-030 — Admin: announcements** · P2 · NEEDS CONFIRMATION
  - BRD: FR-022, BR-008, BR-014 · Dependencies: TASK-010
  - Acceptance: Created announcements appear on correct dashboards only. Verification: tests.

- [ ] **TASK-031 — Student–staff messaging** · P3 · NEEDS CONFIRMATION
  - BRD: FR-023 · Dependencies: TASK-010
  - Acceptance: Only the two participants can read a thread. Verification: tests.

- [ ] **TASK-032 — Account settings; request and student records** · P3 · NEEDS CONFIRMATION
  - BRD: FR-024, FR-025 · Dependencies: TASK-010, TASK-023
  - Acceptance: To be defined after TASK-020. Verification: To be defined.

### Authentication and account management (decisions required)

- [ ] **TASK-013 — Registration (only if confirmed)** · P2 · NEEDS CONFIRMATION
  - Description: Implement self-registration with new accounts `deactivated` until admin activation, or record that accounts are admin-created only.
  - BRD: FR-027, REG-001/002 · Dependencies: TASK-020, TASK-007
  - Acceptance: Per the confirmed decision; no account can log in until activated. Verification: tests.

- [ ] **TASK-014 — Password reset (only if confirmed)** · P2 · NEEDS CONFIRMATION
  - Description: Implement email or admin-assisted reset using the existing `password_reset_tokens` table; requires mail configuration and a unique, non-null email per user.
  - BRD: FR-028, PWR-001/002 · Dependencies: TASK-020, TASK-007
  - Acceptance: Reset link/flow works once and expires; throttled; generic responses. Verification: tests with fake mail.

- [ ] **TASK-015 — Force password change for imported legacy users** · P2 · NEEDS CONFIRMATION
  - Description: Legacy passwords were weak and plaintext; require change at first login after import.
  - BRD: SEC-008 · Dependencies: TASK-006, TASK-029
  - Acceptance: Imported users are redirected to change password before using the app. Verification: feature test.

---

## Dependency summary

`001 → 002, 003, 004` · `003 → 005, 009, 010, 011, 012` · `004 → 007, 043` · `006 → 008, 015, 045` · `007 → 009, 013, 014, 029, 043` · `010 → 021–031` · `020 → 013, 014, 021…` · `021 → 022, 023, 027` · `023 → 024, 025` · `024 → 026`
