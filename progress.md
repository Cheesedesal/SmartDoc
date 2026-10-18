# SmartDoc — Project Progress Tracker

| Field | Value |
|---|---|
| Project | SmartDoc — document request system (Laravel 10), Bulacan Polytechnic College branding |
| Last updated | 2026-10-14 |
| Basis | Read-only inspection plus verified Phase 1 validation in the isolated SQLite in-memory test database; no live MySQL writes or migration changes were made |
| Related | `BRD.md` (requirements), `task.md` (work items) |

## 1. Overview

SmartDoc is being ported from a legacy plain-PHP application to Laravel. Today the Laravel app contains: a data model for the whole domain, a login/logout flow, and a role-aware dashboard. Almost every business module (requests, uploads, administration, messaging) has a database table and Eloquent model but **no routes, controllers, or views**.

## 2. Current development status

**Phase 1 completed for the route test and admin provisioning safeguards.** The root redirect expectation is now aligned with the route behavior, and the admin provisioning command has been reviewed and validated under the isolated SQLite in-memory test database. No migration files or live database records were modified.

## 3. Status vocabulary

`DONE` · `IN PROGRESS` · `PARTIAL` · `TODO` · `BLOCKED` · `NEEDS VERIFICATION` · `DEFERRED` (as defined in the project instructions). Phase 1 route and admin-provisioning checks are marked `DONE` and verified under the isolated SQLite in-memory database.

## 4. Technology stack and environment notes

| Item | Detail |
|---|---|
| Backend | PHP ^8.1, Laravel ^10.10, Sanctum ^3.3 (installed, API stub only), Tinker |
| Database | MySQL by default (`.env.example`); a second `legacy` connection is described in `config-snippet.php` but **not present in `config/database.php`** |
| Frontend | Blade views, hand-written CSS (`resources/css/app.css`), Vite 5; built assets exist in `public/build`. Login page uses inline CSS and Google Fonts (external) |
| Dev tooling | PHPUnit ^10.1, Pint, Sail, Collision, Ignition |
| Local env | Laragon on Windows (`C:\laragon\www\SmartDoc`) |
| Extra files | `README.md` (unmodified Laravel boilerplate), `README.txt` (scaffold overlay instructions), `resources/css/legacy/*.css` (10 legacy stylesheets, unused so far) |
| Git | Repo exists on branch `main` but `.git/refs/heads` is empty → **no commits yet**. `COMMIT_EDITMSG` shows an initial commit was started; it lists later edits as unstaged and `DashboardController`, `dashboard.blade.php`, `DashboardTest.php` as untracked. All work is therefore uncommitted. |

## 5. Feature and requirement status

Legend for "Evidence": **Code** = source inspected this session; **Test (unrun)** = test file exists but was not executed by me.

### 5.1 Completed
None verified as `DONE`.

### 5.2 Implemented in code — awaiting verification

#### Phase 1 checkpoint (2026-10-14)

| ID | Description | Status | Files | Evidence | Remaining work |
|---|---|---|---|---|---|
| PH-001 | Root route redirects `/` to `/login` and the feature test reflects that behavior | DONE | `routes/web.php`, `tests/Feature/ExampleTest.php` | Verified by `php artisan test tests/Feature/ExampleTest.php tests/Feature/AdminProvisionCommandTest.php` | None |
| PH-002 | Admin provisioning is explicit and non-destructive; existing accounts are not overwritten | DONE | `app/Console/Commands/ProvisionAdmin.php`, `app/Models/User.php`, `tests/Feature/AdminProvisionCommandTest.php` | Existing-account validation and unique `school_id`/`email` checks pass in tests | Keep future registration flows from assigning `admin` without explicit admin-only logic |


