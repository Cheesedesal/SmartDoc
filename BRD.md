# SmartDoc — Business Requirements Document (BRD)

| Field | Value |
|---|---|
| Document version | 0.1 (initial, evidence-based draft) |
| Last updated | 2026-10-14 |
| Basis | Read-only inspection of `C:\laragon\www\SmartDoc` (code, migrations, views, tests, config). No stakeholder input yet. |
| Related documents | `progress.md` (verified status), `task.md` (work items) |

## How to read this document

Every requirement carries an **evidence tag**:

- **[C] Confirmed** — the behaviour is visible in the current code. This confirms the *implementation*, not that the business approved it.
- **[I] Inferred** — derived from the database schema, enums, import command, or legacy CSS file names. Probably intended, but **Needs confirmation**.
- **[R] Recommendation** — suggested by this review; not a stated requirement.

Nothing in this document has been approved by a business owner. Items marked `Needs confirmation` must be confirmed before they are treated as binding. "Implemented" in the **Current state** column means the code exists; see `progress.md` for whether it is verified.

---

## 1. Project overview

SmartDoc is a Laravel 10 web application for requesting and processing school documents. The UI branding refers to **Bulacan Polytechnic College** and the caption "AI Document Request System" [C]. The project appears to be a **port of an earlier plain-PHP SmartDoc application** to Laravel: code comments reference "legacy login.php", "legacy completeCurrentStage()", "legacy rejectRequest()", and "legacy RMFunctions" [C]. The legacy database is named `smartdoc_legacy` [C].

Whether the "AI" in the caption corresponds to any AI feature: **Needs confirmation** (no AI-related code was found).

## 2. Business background and problem statement

**Inferred [I]:** Students of the institution need to request official documents (e.g. enrollment verification, as used in the test data). Staff process the requests through stages, and administrators manage users, departments, document types, and announcements. SmartDoc replaces manual or earlier-system handling with a tracked, role-based workflow.

The exact business problem, volumes, and pain points: **Needs confirmation**.

## 3. Project objectives

| ID | Objective | Evidence |
|---|---|---|
| OBJ-01 | Provide secure, role-based access for students, staff, and administrators | [C] Role enum, auth flow |
| OBJ-02 | Let students submit and track document requests | [I] `document_requests` schema; dashboard progress logic |
| OBJ-03 | Let staff process requests through defined stages and statuses | [I] `RequestStage`/`RequestStatus` enums |
| OBJ-04 | Let administrators configure users, departments, document types and requirements | [I] schema; legacy CSS names; admin dashboard counts |
| OBJ-05 | Communicate through announcements and student–staff messages | [I] schema; [C] announcements shown on dashboard |
| OBJ-06 | Migrate existing legacy data into the new schema without loss | [C] `smartdoc:import-legacy` command exists |

## 4. Scope of the system

SmartDoc is a server-rendered Laravel/Blade web application using MySQL (default), session authentication, and a Vite-built stylesheet. A Sanctum API stub exists but is unused [C].

## 5. In-scope features

Status key (current implementation): **Implemented** = code present; **Schema only** = tables/models exist but no controller/route/view; **None** = nothing found.

| Feature | Evidence tag | Current state |
|---|---|---|
| Login by school ID + password, logout | [C] | Implemented |
| Role-aware dashboard (student / staff / admin) | [C] | Implemented |
| Announcements visible by audience on dashboard | [C] | Implemented (read-only) |
| Document request submission, tracking, staged processing | [I] | Schema + models only |
| Requirement upload and verification | [I] | Schema + models only |
| Request rejection / release / completion | [I] | Enum rules only |
| Document type and requirement configuration | [I] | Schema + models only |
| Department management | [I] | Schema + model only |
| User management | [I] | Schema + model only |
| Announcement management | [I] | Schema + model only |
| Student–staff messaging | [I] | Schema + model only |
| Account settings, request records, student records | [I] (legacy CSS names only) | None |
| Legacy data import | [C] | Command exists; not runnable as configured (see `progress.md`) |

## 6. Out-of-scope features

No scope decision has been recorded for future work, but this project now explicitly approved the following in-scope behavior for this implementation phase: public self-registration for student accounts only, with new accounts created as `pending` until an administrator approves and activates them. The following remain out of scope unless separately approved: password reset, email verification, payment processing, public API, AI-assisted features.

## 7. User roles and responsibilities

Roles **[C]** (`App\Enums\Role`): `student`, `staff`, `admin`.