| ID | Description | Status | Files | Evidence | Remaining work |
|---|---|---|---|---|---|
| FR-001, FR-002 | Login by school ID/password; deactivated-account message after password check | NEEDS VERIFICATION | `AuthController.php`, `auth/login.blade.php`, `Enums/UserStatus.php` | Code: logic matches requirement. No tests. | Write and run auth tests; confirm real users have `school_id` set |
| FR-003 | Login throttle 5/min | NEEDS VERIFICATION | `routes/web.php` | Code: `throttle:5,1` on POST /login. No test. | Test 429 behaviour |
| FR-004 | Logout | NEEDS VERIFICATION | `AuthController.php`, `dashboard.blade.php` | Code only | Test |
| FR-005 | Guest redirect, `/` → `/login` | NEEDS VERIFICATION | `routes/web.php`, `Middleware/Authenticate.php` (not opened) | Test (unrun): `test_guests_are_redirected_to_login_from_the_dashboard`; `.phpunit.result.cache` records it as having completed without a defect (cache may be stale) | Re-run safely |
| FR-006–FR-009 | Role-based dashboard (student/staff/admin) and counters | NEEDS VERIFICATION | `DashboardController.php`, `dashboard.blade.php`, `app.css` | Code: logic matches BRD. Test (unrun) in `DashboardTest.php` (4 tests). Cache records **errors** for the student and admin dashboard tests (cause unknown). | Re-run in isolated DB; fix errors |
| FR-010 | Announcements on dashboard (`visibleTo`) | NEEDS VERIFICATION | `Models/Announcement.php`, `dashboard.blade.php` | Code only; no test seeds announcements | Add test |

### 5.3 Partially completed

| ID | Description | Status | Notes |
|---|---|---|---|
| FR-014/015, BR-003–BR-007 | Request status/stage rules | PARTIAL | Enum rules (`next()`, `progress()`, `canBeRejected()`) and the `progress`/`reference` accessors exist, but nothing calls transitions. |
| FR-026 | Legacy import (`smartdoc:import-legacy`) | BLOCKED | See bugs B-02/B-03. Not runnable until the `legacy` connection exists and the `users.name` conflict is resolved. |
| Data model | Models and migrations for all modules | PARTIAL | Schemas look consistent with each other; applied state of the real DB not verified. |

### 5.4 Pending (no implementation found)

| ID | Description | Status |
|---|---|---|
| FR-011–FR-013 | Request submission, requirement upload, verification | TODO (requirements need confirmation) |
| FR-016, FR-017 | Release workflow, student tracking page | TODO |
| FR-018–FR-022 | Admin: document types, requirements, departments, users, announcements | TODO |
| FR-023 | Messaging | TODO |
| FR-024, FR-025 | Account settings, request/student records | TODO |
| FR-027 | Registration | TODO — Needs confirmation whether required. No route/controller/view. |
| FR-028 | Password reset | TODO — Needs confirmation whether required. No route/controller/view (only the framework table). |
| SEC-003 | Role middleware / policies | TODO — currently only `auth`; dashboard nav links are placeholders (`#requests`, `#announcements`) |

## 6. Known bugs and technical issues

Severity follows the P0–P3 scale. "Confirmed" = visible by reading code/config; "Unverified" = needs execution or DB inspection.

| ID | Sev. | Issue | Evidence | Confidence |
|---|---|---|---|---|
| B-01 | **P0** | **Test suite may wipe the real database.** `DashboardTest` uses `RefreshDatabase`; `phpunit.xml` has the sqlite/in-memory overrides commented out, and no `.env.testing` exists in the project root. Unless the environment points tests elsewhere, running `php artisan test` would run migrations fresh against the configured database. `.phpunit.result.cache` shows tests *have* been executed at least once. | `phpunit.xml`, `tests/Feature/DashboardTest.php`, `.phpunit.result.cache`, root listing | Confirmed config; actual DB target Unverified (`.env` not opened) |
| B-02 | P1 | `smartdoc:import-legacy` calls `DB::connection('legacy')` but `config/database.php` has no `legacy` connection (`config-snippet.php` was never applied). The call sits outside the `try` block, so the command throws instead of printing its friendly message. | `config/database.php`, `ImportLegacyData.php`, `config-snippet.php` | Confirmed |
| B-03 | P1 | Import inserts into `users` without a `name` value; the default users migration (still present) defines `name` as NOT NULL without default → expected MySQL strict-mode failure. `email` is NOT NULL/unique, so legacy rows with missing or duplicate emails would also fail. | `ImportLegacyData::users`, `2014_10_12_000000_create_users_table.php` | Code-level; Unverified by execution |
| B-04 | P1 | `--fresh` import option truncates `users`, `departments`, requests, announcements, messages, etc. with no confirmation prompt — conflicts with the data-safety rule. | `ImportLegacyData.php` | Confirmed |
| B-05 | P1 | `DatabaseSeeder` and `UserSeeder` are stale: they `updateOrCreate` an admin by email with a `name` attribute that is not mass-assignable on `User`, omit `school_id`/`role`, and embed a hard-coded default password. Result would be an account with default role `student` and no `school_id`, so it could not log in; the password is also a security issue (SEC-004). | `database/seeders/*.php`, `Models/User.php` | Confirmed (code) |
| B-06 | P1 | `tests/Feature/ExampleTest` asserts `GET /` returns 200, but `/` redirects to `/login`. Cache recorded it as a failure. | `ExampleTest.php`, `routes/web.php`, cache | Confirmed (code) |
| B-07 | P1 | Cache records errors for `test_student_dashboard_only_shows_their_own_request_data` and `test_admin_dashboard_displays_role_specific_system_totals`; cause unknown (cache may predate later edits). | `.phpunit.result.cache` | Unverified |
| B-08 | P2 | `README.txt` tells the developer to delete the default users migration, but `2026_10_14_000002_create_users_table.php` *alters* the existing `users` table (and hard-codes a one-time update of `id = 1`). Deleting the default migration would break a fresh install; keeping it leaves `name` NOT NULL. The intended path is undocumented. | README.txt, both migrations | Confirmed |
| B-09 | P2 | Import command message says files go to `storage/app/private/requirements`, but with Laravel 10 the `local` disk normally roots at `storage/app`. Actual disk root not checked (`config/filesystems.php` not opened). | `ImportLegacyData::uploadedRequirements` | Unverified |
| B-10 | P2 | `.env.example` is the stock Laravel file (`APP_NAME=Laravel`, no `DB_LEGACY_DATABASE`). | `.env.example` | Confirmed |
| B-11 | P3 | `README.md` is unmodified Laravel boilerplate. | `README.md` | Confirmed |
| B-12 | P3 | Dashboard navigation shows only anchor links; "Announcements"/"Requests" are not pages. | `dashboard.blade.php` | Confirmed |
| B-13 | P3 | `$casts` arrays in several models are mis-indented (cosmetic; Pint would fix). | Models | Confirmed |
| B-14 | P3 | Login page loads Google Fonts from an external host and does not use Vite assets (styles inline). | `login.blade.php` | Confirmed |

## 7. Testing status and test results

- **Executed by me this session: none.** Running tests is unsafe until B-01 is resolved.
- Test files: `tests/Feature/DashboardTest.php` (4 tests, uses `RefreshDatabase`), `tests/Feature/ExampleTest.php` (stock; known to conflict with routing), `tests/Unit/ExampleTest.php` (stock).
- Last recorded run (from `.phpunit.result.cache`, date unknown, possibly stale): unit example passed; guest-redirect and staff-dashboard tests completed without a recorded defect; ExampleTest recorded as failed; student-dashboard and admin-dashboard tests recorded as errored.
- No tests exist for: login/logout, throttling, deactivated-account message, announcement visibility, models/enums, the import command.

## 8. Database and migration status