| Role | Verified in code | Intended responsibility [I] — Needs confirmation |
|---|---|---|
| Student | Sees only own requests and student-audience announcements on dashboard | Submit and track own requests, upload requirements, message staff |
| Staff | Sees non-completed/non-rejected request queue (all students) | Review, process, verify, reject, release requests; message students |
| Admin | Sees system totals (users, students, staff, active users, departments, document types) | Manage users, departments, document types, announcements |

User attributes **[C]**: `school_id`, `firstname`, `surname`, `email`, `department_id`, `course`, `year`, `section`, `position`, `role`, `status` (`pending`, `activated`, `deactivated`).

Public self-registration is approved for student accounts only. Newly registered student accounts are created in `pending` status and cannot log in until an administrator changes their status to `activated`. Public registration may never assign `role=admin` or a privileged `status` value.

Whether staff are scoped to departments or can see all requests: **Needs confirmation** (dashboard currently shows all open requests to all staff).

## 8. Functional requirements

Columns: **Current state** = what the code does today. **Req. status** = whether the requirement itself is confirmed.

| ID | Requirement | Tag | Current state |
|---|---|---|---|
| FR-001 | A user logs in with school ID and password; only `activated` accounts may authenticate. | [C] | Implemented (`AuthController::login`) |
| FR-002 | A deactivated user who supplies the correct password sees a "not activated by the admin" message; wrong passwords receive the generic "Invalid ID or password" (no account enumeration). | [C] | Implemented |
| FR-003 | Login attempts are throttled (5 per minute per the route middleware). | [C] | Implemented (`throttle:5,1`) |
| FR-004 | A logged-in user can log out; session is invalidated and CSRF token regenerated. | [C] | Implemented |
| FR-005 | Guests are redirected to login when requesting protected pages; `/` redirects to `/login`. | [C] | Implemented |
| FR-006 | After login, the dashboard content depends on role. | [C] | Implemented |
| FR-007 | Student dashboard shows request counts and the six most recent requests **for that student only**, plus number of active document types. | [C] | Implemented |
| FR-008 | Staff and admin dashboards show counts across all requests and the six most recent open requests. | [C] | Implemented |
| FR-009 | Admin dashboard shows total users, students, staff, active users, departments, and document types. | [C] | Implemented |
| FR-010 | Dashboard shows up to four announcements that are Published, addressed to the user's role or to all, and not expired. | [C] | Implemented |
| FR-011 | A student can submit a document request (type, purpose, claiming method `Pick-up`/`Email`, remarks). | [I] | Not implemented |
| FR-012 | A student can upload a file for each document requirement; one upload per requirement per request. | [I] | Not implemented |
| FR-013 | Staff can mark uploaded requirements as verified. | [I] | Not implemented |
| FR-014 | Staff can approve a request and advance it through stages: Request Received → For Verification → For Preparation → For Finalization → Ready for Release → Released. | [I] | Enum transitions only |
| FR-015 | Staff can reject a request with a reason unless it is already Completed, Rejected or Ready for Pickup. | [I] | Enum rule only |
| FR-016 | Staff can release a request and record who released it and when. | [I] | Not implemented |
| FR-017 | Students can see progress of a request (0/25/50/75/100 %, derived from stage and status). | [I] | Calculation only; no tracking page |
| FR-018 | Admin can create, edit, activate/deactivate document types (name, description, processing days, fee). | [I] | Not implemented |
| FR-019 | Admin can define requirements per document type (required/optional). | [I] | Not implemented |
| FR-020 | Admin can manage departments (code, name, active/inactive). | [I] | Not implemented |
| FR-021 | Admin can create, edit, activate/deactivate users and assign role/department. | [I] | Not implemented |
| FR-022 | Admin can create announcements (title, message, audience, status, publish/expiry dates). | [I] | Not implemented |
| FR-023 | A student and a staff member can exchange messages. | [I] | Not implemented |
| FR-024 | Users can update their own account settings. | [I] (legacy CSS name only) | Not implemented |
| FR-025 | Staff/admin can view request records and student records. | [I] (legacy CSS names only) | Not implemented |
| FR-026 | Existing legacy data can be imported into the new schema. | [C] | Command exists; blocked (see `progress.md`) |
| FR-027 | Self-registration. | Needs confirmation | None — **no route, controller, or view exists** |
| FR-028 | Password reset. | Needs confirmation | None — `password_reset_tokens` table only; **no route, controller, or view exists** |

## 9. Non-functional requirements