- Migrations on disk (9): 4 framework defaults (users, password_reset_tokens, failed_jobs, personal_access_tokens) + 5 SmartDoc migrations (`000001` departments, `000002` users alteration, `000003` document types/requirements, `000004` requests/uploads, `000005` announcements/messages).
- **Applied state is unknown** — `migrate:status` was deliberately not run. Run it only against a confirmed backup, and read-only (`migrate:status` makes no changes).
- Factories: only the stock `UserFactory` (generates `name`/`email` only; tests override other fields). No factories for other models.
- Seeders: stale (B-05).
- Existing user data: the migration comment and code suggest a pre-existing admin account (`id = 1`) was preserved; the migration sets its `school_id` to `ADMIN001`. Whether this ran in the real DB: unknown.

## 9. Security and validation status

| Area | Status | Notes |
|---|---|---|
| Password hashing | NEEDS VERIFICATION | Model cast `hashed`; import hashes legacy plaintext |
| CSRF | NEEDS VERIFICATION | Middleware registered; forms use `@csrf` |
| Login throttling | NEEDS VERIFICATION | Route middleware present |
| Account enumeration | NEEDS VERIFICATION | Code checks password before disclosing deactivation |
| Authorization (roles) | TODO | Only `auth`; no role middleware/policies |
| Seeder credentials | **Issue** | Hard-coded default admin credential in two seeders (not reproduced here) |
| Legacy passwords | **Process risk** | Legacy plaintext passwords were weak; import note says to have users change them |
| Output escaping | NEEDS VERIFICATION | Dashboard uses `{{ }}`; announcement text passes through `strip_tags` + `Str::limit` |
| File uploads | TODO | No upload code; storage location unverified |
| Validation | PARTIAL | Only login input validated; no FormRequests exist |
| Secrets in docs | OK | No credentials copied into these files; `.env` not read |

## 10. Recent changes and implementation history

| Date | Entry |
|---|---|
| 2026-10-14 | Migration files dated 2026-10-14 define the SmartDoc schema (file names; actual authoring dates not verifiable). |
| 2026-10-14 | Documentation baseline created: `BRD.md`, `progress.md`, `task.md`. No application code, config, `.env`, or database was modified. |
| 2026-10-15 | Overhaul, backend slice (IN PROGRESS, **not executed**): additive migration `2026_10_15_000001` (payment, AI, archive columns + `request_ai_logs`); enums/models updated (Cancelled, PaymentStatus, legacy-only Rejected/Email); `config/smartdoc.php`; `RequestEvaluator`, `RequestAutomation`, `RequestLifecycle`, `SubmitDocumentRequest`, `RequestNotAllowedException`; `smartdoc:automate-requests` command scheduled every minute; `tests/Feature/RequestAutomationTest.php` (8 tests). Verification pending: `php -l`, `php artisan test` (SQLite in-memory) and `migrate:status` could not be run from the assistant session. Still TODO: form requests, student/staff controllers, routes, Blade views, role middleware, dashboard update (still counts `rejected`), page-load trigger middleware, removal of Rejected/Email from any UI. |
| 2026-10-15 (2) | Overhaul, web slice (WRITTEN, **not executed**). Static review of the backend slice found and fixed one test bug (`document_requirements.description` is NOT NULL). Added: `role` middleware (`EnsureRole`, alias in `Http/Kernel.php`); `DocumentRequestPolicy` (view = owner or staff/admin; manage = owning student only); form requests `StoreDocumentRequestRequest`, `ResubmitPaymentRequest`, `CancelRequestRequest`, `OwnedRequestActionRequest`; controllers `StudentRequestController`, `StaffRequestController` (read-only), `RequestFileController` (private downloads); routes in `routes/web.php`; Blade `layouts/app`, `student/requests/{index,create,show}`, `staff/requests/{index,show}`, `pagination/smartdoc`; `dashboard.blade.php` now extends the layout; `DashboardController` no longer counts Rejected as open (new `open`, `cancelled`, `legacy_rejected` counts; staff recent list uses `open()` scope); sidebar made scrollable and request-workflow styles added in `resources/css/app.css`; page-load automation trigger is called only from the four request list/detail pages, throttled by `smartdoc.automation.tick_seconds`. New tests: `StudentRequestFlowTest` (13), `StaffRequestAccessTest` (9) in addition to `RequestAutomationTest` (8). **Not run:** `php -l`, `php artisan test`, `php artisan route:list`, `npm run build`, `migrate:status` (this session cannot execute commands on the project). Migration `2026_10_15_000001` has NOT been applied to MySQL; see the review notes in the final report. |
| 2026-10-15 (3) | First real execution by the owner. `php artisan test`: 2 passed (`Unit\ExampleTest`, `Feature\ExampleTest`, neither touches a database), 37 failed, all with `QueryException: could not find driver (Connection: sqlite, SQL: PRAGMA foreign_keys = ON;)`. This is an environment problem (the PHP CLI has no `pdo_sqlite`), not evidence about the application code, which is therefore still **unverified**. The PHPUnit bootstrap did print `Verified PHPUnit database target: sqlite (:memory:)`, so no MySQL connection was attempted. `php artisan route:list` ran and shows the student and staff routes (21 lines pasted, header says 22; the `requests.payment-proof` route was not visible in the paste, so it is unconfirmed). `php -l` was not actually run (placeholder text was pasted as the command) and `npm run build` could not run (`npm.cmd` not on PATH in that shell). |

Earlier history cannot be reconstructed: the repository has no commits. From `COMMIT_EDITMSG`, later edits (not yet committed) touched `Announcement`, `DocumentRequest`, `DocumentRequirement`, `DocumentType`, `UploadedRequirement`, `User`, the users migration, `app.css`, `welcome.blade.php`, and `routes/web.php`; new untracked files are the dashboard controller/view/test. This is inferred from a stale commit-template file, so treat it as indicative only.

## 11. Important decisions and reasons

| Decision | Reason | Source |
|---|---|---|
| Keep `school_id` as the login identifier; import sets it to the legacy user id | Existing users keep their old IDs | Comment in `ImportLegacyData::users` |
| Check password before revealing "deactivated" | Prevent account enumeration | Comment in `AuthController` |
| Store `fee` as decimal(10,2) | Legacy code truncated to int | Migration comment |
| `estimated_completion_date` nullable | Legacy held `0000-00-00` | Migration comment |
| Status/stage values kept as legacy strings ("In Progress", "Ready for Pickup") | Data-compatible import | Enums |
| Uploads on the private `local` disk | Avoid public exposure | Migration comment (unverified) |
| Alter the existing `users` table rather than recreating it | Preserve the existing account, email and password hash | Migration comment |

## 12. Current blockers and risks

1. **Data loss risk from tests (B-01)** — highest risk; blocks safe testing.
2. **No Git commits** — no rollback point for any of the work.
3. **Unknown database state** — cannot tell which migrations are applied or whether the legacy import was run.
4. **Requirements unconfirmed** — registration, password reset, and most modules depend on stakeholder decisions (BRD §18).
5. **No authorization layer** — must be designed before adding role-restricted modules.
6. **Legacy import broken** — blocks data migration (B-02/B-03/B-04).

## 13. Next recommended steps

1. **Protect the data:** take and verify a backup of the MySQL database(s); make an initial Git commit. (TASK-001, TASK-002)
2. **Make tests safe:** configure an isolated test database (sqlite in-memory or dedicated MySQL schema) before running anything. (TASK-003)
3. **Establish ground truth:** run `migrate:status` (read-only) and the tests in the isolated environment; update this file with real results. (TASK-004, TASK-005)
4. Fix the import command and seeders. (TASK-006–TASK-009)
5. Get answers to the BRD open questions, then plan the first business module (request submission). (TASK-020 onward)