| ID | Requirement | Tag | Current state |
|---|---|---|---|
| NFR-001 | Passwords are stored hashed. | [C] | `password` cast = `hashed`; import hashes legacy plaintext |
| NFR-002 | CSRF protection on web forms. | [C] | Middleware active; forms use `@csrf` |
| NFR-003 | Existing production-like data must never be lost by tests, seeders, or tooling. | [R] (owner instruction) | **At risk** — see `progress.md` |
| NFR-004 | Dashboard queries should avoid per-row queries. | [R] | Eager loading used for student/documentType |
| NFR-005 | Responsive layout for the dashboard. | [C] | CSS media queries present |
| NFR-006 | Uploaded requirement files stored on a private (non-public) disk. | [I] | Schema comment says private `local` disk; not yet verified |
| NFR-007 | Automated tests protect core flows and run against an isolated database. | [R] | Only dashboard tests exist; isolation not configured |
| NFR-008 | Supported stack: PHP ^8.1, Laravel ^10.10, MySQL. | [C] | `composer.json`, `.env.example` |
| NFR-009 | Accessibility basics (labels, aria attributes). | [R] | Login labels and dashboard aria labels present; not audited |

## 10. Business rules and validation requirements

| ID | Rule | Tag |
|---|---|---|
| BR-001 | Login requires `school_id` and `password` (strings). | [C] |
| BR-002 | Only `activated` users can authenticate. | [C] |
| BR-003 | Request statuses: Pending, In Progress, Ready for Pickup, Completed, Rejected. | [C] |
| BR-004 | A request counts as "open" unless Completed or Rejected. | [C] |
| BR-005 | A request cannot be rejected once Completed, Rejected or Ready for Pickup. | [C] (enum) / [I] (enforcement point not built) |
| BR-006 | Progress: Pending/Rejected = 0 %, Ready for Pickup/Completed = 100 %, otherwise by stage. | [C] |
| BR-007 | Request reference format `REQ-` + id padded to 3 digits. | [C] |
| BR-008 | Announcement visible if Published, audience matches role or `all`, and not expired (`expires_at` null or ≥ start of today). | [C] |
| BR-009 | Document type names unique; fee is decimal(10,2); processing days is a non-negative integer. | [C] (schema) |
| BR-010 | Requirement names unique within a document type. | [C] (schema) |
| BR-011 | One upload per requirement per request. | [C] (schema) |
| BR-012 | Department code and name are unique. | [C] (schema) |
| BR-013 | `school_id` is unique. | [C] (schema) |
| BR-014 | Announcement titles are unique. | [C] (schema) — **Needs confirmation** (may be an unintended legacy constraint) |
| BR-015 | Who may approve/reject/release, and whether approvals are department-scoped. | Needs confirmation |
| BR-016 | Fee payment handling and whether fees block processing. | Needs confirmation |

## 11. User workflows and business processes

**Confirmed [C]**

1. *Login*: enter ID + password → validated → (deactivated-with-correct-password message | invalid message | session regenerated and redirect to intended page or dashboard).
2. *Dashboard*: role-specific counts, recent requests, announcements.
3. *Logout*: POST → session invalidated → login page.

**Inferred [I] — Needs confirmation**

4. *Request lifecycle*: Student submits request (status Pending, stage Request Received) → staff approves (In Progress, For Verification) → stages advance (Preparation → Finalization → Ready for Release) → Ready for Pickup → Released/Completed; rejection possible before Ready for Pickup.
5. *Requirement handling*: student uploads files per requirement → staff verifies.
6. *Communication*: student–staff messages, admin announcements by audience.

## 12. Data requirements and database relationships

Tables defined by migrations **[C]** (applied state of the real database not verified):

- `departments` (code, name, status)
- `users` (extends the Laravel default users table: adds `school_id`, `firstname`, `surname`, `department_id` → departments, `course`, `year`, `section`, `position`, `role`, `status`; keeps `name`, `email`, `email_verified_at`, `remember_token`)
- `document_types` (name, description, processing_days, fee, is_active)
- `document_requirements` (document_type_id → document_types cascade delete, name, description, is_required)
- `document_requests` (user_id → users restrict, document_type_id → document_types restrict, current_processed_by / approved_by / rejected_by / released_by → users nullOnDelete, status, current_stage, claiming_method, purpose, remarks, estimated_completion_date, approval/rejection/completion/release timestamps, rejection_reason)
- `uploaded_requirements` (document_request_id, document_requirement_id, original_name, path, is_verified; unique pair)
- `announcements` (title, message, audience, status, published_at, expires_at, created_by → users cascade)
- `messages` (student_id, staff_id, sender_id → users cascade, body)
- Framework tables: `password_reset_tokens`, `failed_jobs`, `personal_access_tokens`

Data observations: `users.name` (NOT NULL, from the default migration) is not part of the SmartDoc user model; `users.email` is NOT NULL and unique. Legacy data contained zero-dates and blank stages (handled by import code) [C].

## 13. Security, authentication, authorization, and privacy requirements

| ID | Requirement | Tag | Current state |
|---|---|---|---|
| SEC-001 | Session-based authentication with session regeneration on login. | [C] | Implemented |
| SEC-002 | Login throttling. | [C] | Implemented |
| SEC-003 | Role-based authorization (middleware/policies) on every protected module. | [R] | **No role middleware or policies exist yet**; only `auth` is applied. Needed before any role-restricted module is added. |
| SEC-004 | No default or hard-coded credentials in seeders. | [R] | **Violated** — both seeders contain a hard-coded default admin credential (value intentionally not reproduced here) |
| SEC-005 | Secrets never committed; `.env` is git-ignored. | [C] | `.gitignore` covers `.env` |
| SEC-006 | Uploaded documents are not publicly accessible and access is authorized per request owner/staff. | [R] | Not implemented |
| SEC-007 | Personal data (names, school IDs, emails, uploaded documents) handled per applicable privacy rules (e.g. Philippine Data Privacy Act). | Needs confirmation | Not assessed |
| SEC-008 | Legacy plaintext passwords must be rotated after import. | [C] (noted in import command) | Process requirement; not tracked elsewhere |

## 14. Registration and password-reset requirements

| ID | Requirement | Status |
|---|---|---|
| REG-001 | Whether students can self-register or accounts are created only by admin (legacy "usermanagement" and the `Deactivated` default message "not activated by the admin" suggest admin activation). | **Needs confirmation** |
| REG-002 | If self-registration exists, new accounts start `deactivated` until admin approval. | Needs confirmation [I] |
| PWR-001 | Whether users can reset passwords by email, via admin, or not at all. | **Needs confirmation** |
| PWR-002 | If email reset is required, mail transport must be configured (`.env.example` defaults to Mailpit-style SMTP host). | Needs confirmation |

Current implementation: **neither registration nor password reset exists.**

## 15. Reporting and document-management requirements

- Document types, per-type requirements, uploads, and verification: schema only [I].
- Reporting: legacy CSS names `requestrecords` and `studentrecords` hint at record/report views; scope **Needs confirmation**. Legacy reports displayed "REQ-007"-style references [C comment].
- Retention period for uploaded documents and records: Needs confirmation.

## 16. Assumptions, constraints, and dependencies

- Local environment: Laragon on Windows, MySQL, PHP ≥ 8.1.
- The existing database may hold important data; destructive commands are prohibited (owner instruction).
- Legacy data import depends on a separate `smartdoc_legacy` database and an extra connection definition that is **not yet in `config/database.php`**.
- The default Laravel users migration remains alongside the SmartDoc alteration migration (the latter *alters* the existing table).
- Git repository exists but has **no commits** on `main` (see `progress.md`).

## 17. Acceptance criteria

Criteria below apply to currently implemented, confirmed behaviour. Criteria for inferred features must be written after the requirement is confirmed.

| Req. | Acceptance criterion |
|---|---|
| FR-001/002 | Activated user with correct credentials reaches the dashboard; deactivated user with correct password sees the activation message; wrong credentials see the generic error; none of these reveal whether an ID exists. |
| FR-003 | A sixth login attempt within one minute from one client is rejected with HTTP 429. |
| FR-004 | After logout, protected URLs redirect to login. |
| FR-005 | Guest `GET /dashboard` → redirect to `/login`; `GET /` → redirect to `/login`. |
| FR-007 | Student A never sees Student B's requests on any dashboard figure or list. |
| FR-008/009 | Staff dashboard excludes Completed/Rejected from the recent list; admin sees all six admin counters. |
| FR-010 | Draft, expired, or other-audience announcements never appear. |
| NFR-003 | Running the full test suite leaves the development database's data unchanged. |

## 18. Open questions and requirements requiring confirmation

1. Is self-registration required (FR-027 / REG-001)? Who activates accounts?
2. Is password reset required, and by what channel (FR-028 / PWR-001)?
3. Which legacy modules must be ported? (Request submission, requirement upload/verification, document-type and requirement admin, departments, users, announcements, messaging, account settings, request records, student records.)
4. Are staff scoped by department, or can all staff process all requests (BR-015)?
5. Are fees collected/verified inside the system (BR-016)?
6. Must announcement titles be unique (BR-014)?
7. What does "AI" in "AI Document Request System" refer to?
8. Which document types and requirements are official at the institution?
9. Retention, privacy, and audit-log requirements (SEC-007).
10. Is the existing database the production dataset, a migrated copy, or a development copy? Has the legacy import already been performed?
11. Should `users.name` remain, or be removed/derived from firstname + surname?
12. Should `email` be mandatory and unique for all users (legacy data may not satisfy this)?
