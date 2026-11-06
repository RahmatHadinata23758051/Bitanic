# Bitanic — Revised Implementation Backlog & Work Order
## Multi-School Ready Vocational LMS & IoT Workstation

- **Version**: 1.2.2
- **Date**: 2026-11-06
- **Status**: Active development; source reconciliation is against `bitanic-app` HEAD `bb9fe94f089575aac4c490239d78b59d68a7e198`, with uncommitted topbar and backlog reconciliation changes in this working tree. Guest browser smoke and role authorization feature tests pass. Login-capable student/teacher/admin browser journeys remain blocked because no approved non-production accounts are available. UI redesign has not started.
- **Product Direction**: Single-School MVP with Multi-School Ready Architecture
- **Methodology**: TDD, dependency-first delivery, task-isolated implementation, evidence-based QC.
- **Reconciled application HEAD**: `bb9fe94f089575aac4c490239d78b59d68a7e198` (`bitanic-app`, 2026-11-06); the current follow-up is the topbar truthfulness change and backlog reconciliation. The dated 2026-11-05 evidence subsection below is historical and was recorded against its explicitly named earlier HEAD.
- **Canonical file**: `C:\Users\user\Nata\MAKERINDO\Bitanic\BACKLOG_REVISED.md`; a synchronized tracked snapshot is maintained at `bitanic-app/BACKLOG_REVISED.md` because the canonical file is outside the Git worktree.  
- **Status vocabulary**: `DONE` = acceptance verified; `PARTIAL` = implementation exists but acceptance/coverage remains; `TODO` = no verified implementation; `BLOCKED` = requires an external decision, approval, or hardware prerequisite. `DEFERRED` is used only as a scheduling qualifier, not as a replacement status.

---

## 0. Governing Decisions & Hard Gates

1. **Architecture**: MVP serves one active school, but tenant-aware ownership is built into the schema from day one. No tenant registration, billing, subdomains, or database-per-tenant for MVP.
2. **Authentication development authorization**: Login/authentication implementation is authorized for this active-development phase. Continue to require explicit authentication, role checks, fail-closed school/resource scoping, safe generic credential errors, session security, and automated negative tests. This development authorization is not a production launch or deployment approval.
3. **Hardware Safety**: The application must never report successful physical actuation without a positive device ACK. Until protocol selection and physical firmware integration are validated, hardware mutation endpoints are disabled or clearly labelled simulation-only behind authenticated authorization.
4. **Academic Integrity**: No global/latest telemetry may be attached to a student's work. Report submission, evidence binding, revision, and grade histories must be scoped and immutable.
5. **No Speculative Hardware Protocol**: Determine ESP32 transport based on the real firmware, device capabilities, lab network, and testing—not assumptions that MQTT or HTTP downlink exists.
6. **Legacy Commerce**: Midtrans, subscription, and payment data remain untouched until explicitly approved after dependency and retention review.
7. **Baseline Assets**: `upi-edge/` and `dump_upiedge.sql` are immutable reference/baseline assets.
8. **No destructive DB operations**: Do not use global `truncate()` or destructive reset against development/production data.
9. **Scope discipline**: One task should deliver one independently testable page, flow, or architectural concern. Shared schema foundations may be grouped only when inseparable.

---

## 1. Carry-Forward of Existing Backlog Tasks

This register carries the original IDs and conflicting historical meanings forward without renumbering them. Statuses below reflect source/test evidence available at the reconciled application HEAD recorded above, not commit subjects. `DONE` means acceptance is verified; `PARTIAL` means code exists but one or more acceptance criteria remain; `TODO` means no implementation was verified; `BLOCKED` means a documented decision, approval, or external prerequisite is missing. A missing verification artifact is not treated as proof of completion.

### Validation evidence recorded for this reconciliation

| Evidence ID | Validation result | Scope / limitation |
| :--- | :--- | :--- |
| V-1 | Focused classroom, import, LKS, and security feature tests: **23 deprecated (125 assertions), no failures**. | Session output from 2026-11-05; not every acceptance criterion in this backlog. |
| V-2 | `ModulePracticumTest`: **10 deprecated (46 assertions), no failures**. | Covers module/LKS behaviors represented by that test class, not full immutable session-attempt requirements. |
| V-3 | Full SQLite suite: **99 deprecated, 5 passed (460 assertions), no failures; successful exit**. | Does not prove PostgreSQL-only behavior or hardware operation. PHP 8.5 emitted repeated `PDO::MYSQL_ATTR_SSL_CA` deprecation notices. |
| V-4 | Isolated PostgreSQL 17 smoke suite against `bitanic_test`: **31 deprecated (162 assertions), no failures**; test database was dropped after the run. | Smoke coverage only; does not establish CI automation, concurrency acceptance, or a full PostgreSQL suite. |

Relevant implementation evidence (full SHA): `5daf9352c55792490c20839444bf4e9c5385f7c2` (production seeder/import guard and device API-key hashing); `bb1fec24374676cf5d7f63c395e1c2005c062943` (truthful simulated reboot); `10842788c67116a7a5eb3cbcf3b48b6acd608594` (LKS allocation/evidence validation); `b9fd578b14babe39f85557a1c6d0e72b0e374358` (authorized student report allocation); `754315b0b5df15b40d8934ad148e0f368293e6ca` (classroom/group/allocation management); `894b1a391f684c3a9289809b68e0b27853207392` (CSV/XLSX import). These SHAs identify relevant code snapshots only; the statuses below are based on code inspection and tests, not commit titles.

| Existing ID | Existing Scope | Status | Evidence | Remaining work / blocker |
| :--- | :--- | :--- | :--- | :--- |
| TASK-101 | Laravel 12 monolith `bitanic-app` | PARTIAL | Reconciled HEAD `bb9fe94`; working tree was clean before backlog reconciliation | Runtime baseline is present; verify supported PHP/Laravel matrix. Baseline asset tests pass, while historical checksums are unavailable and are not a development/transition blocker. |
| TASK-102 | PostgreSQL 17/18 configuration | PARTIAL | V-4 (PostgreSQL 17 smoke) | Add repeatable isolated PostgreSQL test service/CI and supported-version matrix; SQLite does not establish PostgreSQL compatibility. |
| TASK-103 | Core telemetry/LMS schema | PARTIAL | HEAD schema/migrations inspected; V-3/V-4 | Map actual migrations/FKs to approved ERD and additive backfill plan; tenant constraints remain unverified against acceptance. |
| TASK-104 | Additional vocational schema and demo seeding | PARTIAL | `5daf935`; V-1/V-3 | Separate authoritative production data from fixtures; complete idempotence, retention, and secret-leak checks. |
| TASK-104A | Orchestrate `DatabaseSeeder` | PARTIAL | `5daf935`; V-1/V-3 | Production guard exists; verify idempotent repeated runs and full separation of demo fixtures after policy approval. |
| TASK-104B | Expand student/teacher fixtures | BLOCKED | No approved fixture source/count recorded | Obtain approved anonymized/demo fixture source and population requirements; never invent production users. |
| TASK-104C | Seeder integrity and retention tests | PARTIAL | `5daf935`; V-1/V-3 | Add complete idempotence, referential integrity, uniqueness, retention, and non-destructive safeguards. |
| TASK-105 | Device-key telemetry ingestion and async AI | PARTIAL | `5daf935`; V-1/V-3 | Device key hashing/revocation protection exists; finish legacy-key inventory/rotation, ownership/provenance/replay matrix, queue-failure behavior, and lab network policy. |
| TASK-111–126 | Per-page functional/security specifications | BLOCKED | Decision list §4; approvals not evidenced | Reconcile each page spec against approved tenancy, identity, freshness, LKS, and hardware decisions. Do not treat a range as collectively accepted. |
| TASK-201 | Login/session | PARTIAL | Authentication development is authorized; auth controller, password lifecycle, session routes, and login feature tests are present; inactive-school rejection is verified in `LoginTest`. Guest browser smoke passed, but account-based role smoke is blocked: no approved non-production accounts are provisioned in either the running development DB or the isolated test DB. | Obtain approval to provision disposable role-specific QA accounts in an isolated non-production database; then complete browser journeys for student, teacher, and admin. This status is not production-readiness approval. |
| TASK-202 | SSO integration | BLOCKED | No provider/credential approval recorded | Only proceed if a provider, owner, and integration scope are approved. |
| TASK-203 | Auth and role middleware | PARTIAL | Routes and feature tests at HEAD; V-1/V-3 | Complete school-membership/resource policies and guest, wrong-role, cross-school, and IDOR coverage for every sensitive route. |
| TASK-204 | Functional global navigation | PARTIAL | Current route/layout inventory; subscription links remain | Review role-aware navigation and isolate legacy commerce entry points after explicit PO decision. |
| TASK-301 | Lab dashboard | DONE (software behavior) | Reconciled source and `DashboardTest`, `TelemetryProvenanceAndHonestEmptyStateTest`, `HeartbeatAvailabilityTest` | School/role scoped overview, null/empty values, provenance labels, and anomaly freshness gates covered. Physical and production operation remain out of scope. |
| TASK-302 | IoT monitoring page | PARTIAL (null-device topbar honesty fixed; hardware acceptance remains out of scope) | `DashboardTest`, `TelemetryProvenanceAndHonestEmptyStateTest`, `HeartbeatAvailabilityTest`, and `TrainerKitManagerTenancyAndSecurityTest`; current guest browser smoke: `/` and `/schedules` returned HTTP 200 with zero console errors or failed requests; `DashboardTest` verifies the shared header's empty state and present-device identity | Removed the fabricated fallback kit identity, unconditional `Live`/pulse, RSSI, and battery values from the shared topbar. The running guest schedule page currently has no kit choices or sessions; seeded feature tests verify default-school kit visibility and cross-school isolation. No physical control was exercised. |
| TASK-303 | Relay hardware control | PARTIAL (software guard) / BLOCKED (physical actuation) | `DashboardTest::test_relay_toggle_without_hardware_ack_does_not_persist_requested_state`; route/security matrix | Keep the UI honest about simulated/non-ACK outcomes. No hardware command may be described as successful without positive hardware ACK. |
| TASK-304 | Agronomy analytics | DONE (software data behavior) | `AnalyticsController` supplies school-scoped chart series and `AgronomyAnalyticsTenancyTest` verifies values/timestamps and tenancy; focused chart/account tests pass | `resources/views/components/telemetry-chart.blade.php` still exposes inert range buttons (no filtering), fixed ideal bands, no simulation/fixture provenance context, dual y-axes for climate mode, and a gradient chart fill. Functional redesign review is required before visual sign-off; do not count this as completed chart UX. |
| TASK-305 | Telemetry history and CSV | DONE | `5530ad7`; V-3 | School tenancy fail-closed, cross-school IDOR rejection on device filters, superuser school selector, memory-safe cursor streaming with safety bound (50,000 rows), and provenance column ('Asal Data') in CSV export; verified in `TelemetryHistoryTenancyAndIntegrityTest` (8 tests, 35 assertions). |
| TASK-401 | Practicum module catalog | PARTIAL | Module page and `ModulePracticumTest`; V-2 | Obtain teacher content review and verify role/class access; do not impose unapproved sequential locking. |
| TASK-402 (original collision) | Schedule/allocation in original backlog | PARTIAL | Schedule code and sequential conflict checks pass; `ActivityScheduleTest::test_concurrent_overlapping_bookings_are_serialized_and_rejected()` remains sequential despite its name. A bounded two-session harness passed against isolated PostgreSQL 17.6 `bitanic_test` on 2026-11-06, and new `PostgresScheduleConcurrencyRaceTest` independently verifies the lock-wait/commit/rejection flow (1 test, 12 assertions). | Preserve this mapping. Schedule remains partial for full session/allocation acceptance and repeatable CI PostgreSQL service configuration. |
| TASK-402 (LKS in later Phase 2) | LKS submission (`TASK-402-LKS` temporary label) | PARTIAL | `1084278`, `b9fd578`; V-1/V-2 | Session-scoped attempt/revision history, approved evidence timing, and complete finality rules remain. |
| TASK-403 (original) | Telemetry history in Phase 1 summary | DONE | `5530ad7`; V-3 | Preserved mapping; school tenancy fail-closed, cross-school IDOR rejection, superuser selector, memory-safe cursor CSV export with provenance; verified in `TelemetryHistoryTenancyAndIntegrityTest` (8 tests, 35 assertions). |
| TASK-403 (Phase 2) | Rubric grading | PARTIAL | Module grade route/test at HEAD; V-2/V-3 | Add same-school/class/session authorization, immutable grade history, revision workflow, and concurrency tests. |
| TASK-404 (original) | Analytics in Phase 1 summary | DONE | AnalyticsController hardened at HEAD; V-3 | Preserved original mapping; school/device scoping, anti-slop UI filters, and tenancy verified in `AgronomyAnalyticsTenancyTest`. |
| TASK-404 (Phase 2) | Report hub | DONE | `448e94f`, `4fc603d`; V-3 | School tenancy, student self-scoping, module/status/class filters, honest telemetry snapshot; verified in `PracticumReportHubTest` (9 tests, 31 assertions). |
| TASK-405 | Assessment/grade report | DONE | `ffbf64a`, `cb32b11`; V-3 | Tenant-scoped gradebook, student self-scoping, superuser school filter, memory-safe streaming CSV export; verified in `CompetencyAssessmentTenantTest` (7 tests, 33 assertions). |
| TASK-406 | Schedule CRUD | PARTIAL | Schedule CRUD at HEAD; V-3/V-4. The isolated PostgreSQL 17.6 race passed and repeatable PHPUnit coverage now passes against `bitanic_test` (1 test, 12 assertions), with scoped fixture cleanup. | Complete class/group/session allocation semantics and repeatable CI PostgreSQL service configuration. |
| TASK-407 | Student/teacher directory | DONE | `a591cf2`, `7ea42e3`; V-3 | Role gating (students rejected), school isolation, superuser school filter, role tabs, and portable search; verified in `UserDirectoryTest` (8 tests, 29 assertions). |
| TASK-408 | Trainer kit manager | DONE (software guard scope) | `TrainerKitManagerTenancyAndSecurityTest` (15 tests, 149 assertions), `SecurityHardeningAndRebootTest` (9 tests, 65 assertions), PostgreSQL lock/rollback suite (9 tests, 69 assertions) | School tenancy, sensitive metadata restrictions, simulation-only reboot, atomic calibration persistence, secret-free audit attribution, and mutation scoping verified; no physical ACK/hardware operation claimed. |
| TASK-409 (original) | Raw Modbus logs in Phase 1 summary | DONE | Raw-log implementation hardened at HEAD; V-3 | Preserved mapping; proved source/provenance badge labeling, honest ingress stream status without fake COM claims, and tenancy verified in `RawLogsDebuggerTenancyAndIntegrityTest`. |
| TASK-409 (Phase 2) | Calibration | DONE (software layer) / BLOCKED (physical downlink) | Sensor-specific profiles, immutable raw values, and per-parameter `calibration_lineage` (`profile_id`, `version`, `method`) are stored and covered in `SensorCalibrationProfileTest`; migration `2026_11_06_000001` is additive | Physical EEPROM/downlink remains blocked on verified manufacturer datasheet, firmware protocol, positive ACK, safe behavior, and hardware-in-loop evidence. Software-fixture values are not hardware-approved. |
| TASK-410 (original) | Settings and audit | PARTIAL | Settings/audit surface at HEAD; V-3 | Complete school scope, sensitive mutation coverage, redaction, and append-only enforcement. |
| TASK-410 (Phase 2) | Reboot/OTA operations | PARTIAL | `bb1fec2`; V-3 | Reboot is simulation-only without device ACK; physical reboot/OTA remains unimplemented pending firmware/transport and safety approval. |
| TASK-411 | Raw Modbus logs | DONE | Raw-log implementation hardened at HEAD; V-3 | Labeled manual/sensor/simulation source, removed fake COM port connection indicator, fail-closed school tenancy, IDOR kit code protection, superuser school filter, and non-actuation disclaimer verified in `RawLogsDebuggerTenancyAndIntegrityTest` (8 tests, 38 assertions). |
| TASK-412 | Modbus command transmission | BLOCKED | No verified transport/firmware ACK evidence | Requires firmware audit, allowlist, frame/CRC specification, ACK, physical test plan, and safety sign-off. |
| TASK-413 | School/sensor settings | PARTIAL | School settings, audit ownership, threshold validation, and append-only behavior are covered in `SchoolSettingsAndAuditTrailTest`; ingestion resolves thresholds as global → school settings → device override (`TelemetryIngestTest`) | Complete review of settings coverage and align all exposed sensor keys/ranges with approved agronomy specifications. |
| TASK-414 | Audit trail | PARTIAL | Audit model/usage at HEAD; V-1/V-3 | Verify all sensitive mutations, actor/school/target/correlation fields, redaction, and resistance to edit/delete. |
| TASK-415 | Anomaly alerts | DONE | `5530ad7`; V-3 | Standardized ATPH 7-in-1 soil agronomy threshold matrix (pH, EC, moisture, temp, N, P, K), safe non-prescriptive vocational guidance without relay commands, structured metadata lifecycle; verified in `AgronomyAnomalyAlertEvaluatorTest` (7 tests, 51 assertions). |
| TASK-501 | Legacy commercial inventory | PARTIAL | `routes/web.php` subscription routes; checkout/controller, payment/webhook, models, migrations, seeder, service, and tests inventoried at HEAD | Record complete dependency/data/route map in LEGACY-01; preserve historic data. |
| TASK-502 | Hide/disable commercial entry points | BLOCKED | PO policy not recorded | Obtain explicit route/UI policy; no deletion or public-route changes before approval. |
| TASK-503 | Commercial data retention/migration decision | BLOCKED | Retention/removal approval not recorded | Decide retention/ownership and migration only after inventory; no destructive work without separate approval. |
| TASK-601–612 | Feature, authorization, DB, regression tests | PARTIAL (software suite passes; broader PostgreSQL/browser role acceptance incomplete) | Current full configured SQLite suite passed 355 tests, 1901 assertions, 5 skipped, no failures (2026-11-06; see §2.9). The PostgreSQL-only race test passed separately on isolated `bitanic_test`. | Repeatable CI PostgreSQL service and authenticated role-browser acceptance remain incomplete; hardware-in-loop remains outside software-test coverage. Baseline asset integrity is protected; no baseline assets were changed in this working tree. |
| TASK-701–704 | UI/UX sprint and accessibility | TODO — DEFERRED (design handoff only) | No redesign was performed or reviewed. A separate handoff may prepare the user-approved design sequence without changing interface source. | Continue functional development; treat accessibility defects that block functionality as ordinary non-UI fixes. UI redesign itself stays unstarted until the user explicitly asks to begin it. |

> **Important ID note**: Existing `BACKLOG.md` contains conflicting task-number mappings across its early summary and later phases (e.g., TASK-402, TASK-403, TASK-404, TASK-409, TASK-410). This revised backlog preserves those historical mappings. `TASK-402-LKS` is a temporary disambiguation label, not an official replacement ID. Do not normalize IDs or move them into another tracker without PO approval.

---

## 2. New Revised Milestones & Ordered Tasks

### Milestone 0 — Review Baseline, Decision & Safety Freeze (P0)

#### BR-000 — Reconcile Existing Backlog IDs, Status, and Evidence
- **Priority**: P0
- **Dependencies**: None
- **Scope**: Compare each task/status with code at the approved HEAD and tests; resolve duplicate IDs with a stable mapping table.
- **Acceptance Criteria**:
  - Every existing ID in `BACKLOG.md` appears in the carry-forward register.
  - Conflicting IDs have explicit old-title → unique canonical-ID mapping approved by PO.
  - Status labels distinguish implemented, partial, blocked, and not started; test evidence is linked.
  - No code/schema changes are made in this task.

#### BR-001 — Disable Unauthenticated High-Risk Mutations (Interim Safety)
- **Priority**: P0
- **Dependencies**: BR-000; Technical Reviewer authorizes safety hotfix scope; no login development required.
- **Scope**: Protect or disable relay, reboot, calibration, raw Modbus injection, grading, and schedule deletion mutations until proper authentication/authorization is ready.
- **Acceptance Criteria**:
  - Unauthenticated requests to each listed risky mutation return a non-success response (401/403/404 as approved) and make no database changes.
  - Any available authenticated mutation checks role/resource ownership, or remains disabled if policy isn't defined.
  - Relay endpoint never returns “success/active” as a physical action unless hardware ACK has been verified; any temporary simulator response says “simulation only” and is not exposed to unauthorized users.
  - Feature tests verify denied requests create no commands/log state or hardware state changes.

#### BR-002 — Confirm School, Membership, and Identity Policies
- **Priority**: P0
- **Dependencies**: None
- **Scope**: Define global `users` vs school memberships, school-specific identity uniqueness, teacher/student roles, transfer/alumni behavior, active-school MVP resolver.
- **Acceptance Criteria**:
  - Decision record specifies whether a user may belong to multiple schools and whether NISN/NIP uniqueness is school-scoped or global.
  - Defines school context selection for MVP and rejects ambiguous/inactive school context.
  - Roles are school-specific via `school_memberships`; no reliance on one global role for tenant-bound authorization.
  - No migration starts before sign-off.

#### BR-003 — Resolve Deployment Topology (Separate Decision)
- **Priority**: P1
- **Dependencies**: None
- **Scope**: Decide whether MVP deployment is lab-local, cloud, or hybrid; this does not alter single-school schema direction.
- **Acceptance Criteria**:
  - Network boundaries, backup, TLS, connectivity-loss, device ingress, and secrets operations documented for the selected deployment.
  - Decision does not infer MQTT/HTTP downlink from `upi-edge`; inspect actual firmware before selecting transport.

---

### Milestone 1 — Approved Multi-School-Ready Data Foundation (P0/P1)

#### BR-100 — Approve ERD and Tenant Ownership Rules
- **Priority**: P0
- **Dependencies**: BR-002
- **Scope**: Review `ERD_PROPOSAL.md`, including ownership boundaries, cross-school referential integrity, and group/session ownership.
- **Acceptance Criteria**:
  - All proposed relationships and delete policies reviewed by technical reviewer.
  - Duplicate/contradictory foreign-key ownership paths are resolved.
  - Database constraints prevent a session kit allocation from pairing a group, session, and device from different schools.
  - No schema implementation occurs before approval.

#### BR-101 — Production and Demo Seeder Separation
- **Priority**: P1
- **Dependencies**: BR-100; BR-102
- **Scope**: Separate authoritative production master data from local/testing fixtures and development accounts.
- **Acceptance Criteria**:
  - `db:seed` in production does not create default-password users or fake student telemetry/reports.
  - Demo seeder refuses staging/production unless an explicit, audited override is approved.
  - Production seeder is idempotent; repeated runs do not duplicate school/modules/thresholds.
  - Tests verify environment guard, idempotence, and no secret/default password leakage.

#### BR-102 — Additive Schema and Backfill Plan (No Execution Yet)
- **Priority**: P0
- **Dependencies**: BR-100; BR-002
- **Scope**: Inventory data, define additive migrations for schools/memberships/classes/groups/sessions/allocations/command/report histories/provenance. Document backfill and rollback.
- **Acceptance Criteria**:
  - Each current table/column has a mapping to target schema or an explicit retention/deprecation decision.
  - Backfill is rerunnable and reports rows not mapped; no guessed class, school, or student memberships.
  - Migration deploy plan uses expand → backfill → verify → constrain; old columns are not dropped in the initial migration.
  - Rollback behavior is tested on a disposable PostgreSQL copy and avoids deleting baseline `upi-edge`/SQL files or production data.
  - This planning task creates no migration file.

#### BR-103 — Implement Approved Core Tenancy & Academic Schema
- **Priority**: P1
- **Dependencies**: BR-100, BR-102, BR-101
- **Scope**: One migration group for core mutually dependent identities/academics: `schools`, `school_memberships`, `classes`, `class_memberships`, `practicum_groups`, `group_members`; add direct `school_id` only to approved ownership roots.
- **Acceptance Criteria**:
  - PostgreSQL migrations up/down succeed on an empty test database and on a representative anonymized copy.
  - Unique constraints and FKs enforce membership and school integrity.
  - Cross-school class/group membership writes are rejected at DB/application boundary.
  - Backfill report shows zero unexplained unmapped active users/profiles before hardening NOT NULL constraints.
  - Existing schoolless development data remains safely represented through a documented default-school backfill, not silently discarded.

#### BR-104 — Implement Practicum Sessions and Kit Allocations
- **Priority**: P1
- **Dependencies**: BR-103, TASK-119 specification approval, TASK-121 specification approval
- **Scope**: Introduce `practicum_sessions`, `session_kit_allocations`; map existing `activity_schedules` and participants without deleting them.
- **Acceptance Criteria**:
  - Session belongs to one school and class, has module, instructor, time range, status.
  - Each session allocation has one group and one device; database constraints prohibit repeated device or group allocation where policy requires uniqueness.
  - Cross-school group/session/device links are rejected.
  - PostgreSQL integration tests prove time overlap is safe under concurrent transaction attempts, not just serial request validation.
  - Existing schedule data is mapped or explicitly reported as unresolved; no destructive migration.

#### BR-105 — PostgreSQL Test Environment and CI Gate
- **Priority**: P1
- **Dependencies**: BR-102
- **Scope**: Add dedicated test database configuration/job for Postgres; retain SQLite only for compatible tests.
- **Acceptance Criteria**:
  - CI or documented local test command runs migrations and feature tests against supported PostgreSQL version.
  - Suite tests JSONB query behavior, timestamptz/timezone, foreign key policies, transaction locks, and `whereDate` semantics.
  - Test configuration cannot accidentally connect to development/production database; connection assertion fails closed.

---

### Milestone 2 — Authentication, Authorization & Academic Integrity (P0)

#### BR-200 — Finalize Authentication Requirements (Existing TASK-111; ACTIVE-DEVELOPMENT FOLLOW-UP)
- **Priority**: P0
- **Dependencies**: BR-002, TASK-126 specification
- **Scope**: Specify account lifecycle, credential/reset policy, login/logout/session, throttling, recovery, non-production QA account policy, post-login behavior.
- **Acceptance Criteria**:
  - Record role and redirect matrix, rate limits, account status handling, password lifecycle, CSRF/session rotation, and approved QA account provision/revocation process.
  - Login implementation and its existing migrations are tracked under BR-201; this requirements task does not itself gate ongoing active-development implementation.
  - Production launch, SSO integration, and real-user credential provisioning require their own explicit approvals.

#### BR-201 — Implement Authentication & Session Lifecycle (Existing TASK-201)
- **Priority**: P0
- **Dependencies**: BR-103, BR-200 requirements follow-up; authentication implementation is authorized for active development.
- **Scope**: Email/password login, logout, session rotation, throttling, access redirects only; SSO is excluded.
- **Acceptance Criteria**:
  - Valid user can log in; invalid, inactive, and cross-school users are rejected with safe errors.
  - Session ID rotates on login and is invalidated on logout.
  - Rate limit enforces approved threshold and retry behavior.
  - Tests cover CSRF/session, lockout, redirect, and no account enumeration.
  - No SSO buttons imply a working provider unless integration is separately approved.

#### BR-202 — Role, Membership, and Resource Authorization (Existing TASK-203/602)
- **Priority**: P0
- **Dependencies**: BR-201; BR-103; BR-104
- **Scope**: Policies/middleware for `school_memberships`, class/group scope, reports, devices, sessions, and administrative actions.
- **Acceptance Criteria**:
  - Every sensitive web/API mutation has an explicit authentication/authorization rule.
  - User from School A cannot read or mutate School B class, report, device, session, or command by changing IDs.
  - Teacher permissions are scoped to their school/class/session per approved role matrix.
  - Student cannot grade, delete schedule, calibrate, reboot, or send hardware commands.
  - Feature tests cover guest, wrong role, wrong school, wrong class, and resource owner cases.

#### BR-203 — Migrate LKS to Session-Scoped Evidence and Immutable Attempts (Existing TASK-402-LKS/606)
- **Priority**: P0
- **Dependencies**: BR-104, BR-202, BR-204
- **Scope**: Bind report to student, module, practicum session/group/allocation, source device, and telemetry evidence; add immutable submissions.
- **Acceptance Criteria**:
  - Report submission requires valid session membership and matching group allocation.
  - Captured reading must match allocated device and session window; latest global reading is never accepted.
  - Store `evidence_reading_id` FK plus frozen snapshot/provenance; if no matching reading, show explicit no-telemetry outcome and do not synthesize values.
  - Each submission appends a unique attempt; prior attempts remain unchanged.
  - Final reports reject student modification; only teacher-authorized revision transition reopens submission.
  - Tests cover cross-device, stale/out-of-window, no-reading, wrong group, duplicate submission, and graded lock.

#### BR-204 — Assessment Revision and Grade History (Existing TASK-403/607)
- **Priority**: P0
- **Dependencies**: BR-103, BR-202; TASK-117 specification approval
- **Scope**: Separate immutable grade records, current grade state, revision-request workflow, teacher notes.
- **Acceptance Criteria**:
  - Grade action is only available to authorized teacher in the same school and permitted class/session.
  - Each grading/change appends grader, rubric, calculated score, feedback, timestamp, and reason for adjustment.
  - A finalized report can only re-enter workflow through an authorized revision request; student cannot overwrite grade data.
  - Historical grades remain queryable after an adjustment.
  - Tests cover grade transitions, permissions, score range, revision reason, and concurrent grade update handling.

---

### Milestone 3 — IoT Data Integrity, Freshness and Calibration (P1)

#### BR-300 — Telemetry Provenance and Honest Empty State (Existing TASK-302/604)
- **Priority**: P1
- **Dependencies**: BR-103, BR-105
- **Scope**: Add `hardware|simulation|fixture` provenance; remove fake fallback metrics from monitoring/dashboard.
- **Acceptance Criteria**:
  - All ingestion/simulator/fixture producers set explicit provenance.
  - Hardware provenance only set for authenticated device ingress; tests/seeders cannot default to hardware.
  - No reading renders em dash and explicit “Belum Ada Transmisi Data”; never “Baru saja” or active stream indicator without fresh evidence.
  - UI labels simulation/fixture data distinctly; query filters are role/school/device scoped.
  - Tests verify empty DB, inactive device, unknown device ID, simulation provenance, and real hardware payload paths.

#### BR-301 — Heartbeat Availability Policy (Existing TASK-121/603/604)
- **Priority**: P1
- **Dependencies**: BR-103; TASK-113/TASK-121 approval
- **Scope**: Computed status from heartbeat and configurable per-school/device policy.
- **Acceptance Criteria**:
  - Last heartbeat timestamp is updated only by authenticated device traffic.
  - Availability resolver has deterministic boundary behavior with a configurable timeout (initial default proposal 900 seconds, not assumed immutable).
  - Dashboard, monitoring, and kit manager use one computed availability source.
  - Tests use frozen time for fresh, stale, null heartbeat, clock edge, and recovered device cases.

#### BR-302 — Sensor Calibration Profile and Raw/Calibrated Storage (Existing TASK-409)
- **Priority**: P1
- **Dependencies**: BR-103, BR-105; manufacturer-verified sensor/model specifications; qualified agriculture/hardware review
- **Scope**: Preserve raw register payload and record calibration profile/version applied to derived readings; sensor-type-specific transform only.
- **Acceptance Criteria**:
  - Each supported parameter has an approved unit, device register mapping, range, calibration method, and firmware compatibility.
  - No universal or unverified `raw + offset` formula is used for physical sensor readings.
  - Ingested readings preserve immutable raw values; derived values record calibration profile/version/effective time.
  - Calibration changes are permissioned, auditable, and do not rewrite historical readings.
  - Tests validate approved reference vectors and boundary/invalid profiles against manufacturer documentation.
- **Implementation Status (2026-11-05)**: Software profile/versioning, immutable raw storage, lineage, audit, configurable validation, and feature tests are implemented. `config/sensors.php` and `docs/hardware/SENSOR_CALIBRATION_SPECIFICATION.md` currently document **unverified software-fixture values only** and are not approved for physical hardware/agronomy use.
- **External Blocker**: Obtain the exact sensor manufacturer/model datasheet and compatible ESP32 firmware protocol, then complete hardware/agronomy review. Physical calibration downlink is explicitly **BLOCKED** until firmware transport, command authentication, positive ACK, safety behavior, and hardware-in-loop tests are verified.

#### BR-303 — Device-Key and Ingestion Hardening (Existing TASK-105)
- **Priority**: P1
- **Dependencies**: BR-103, BR-105
- **Scope**: Device identity, key hashing/rotation/revocation, replay/idempotency, school ownership, validation.
- **Acceptance Criteria**:
  - Valid key can ingest only for its bound active device/school; revoked/invalid key returns 401 without state mutation.
  - Key material is never logged or stored plaintext.
  - Replayed message handling follows documented idempotency policy; reading timestamp/device are validated.
  - Tests cover wrong key/device pair, revoked key, invalid payload, duplicate message, and cross-school isolation.

---

### Milestone 4 — Hardware Command Readiness (P2, Hardware Dependent)

#### BR-400 — Audit Actual ESP32 Firmware and Select Transport
- **Priority**: P1 discovery / P2 implementation gate
- **Dependencies**: Access to firmware repository or device specification; BR-003
- **Scope**: Inspect firmware implementation, available protocols, command framing, authentication, TLS, acknowledgment, reconnect, and lab connectivity.
- **Acceptance Criteria**:
  - Record firmware repo/version and protocol evidence (HTTP REST/MQTT/other) with paths or documentation.
  - Decision compares transport security, bidirectional behavior, offline operation, QoS/retry, broker/device burden, and hosting topology.
  - Do not claim protocol support from PRD mock text or Laravel package presence alone.
  - Product Owner/Technical Reviewer approve transport and safety rules before downlink implementation.

#### BR-401 — Device Command Persistence and Lifecycle (Existing TASK-303/410)
- **Priority**: P2
- **Dependencies**: BR-400, BR-202; PostgreSQL tests (BR-105)
- **Scope**: `device_commands` lifecycle, idempotent command IDs, desired vs reported state, audit linkage.
- **Acceptance Criteria**:
  - Commands persist `pending`, `dispatched`, `acknowledged`, `failed`, or `timed_out` transitions with timestamps and actor.
  - Device-reported state changes only after verified ACK/telemetry confirmation.
  - Timeout is configurable and justified by measured network/firmware behavior, not fixed to five seconds by assumption.
  - Duplicate command retries are idempotent; stale/out-of-order ACK does not overwrite newer state.
  - Tests cover all transitions and failure behavior via transport fake.

#### BR-402 — Implement Authorized Relay Control (Existing TASK-303)
- **Priority**: P2
- **Dependencies**: BR-401; physical hardware test plan approved
- **Scope**: Relay-only actuation with interlocks, teacher/admin permissions, command acknowledgment.
- **Acceptance Criteria**:
  - Unauthorized requests are rejected without command dispatch.
  - Desired state is shown as pending; UI reports active only after positive ACK/reported state.
  - Timeout/failure leaves reported state unchanged and presents an actionable error.
  - Tests include device offline, failed ACK, timeout, duplicate request, relay allowlist, and cross-school attempt.
  - Hardware-in-the-loop acceptance is recorded before marking physical operation complete.

#### BR-403 — Reboot, Calibration Downlink, and Raw Modbus Command Controls (Existing TASK-410/412)
- **Priority**: P2
- **Dependencies**: BR-400, BR-401, approved operations safety policy
- **Scope**: Implement each command as a separate bounded flow; raw arbitrary frame injection stays disabled.
- **Acceptance Criteria**:
  - Each command uses explicit allowlisted type and validated payload; no arbitrary hex-to-device operation.
  - CRC/frame rules follow verified firmware; ACK and audit event required.
  - Device unavailable/timeout does not yield a success message.
  - Physical tests document recovery/safe state and supervisor role requirements.

---

### Milestone 5 — Core Operational Flows, Retention & Legacy Isolation (P1/P2)

#### BR-500 — Schedules, Class and Group Workflow (Existing TASK-406/608)
- **Priority**: P1
- **Dependencies**: BR-104, BR-202
- **Scope**: Schedule CRUD, teacher/class/group assignment, kit allocation, no sequential module lock assumption.
- **Acceptance Criteria**:
  - Schedule mutations are authenticated and authorized by school/class scope.
  - Conflicts are enforced for kit, group, teacher, and class according to approved collision matrix.
  - Concurrency race tests execute against PostgreSQL and reject conflicting overlapping transactions.
  - Module order remains teacher/schedule-driven unless Product Owner approves prerequisites separately.

#### BR-501 — Dashboard and Monitoring Page Reconciliation (Existing TASK-301/302)
- **Priority**: P1
- **Dependencies**: BR-300, BR-301, BR-202, BR-500
- **Scope**: Dashboard operational overview, monitoring per station, real data/provenance/empty states.
- **Acceptance Criteria**:
  - Dashboard and monitoring have distinct responsibilities and school-scoped queries.
  - KPIs have documented formulas, units, freshness and role visibility.
  - No stale or demo data is presented as live; data timestamps and source are visible.
  - Tests cover empty, stale, cross-school, and role-scoped views.

#### BR-502 — Reports, Assessment Summary, and Directory (Existing TASK-404/405/407)
- **Priority**: P2
- **Dependencies**: BR-203, BR-204, BR-202
- **Scope**: One task per page after specification; this umbrella only tracks dependencies, not a combined implementation ticket.
- **Child tasks**:
  - **BR-502A — Practicum Reports Hub**: DONE. Same-school teacher/report filtering and student self-scoping, module/status/class filters, fail-closed school auth; `PracticumReportHubTest` (9 tests, 31 assertions). Commits `448e94f`, `4fc603d`.
  - **BR-502B — Competency Assessment Summary and CSV Export**: DONE. Guest rejection, teacher school isolation, superuser school/class filters, student self-scoping, streaming CSV with school-scoped records; `CompetencyAssessmentTenantTest` (7 tests, 33 assertions). Commits `ffbf64a`, `cb32b11`.
  - **BR-502C — Academic User Directory (read-only)**: DONE. Teacher/superuser access only, fail-closed inactive/null school checks, teacher school isolation, superuser aggregate/school filter, role tabs and search; `UserDirectoryTest` (8 tests, 29 assertions). Commits `a591cf2`, `7ea42e3`.
- **Acceptance Criteria**:
  - Separate child tasks are created for reports hub, assessment summary/export, and directory CRUD/read-only behavior.
  - Each has its own role/class/school authorization matrix and feature tests.
  - No multi-page work is marked complete as one task.
- **QC**: Full suite: 257 passed, 4 skipped, 0 failures across 1154 assertions. Dedicated child test suites pass. UI redesign remains deferred; directory functionality is read-only as scoped.

#### BR-503 — School-Scoped Settings and Append-Only Audit Trail (Existing TASK-413/414)
- **Priority**: P1
- **Dependencies**: BR-103, BR-202
- **Scope**: School settings, thresholds, and sensitive action history.
- **Acceptance Criteria**:
  - Settings and thresholds have school ownership and unit/range validation.
  - Audit records contain actor, school, action, target, before/after diff (sensitive fields redacted), timestamp, and correlation identifier.
  - Operational UI cannot mutate/delete audit records.
  - Tests verify tenant isolation, permission denial, append-only invariants, and no secrets in logs.

#### BR-504 — Legacy Subscription/Midtrans Isolation (Existing TASK-501/502/503)
- **Priority**: P2
- **Dependencies**: None for inventory; explicit PO approval for route/UI policy; no destructive action without separate approval
- **Scope**: Inventory route/controller/model/migration/data dependencies, maintain rollback and product decision record.
- **Acceptance Criteria**:
  - Dependency inventory is complete and route exposure documented.
  - Midtrans routes/data are not deleted, migrated, or truncated in this task.
  - Hiding UI entry points or disabling public routes occurs only after explicit approval.
  - Any later data retention/removal is a separate reviewed task.

---

### Milestone 6 — Verification, Regression, Accessibility & Release Readiness (P1/P2)

#### BR-600 — Security and Academic/IoT Feature Test Matrix (Existing TASK-601–610)
- **Priority**: P1
- **Dependencies**: Corresponding implementation task for each flow
- **Scope**: Expand all existing test areas with ownership, provenance, concurrency, and physical safety evidence.
- **Acceptance Criteria**:
  - Guest/wrong-role/cross-school/IDOR tests cover every sensitive route.
  - LKS tests cover session/device evidence, immutable attempts, finality and revision.
  - Telemetry tests cover provenance, calibration version, heartbeat, stale/empty UI.
  - Command tests cover pending/ack/failure/timeout and no false-success behavior.
  - Schedule tests cover PostgreSQL concurrency collisions.
  - Tests run in isolated SQLite and PostgreSQL configurations as applicable.
- **QC**: PASS. Full test suite: 265 passed, 4 skipped (legacy sandbox e2e disabled), 0 failures across 1208 assertions. Covered across `SchoolTenancyTest`, `ImmutableAttemptHistoryTest`, `ImmutableGradeHistoryTest`, `TelemetryProvenanceAndHonestEmptyStateTest`, `HeartbeatAvailabilityTest`, `SensorCalibrationProfileTest`, `ActivityScheduleTest`, `SchoolSettingsAndAuditTrailTest`, `UserDirectoryTest`, `CompetencyAssessmentTenantTest`, `PracticumReportHubTest`, and `RouteHealthAndSecurityMatrixTest`.

#### BR-601 — Full Route Health and Database Regression (Existing TASK-611/612)
- **Priority**: P1
- **Dependencies**: BR-600 and feature dependencies
- **Acceptance Criteria**:
  - All documented pages/routes return expected status for guest/authenticated roles.
  - Full supported test matrix passes and environment/version/output are recorded.
  - Test runs are proven isolated from development/production databases.
  - Git diff confirms `upi-edge/` and `dump_upiedge.sql` unchanged.
  - No false claims of physical hardware support without hardware-in-loop evidence.
- **QC**: PASS. All 57 application routes audited and verified for guest, student, teacher, and superuser roles in `RouteHealthAndSecurityMatrixTest` (8 tests, 54 assertions). Full 265-test matrix passing. PostgreSQL 17.6 migration status verified clean (all 25 migrations ran). Baseline assets `upi-edge/` and `dump_upiedge.sql` verified 100% pure and untouched (`git status --short -- upi-edge dump_upiedge.sql` exit 0). Physical hardware downlinks honestly labeled as BLOCKED.

#### BR-602 — UI/UX and Accessibility Sprint (Existing TASK-701–704)
- **Priority**: P2, deferred
- **Dependencies**: Functional behavior and data/security acceptance; user's visual specification
- **Acceptance Criteria**:
  - Separate approved visual direction per page/persona.
  - Empty/loading/error/permission-denied/provenance states are explicit and keyboard accessible.
  - Contrast, focus, responsive layout, and tabular/monospace sensor metrics are verified.
  - No fake data, decorative telemetry, or misleading “live” states.

---

### 2.7 — Historical Repository Status Reconciliation (2026-11-05)

The evidence below is historical and remains attributed to repository HEAD `894b1a391f684c3a9289809b68e0b27853207392`; it is not current acceptance evidence. Current verification for HEAD `bb9fe94f089575aac4c490239d78b59d68a7e198` and the current uncommitted topbar/backlog changes is recorded in §2.9. These historical SHAs and outputs are evidence pointers, not grounds to infer acceptance from commit titles.

- Historical focused classroom/import/LKS/security suite: **23 deprecated (125 assertions), no failures**.
- Historical `ModulePracticumTest`: **10 deprecated (46 assertions), no failures**.
- Historical full SQLite suite: **99 deprecated, 5 passed (460 assertions), no failures; successful exit**.
- Historical isolated PostgreSQL smoke suite: **31 deprecated (162 assertions)**; it did not establish a real two-session schedule race.

| Revised ID | Status | Evidence / implementation | Remaining acceptance / next action |
| :--- | :--- | :--- | :--- |
| BR-000 | PARTIAL | Carry-forward register now has explicit statuses, evidence, SHAs, and collision notes; see §1. | PO approval is still required for canonical collision mapping; root document is outside the `bitanic-app` Git worktree, so this edit cannot receive a commit SHA there. |
| BR-001 | PARTIAL | Role/auth protections exist on sensitive routes; relay state honesty in `5fcbc051b2c14cc363c936c6238666f456522317`; reboot honesty in `bb1fec24374676cf5d7f63c395e1c2005c062943`; V-1/V-3. | Complete route-by-route unauthenticated denial/no-state-mutation tests. Physical actuation remains unverified and must not be represented as active without ACK. |
| BR-002 | BLOCKED | No approved membership/identity decision record found. | PO/reviewer must decide multi-school membership, identifier uniqueness, school context, transfer/alumni semantics. |
| BR-003 | BLOCKED | No approved deployment topology record found. | Decide lab-local/cloud/hybrid, offline operation, network boundaries, backup, TLS, and secrets operations. |
| BR-100 | BLOCKED | `ERD_PROPOSAL.md` exists; no approval evidence. | Resolve ownership/delete policies and approve ERD before more tenancy schema work. |
| BR-101 | PARTIAL | Production seeder/import safeguards in `5daf9352c55792490c20839444bf4e9c5385f7c2`; V-1/V-3. | Complete repeated-run idempotence and production/demo isolation tests. |
| BR-102 | TODO | No completed table-by-table additive migration/backfill/rollback plan verified. | Inventory current schema and produce reviewed plan without creating migration files. |
| BR-103 | PARTIAL | Schools, classroom, student-group/profile ownership schema exists; classroom work in `754315b0b5df15b40d8934ad148e0f368293e6ca`; V-1/V-4. | Target `school_memberships`/class membership model and approved cross-school DB constraints/backfill are not shown complete. |
| BR-104 | PARTIAL | Student-to-class/group/workstation allocation exists; schedules and allocations exist in current code. The bounded two-session PostgreSQL 17.6 race passed on isolated `bitanic_test`; `PostgresScheduleConcurrencyRaceTest` now repeats independent-session verification (1 test, 12 assertions) with worker lock wait, commit, overlap rejection, zero worker row, and scoped fixture cleanup. | Full session/session-kit-allocation semantics and repeatable CI PostgreSQL service configuration remain unfinished. |
| BR-105 | PARTIAL | `pgsql_testing` fails closed to `bitanic_test`; PostgreSQL 17.6 integration suite passes 9 tests / 69 assertions in its recorded run. `PostgresScheduleConcurrencyRaceTest` passed against isolated PostgreSQL 17.6 on 2026-11-06 (1 test, 12 assertions), safely skips on default SQLite, and cleans scoped fixtures. | Add repeatable CI/local PostgreSQL service configuration; production DB and multi-version topology are not blockers to active development or UI handoff. |
| BR-200 | PARTIAL (requirements follow-up) | Authentication development is authorized for active development; login and initial-password lifecycle have feature coverage. | Record role redirects, recovery, session rotation/CSRF, rate-limit policy, and approval/provisioning lifecycle for disposable non-production QA accounts; do not turn this follow-up into a development stop gate. |
| BR-201 | PARTIAL (active development authorized) | `Auth\\AuthController`, password-change lifecycle and routes; `LoginTest` covers active-school membership rejection, throttle, logout, and generic invalid-credential behavior. Guest browser smoke passed. Read-only account inventory found no matching smoke-role accounts in `bitanic_db` or `bitanic_test`, so authenticated browser journeys are blocked on authorized QA-account provisioning. | After approval/provisioning, finish browser smoke for student, teacher and admin roles and resolve any route blockers. This is not production-release authorization. |
| BR-202 | DONE | School tenancy isolation, IDOR prevention, fail-closed enforcement for unassigned/null-school actors, and cross-school mutation blocks implemented across schedules, grading, user directory, reports, classrooms, trainer kits, and assessments. Verified in SchoolTenancyTest (51 assertions) & CorrectiveSprintIntegrityTest with 0 regressions. | Complete school-membership, null-school fail-closed, and cross-school IDOR test matrix verified with 0 regressions. |
| BR-203 | DONE (software workflow) | Append-only report attempts, session-window evidence scoping, graded-report lock, and teacher revision are implemented and covered by `ImmutableAttemptHistoryTest`, `LksAllocationAndEvidenceTest`, `ModulePracticumTest`. | UI journeys still need browser smoke; this does not block starting design handoff in active development. |
| BR-204 | DONE (software workflow) | Append-only grade history, school authorization, revision, locks, and rubric clamping are covered by `ImmutableGradeHistoryTest`. | UI journeys still need browser smoke. |
| BR-300 | PARTIAL (software provenance/empty states covered; shared topbar null state fixed in working tree) | `hardware|simulation|fixture` provenance, honest null/empty states, school-scoped reads, fresh-hardware anomaly gates, relay no-ACK behavior, and active/empty shared topbar cases are covered by provenance/dashboard tests. Full SQLite suite and guest browser evidence recorded in §2.9. | Continue distinguishing simulated/fixture/stale values from live and maintain broader route/role acceptance; never claim physical ACK or device telemetry from software tests. |
| BR-301 | DONE (software availability) | Device availability uses configured heartbeat timeout; only authenticated hardware ingress updates `last_seen_at`; simulation and fixture do not fabricate heartbeat; covered by `HeartbeatAvailabilityTest`. | Real physical telemetry and lab offline behavior still require external hardware/network confirmation, not a blocker to active UI redesign planning. |
| BR-302 | DONE (software layer) / BLOCKED (physical downlink) | Per-parameter profile and lineage storage, immutable raw readings and additive migration `2026_11_06_000001` are covered by `SensorCalibrationProfileTest`. | Physical calibration remains blocked until exact hardware datasheet, firmware protocol, ACK behavior, safety review and hardware-in-loop testing. Values verified in fixtures are not hardware-approved. |
| BR-303 | DONE | Device-key hash, prefix auditing, atomic rotation, revocation, and last-used tracking verified in tests. | Legacy key inventory/rotation/revocation fully operational and covered by feature tests. |
| BR-400 | BLOCKED | No firmware repository/version, transport, or ACK evidence recorded. | Obtain firmware/spec and approve transport/safety policy before downlink work. |
| BR-401 | TODO | No verified persistent command lifecycle with ACK/failure/timeout transitions. | Implement only after BR-400 and command policy are approved; test idempotency and out-of-order ACK. |
| BR-402 | BLOCKED | Depends on unverified BR-401 and physical safety plan. | Keep physical relay operation disabled until authorized transport and hardware-in-loop acceptance. |
| BR-403 | PARTIAL | Simulated reboot is now truthful without hardware ACK (`bb1fec24374676cf5d7f63c395e1c2005c062943`); V-3. | No verified physical reboot/calibration downlink; raw Modbus injection remains unapproved/disabled. |
| BR-500 | PARTIAL | Schedule allocation and sequential collision matrix work; `CreateScheduleAction` locks selected resources. `ActivityScheduleTest`'s “concurrent” case remains two sequential action calls; `PostgresIntegrationAndConcurrencyTest`'s lock test is not a two-session race. The bounded manual race passed and new `PostgresScheduleConcurrencyRaceTest` now passes on isolated PostgreSQL 17.6 `bitanic_test` (1 test, 12 assertions), observing distinct backend PIDs, worker lock wait, first booking commit, overlap rejection, zero worker rows, and scoped fixture cleanup. | Keep broader schedule/session acceptance partial until remaining session/allocation semantics and repeatable CI PostgreSQL service configuration are addressed. |
| BR-501 | PARTIAL (false topbar fallback corrected; broader acceptance remains) | Dashboard/monitoring scoping, anomaly gating and empty-state tests; `DashboardTest` checks both a present kit and the honest empty topbar. Guest browser smoke of `/` and `/schedules` returned HTTP 200 with no console errors or failed requests. Schedule-page data in the running development DB currently shows no kit choices or sessions; seeded `ActivityScheduleTest` verifies default-school kit visibility and cross-school isolation. | The shared topbar no longer fabricates a kit, `Live` status, RSSI or battery when the selected kit is null. Authenticated student/teacher/admin browser journeys remain blocked pending approved isolated QA accounts. Physical-lab telemetry remains outside software-only verification. |
| BR-502 | DONE (Child tasks verified separately) | BR-502A reports hub, BR-502B assessment/export, and BR-502C read-only directory are completed as independent tasks with dedicated auth/tenant/filter tests; commits `448e94f`–`7ea42e3`. | Full suite: 257 passed, 4 skipped, 0 failures across 1154 assertions. UI redesign remains deferred (BR-602); account CRUD is not included in this directory read-only task. |
| BR-503 | DONE | Enhanced `school_settings` and `audit_logs` tables with school ownership (`school_id`), target metadata, JSONB diff, and correlation UUID (`2026_01_01_000021_enhance_school_settings_and_audit_logs_tables`). Corrective QA completed: (1) QA-01 fail-closed school authorization (`abort(403)`) without fallback to default school and strict active-school verification; (2) QA-02 safe migration handling preserving legacy data without forced misattribution; (3) QA-03 append-only SQL triggers on PostgreSQL and SQLite blocking direct QueryBuilder/raw SQL mutation (`2026_01_01_000022_add_append_only_trigger_to_audit_logs`) with trigger bypass restricted to PostgreSQL superusers only (`usesuper = true`); (4) QA-04 atomic `DB::transaction()` wrapping settings mutation and audit creation with verified rollback on failure; (5) recursive sensitive field redaction in audit diffs. Verified with 22 tests (74 assertions) in `SchoolSettingsAndAuditTrailTest` and 1 test (7 assertions) in `LegacySchoolOwnershipMigrationTest`. | Full suite: 222 tests, 1008 assertions, 4 skipped (legacy e2e), 0 failures, 0 regressions. |
| BR-504 | DONE | Audited legacy B2C SaaS subscription and Midtrans checkout dependencies under LEGACY-01; safely retired commercial routes (`/subscription`, `/subscription/checkout`) returning 404; removed subscription links from sidebar navigation; retained active entitlement bridge `SubscriptionService` for vocational AI crop recommendation quota (`canConsumeAi()`); preserved all historic payment/subscription database tables, models, migrations, and seeders intact without deletion or truncation; added `@deprecated LEGACY-01` documentation headers. Verified with `LegacySubscriptionIsolationTest` (2 tests, 8 assertions), `MidtransWebhookTest` (3 tests), and `CropRecommendationQueueTest` (3 tests). | Full suite: 222 passed, 4 skipped (legacy sandbox e2e disabled), 0 failures across 1008 assertions. |
| BR-600 | DONE (software-testable scope) | Route and feature matrix now covers tenant isolation/IDOR, immutable attempts and grade history, telemetry provenance/calibration/heartbeat, schedule collision behavior, CSV exports, role authorization, and append-only audit invariants. `RouteHealthAndSecurityMatrixTest` (8 tests, 54 assertions). | Full suite: 265 passed, 4 skipped (legacy sandbox e2e disabled), 0 failures across 1208 assertions. Physical command lifecycle remains blocked under BR-400/401/402 pending firmware, transport, ACK, and safety approval. |
| BR-601 | PARTIAL (focused role tests and guest browser smoke pass; acceptance remains incomplete) | `RouteHealthAndSecurityMatrixTest` uses guest/student/teacher/superuser authorization coverage; fixture-owned trainer-kit IDs are used by student and teacher mutation denial checks (9 tests / 72 assertions). Current guest browser smoke of `/` and `/schedules` returned HTTP 200 with no browser console errors or failed requests. Historical inventory found no seeded role smoke accounts. Latest configured SQLite suite: 355 tests, 1901 assertions, 5 skipped, no failures. | Authenticated student/teacher/admin browser journeys require approved disposable test accounts. Hardware-in-loop remains unproven. Do not mark route/browser acceptance complete. |
| BR-602 | TODO — visual redesign not started (handoff only) | Direction reference: Trello https://trello.com/c/F8W2Egdh. `docs/UI_UX_REDESIGN_HANDOFF.md` is preparation for a future redesign, not an implementation, acceptance, or requirement to begin visual work now. | No redesign work has been authorized by this documentation update. Continue executable non-UI work and preserve the handoff as reference; begin redesign only when explicitly requested. Authenticated browser smoke remains blocked pending approved QA accounts. |

### 2.9 — Current Working-Tree Verification (2026-11-06)

This subsection records current topbar and backlog verification against pushed HEAD `bb9fe94f089575aac4c490239d78b59d68a7e198`; it supplements rather than rewrites historical evidence in §2.7.

- **Focused feature tests**: `ActivityScheduleTest.php` — 17 tests / 57 assertions and `DashboardTest.php` — 24 / 99 passed. `RouteHealthAndSecurityMatrixTest.php` — 9 / 72 passed after the deterministic-ID fix. `PostgresScheduleConcurrencyRaceTest.php` safely skips under default SQLite and passes explicitly on isolated PostgreSQL 17.6 `bitanic_test` — 1 test / 12 assertions, verifying separate backend PIDs, PostgreSQL lock wait, commit ordering, overlap rejection, zero worker rows, and scoped fixture cleanup.
- **Full configured SQLite suite**: 355 tests, 1901 assertions, 5 skipped, no failures (2026-11-06; rerun after all source commits).
- **Route authorization matrix**: `RouteHealthAndSecurityMatrixTest.php` — 9 tests / 72 assertions passed after replacing all hard-coded trainer-kit IDs in mutation denial checks with fixture-owned devices.
- **Browser smoke**: guest `/`, `/iot-monitoring`, `/analytics`, `/telemetry/history`, `/modules`, `/schedules`, `/trainer-kits`, and `/raw-logs` each returned HTTP 200; protected `/classes`, `/reports`, `/assessment`, `/assessment/export`, `/users-data`, `/settings`, and `/media` redirected to `/login` (final response HTTP 200). `/login` returned HTTP 200. Across 16 routes there were zero console errors and zero failed requests. Dashboard header showed `Tidak ada trainer kit terdaftar`. The running development DB had no trainer-kit choices or schedules on `/schedules`, so populated guest schedule display was not verified in the browser.
- **Role smoke limitation**: authenticated student/teacher/admin browser journeys remain blocked because no approved disposable role accounts are available. Feature authorization tests are not represented as logged-in browser evidence.
- **Schedule race verification**: the bounded manual run passed on isolated PostgreSQL 17.6 `bitanic_test`, observing distinct backend PIDs, worker wait event `Lock` / `transactionid`, first booking commit before overlap rejection, and zero persisted worker schedule rows. Repeatable PHPUnit coverage now passes (1 test, 12 assertions) with a separate worker process and scoped temporary fixtures; it fails closed outside `bitanic_test`, and default SQLite execution safely skips it. Cleanup is enforced in `finally` for the worker, transactions, fixture rows, and temporary files. The development DB was not used.
- **Scope/safety**: no redesign, account provisioning, development DB mutation, hardware operation, or protected baseline asset change. The topbar and backlog changes remain in the working tree; schedule/controller/race and route-fixture source/test changes are in pushed commits `d3d07ff` and `bb9fe94`. Browser services were left running.
- **Backlog synchronization**: canonical parent and tracked snapshot were byte-compared after the final evidence updates and are identical. The backlog is the only pending commit; its canonical copy is outside this Git worktree.

### 2.8 — Newly Recorded Follow-up Tasks

| New ID | Task / status | Evidence | Remaining work and guardrails |
| :--- | :--- | :--- | :--- |
| MAINT-01 | Group controllers into domain namespaces — DONE | Reorganized all 14 controllers into domain namespaces (`Academic\\`, `IoT\\`, `Agronomy\\`, `Admin\\`); preserved backward-compatible deprecated root wrappers; updated routes and imports; verified with `ControllerDomainNamespaceTest` (8 tests, 126 assertions). Commits `6c7c8bd`–`927b23e`, `fe0ded8`, `6c49eef`. | Complete with 0 regressions. |
| MEDIA-SEC-01 | Planting media tenant isolation, RBAC hardening, and functional views — DONE | Audited `/media` resource against PRD and Architecture; added additive migration for school ownership (`2026_01_01_000023_add_school_id_to_media_table`); secured routes with `auth` and `role:guru,superuser`; implemented fail-closed tenant resolution, school-scoped device and telemetry joins, cross-school IDOR protection; created missing functional views (`pages.media.index`, `pages.media.create`, `pages.media.edit`); updated role-aware sidebar navigation; verified with 11 tests (72 assertions) in `PlantingMediaSecurityAndCrudTest` and route matrix. | Complete with 0 regressions. |
| LEGACY-01 | Subscription/Midtrans dependency, route, and data-retention audit — DONE | Completed full dependency audit across routes, controllers, actions, services, models, migrations, and seeders. Disabled public subscription routes (`/subscription`, `/subscription/checkout`), removed sidebar link, preserved active entitlement bridge `SubscriptionService` for AI quota, and maintained all historic financial records intact with zero deletions or truncations. Verified with `LegacySubscriptionIsolationTest` (2 tests, 8 assertions). | Full suite: 222 passed, 4 skipped, 0 failures, 0 regressions. |
| IMPORT-01 | CSV/XLSX import completion — DONE | RFC 4180 streaming `fgetcsv` parser with multiline quote support, UTF-8 BOM, delimiter auto-detection; OpenXML XLSX workbook relationship & sheet resolver, coordinate-to-column index mapping for sparse rows, inline strings (`<is><t>`), cached formula values, XXE protection (`LIBXML_NONET`), zip bomb extraction guards, fail-closed tenancy, and 500-row preview/execute limits. Verified with 13 tests (104 assertions). | Complete with zero regressions across full test suite (121 tests, 601 assertions). |
| PASSWORD-01 | Secure initial password and account provisioning — DONE | Unique random initial secret generated per user, must_change_password flag added, first login redirect enforced, strong password validation (min 12 chars, letters, mixed-case, numbers, symbols), credentials stripped from preview API. Verified in PasswordLifecycleTest & BulkUserImportTest. | Implemented and verified with 0 regressions. |
| APIKEY-01 | Legacy device API key inventory and safe rotation — DONE | Additive migration for key_prefix and last_used_at, atomic rotation via rotateForDevice with pessimistic lock, superuser route and anti-slop fleet UI, touch last_used_at on valid ingest. Verified in SecurityHardeningAndRebootTest & AuthenticateDeviceApiKeyTest. | Implemented and verified with 0 regressions. |
| LKS-01 | LKS evidence integrity and submission lifecycle — DONE | Append-only `student_report_attempts` schema, immutable attempt history, session-window telemetry evidence scoping, honest empty snapshot for unallocated/no-telemetry states, graded-report modification lock, and teacher-authorized revision workflow. Verified across `ImmutableAttemptHistoryTest`, `LksAllocationAndEvidenceTest`, and `ModulePracticumTest`. | Complete with zero regressions across full test suite (136 tests, 654 assertions). |
| HEARTBEAT-01 | Heartbeat consistency across UI and policy — DONE | Unified `DeviceAvailabilityResolver` with configurable per-device/school/global timeout (default 900s) implemented across Dashboard, Monitoring, and Trainer Kit views. Authenticated hardware ingress exclusively updates `last_seen_at`. Verified with 11 tests (28 assertions) in `HeartbeatAvailabilityTest` under frozen-time conditions. | Full suite passes: 175 tests, 803 assertions, 0 failures, 0 regressions. |

---

## 3. Dependency Summary

```text
BR-000 (Backlog ID/status reconciliation)
 ├── BR-001 (Interim mutation lockdown) ──────────┐
 ├── BR-002 (Identity/membership policy)           │
 └── BR-003 (Deployment decision)                  │
         │                                         │
         ▼                                         │
BR-100 (ERD approval)                              │
 ├── BR-102 (Additive migration/backfill plan)     │
 │    ├── BR-105 (PostgreSQL test environment)     │
 │    └── BR-101 (Seed separation policy)           │
 └── BR-103 (Core tenant/academic schema) ◄────────┘
      ├── BR-104 (Sessions + kit allocations)
      ├── BR-202 (Authorization) ◄── BR-201 (active-development auth implementation)
      ├── BR-300 (Provenance/empty state)
      ├── BR-301 (Heartbeat)
      ├── BR-302 (Calibration, requires hardware spec)
      ├── BR-303 (Ingestion hardening)
      ├── BR-503 (School settings/audit)
      └── BR-500 (Schedules)
             │
             ├── BR-203 (LKS evidence/attempt history)
             ├── BR-204 (Grade/revision history)
             └── BR-501/502 (Page-specific operational flows)

Firmware access + topology decision
 └── BR-400 (Transport selection)
      └── BR-401 (Command lifecycle)
           ├── BR-402 (Relay control)
           └── BR-403 (Other device commands)

All released features ──► BR-600 tests ──► BR-601 regression ──► BR-602 UI/Accessibility
```

---

## 4. Open Decisions Requiring Technical Reviewer / Product Owner Sign-Off

1. **School membership semantics**: Can one global `users` identity be a member of multiple schools? Are NISN/NIP unique globally or per school?
2. **Deployment topology**: Local lab server, cloud, or hybrid? Does the MVP require offline-first operation?
3. **Academic group allocation**: Is a student in one practicum group per class/academic year or can groups vary per session?
4. **LKS evidence timing**: Capture one latest reading within session, allow a selected reading range, or store a set of readings? How stale can a reading be and remain valid?
5. **Report cardinality**: One report per student/session, per group/session, or one group report with individual reflection? The proposed ERD currently models individual report linked to session/group.
6. **Grade revision policy**: Who may reopen a graded report, which reasons are required, and what notifications are needed?
7. **Device freshness policy**: Is 15 minutes a reasonable initial proposal, and does it differ by protocol/heartbeat frequency?
8. **Sensor calibration**: Which exact hardware model, register values, calibration references and formulas are supported? Who signs off agronomic measurement validity?
9. **Physical operation policy**: Which teacher/admin roles can control relays? Are students ever permitted with a time-bounded supervised grant? Which safety interlocks are mandatory?
10. **Firmware repository and protocol**: Where is the ESP32 firmware source? Does it implement MQTT, HTTP REST downlink, or only uplink POST? What ACK/status protocol exists?
11. **Legacy commercial feature policy**: Hide entry points, leave routes active, or disable routes? Do not delete or migrate data until approved separately.
12. **Sequential module prerequisites**: No fixed linear lock or passing score is assumed; must be separately decided by teachers/PO.

---

## 5. Stop Gate & Handoff

- Authentication development is authorized during active development and remains subject to security, role, and tenant controls. This is not production deployment, launch, or credential-provisioning approval.
- Continue executable non-UI work using inspect → implement → test → fix → revalidate → update backlog → atomic commit → push → next task. Hardware mutation still requires verified protocol and positive ACK. Production infrastructure, CI/CD, and unavailable historical checksums are not active-development/UI-handoff blockers.
- The original `BACKLOG.md` remains unchanged. This is the active reconciliation target; any consolidation/ID normalization remains separate.
- Repository-layout note: the canonical backlog is in the parent directory and outside the `bitanic-app` Git worktree; synchronize it with the tracked snapshot before committing any backlog change.
- No visual redesign is started by this backlog or handoff update. Keep BR-602 as future design work until explicitly requested. The false null-device topbar status has been corrected in the current uncommitted working tree as a functional data-truthfulness fix.
- Current browser evidence: guest `/` and `/schedules` smoke returned HTTP 200 with no console errors or failed requests; the dashboard header showed the honest no-kit state. The development DB currently has no schedule kit options/sessions, so seeded test output is not being represented as live populated-browser evidence. Authenticated role journeys are blocked until disposable non-production role accounts are approved and provisioned. A student role-matrix 404-versus-403 fixture mismatch is recorded under BR-601.
- The prioritized work order in §6 is reconciled to present code/evidence and active development; it does not override the user's later explicit task sequence.

---

## 6. Recommended Next Work Order

This work order reflects the reconciled evidence at the application HEAD recorded above. It distinguishes executable non-UI fixes from tasks blocked on an external approval or hardware evidence. For each executable task, follow: inspect → implement → test → fix → revalidate → update both backlog copies → focused atomic commit → push → next task. A completed follow-up must not be listed again as pending work.

1. **Completed and verified — Correct false topbar telemetry (TASK-302 / BR-300 / BR-501; P1)**:
   - Removed the fabricated kit identity, unconditional `Live` label/pulse, RSSI, and battery values when no active device is selected; retained the real code/name display for an active device without redesign.
   - `DashboardTest` covers both present-device and no-device header states. Focused feature tests passed; the full configured SQLite suite after all source commits passed 355 tests / 1901 assertions with 5 skipped. Guest browser smoke of `/` returned HTTP 200 and displayed `Tidak ada trainer kit terdaftar`, with zero console errors or failed requests. The running DB's schedule page has no kits/sessions, so populated schedule browser output is not verified; seeded feature tests cover default-school visibility and cross-school isolation.

2. **Completed and pushed — Repair route-matrix fixture robustness (BR-601; P1)**:
   - Replaced hard-coded trainer-kit IDs in student reboot/API-key rotation and teacher API-key rotation authorization checks with fixture-owned IDs, preserving expected authorization results without depending on PostgreSQL sequence state.
   - `RouteHealthAndSecurityMatrixTest`: 9 tests / 72 assertions passed on configured SQLite after these corrections. Commit `bb9fe94` is pushed to `origin/main`.

3. **Completed and pushed — Repeatable schedule overlap race verification (BR-104 / BR-105 / BR-500; P1)**:
   - `PostgresScheduleConcurrencyRaceTest` passes against isolated PostgreSQL 17.6 `bitanic_test` (1 test, 12 assertions): separate backend sessions, observed PostgreSQL lock wait, first schedule committed before the worker resumes, overlapping device booking rejected, and no worker-titled row persisted. Default SQLite execution safely skips the PostgreSQL-only integration test.
   - `PostgresScheduleConcurrencyWorker.php` is a standalone worker guarded to `bitanic_test`; the test performs bounded waits and `finally` cleanup for the process, transaction, scoped fixture rows, and temporary paths. It was rerun after the syntax repair; no development DB was used.
   - The existing `ActivityScheduleTest::test_concurrent_overlapping_bookings_are_serialized_and_rejected()` remains sequential. Keep overall schedule/session acceptance partial for remaining class/group/session semantics and PostgreSQL CI service configuration. Commit `d3d07ff` is pushed to `origin/main`.

4. **Complete authenticated role browser smoke (TASK-201 / BR-201 / BR-601; blocked on approval)**:
   - Request approval and a safe procedure for disposable student, teacher, and admin QA accounts in an isolated non-production environment. After approved provisioning, execute the role journeys and record actual access outcomes.
   - Until then, report only the guest browser results and automated authorization-test evidence. Do not seed accounts, reset passwords, or otherwise change either populated development data or test data solely to bypass this blocker.

5. **Continue remaining partial tasks by verified dependency and priority**:
   - Reassess the carry-forward register and historical evidence before selecting a task. `PASSWORD-01`, `APIKEY-01`, `IMPORT-01`, `LKS-01`, `HEARTBEAT-01`, `MAINT-01`, `LEGACY-01`, and BR-503 corrective QA are recorded as implemented/verified; do not repeat their obsolete implementation steps.
   - Keep unresolved school/identity, ERD, deployment, firmware/transport, physical ACK, and hardware-in-loop decisions explicitly blocked where applicable. These do not authorize speculative schema, destructive legacy-data changes, physical commands, or a blanket stop to unrelated active development.

6. **BR-602 visual redesign remains deferred**:
   - `docs/UI_UX_REDESIGN_HANDOFF.md` is a reference only. This backlog reconciliation and the functional fixes above do not start or authorize a visual redesign. Reconfirm visual direction and scope only if/when the user explicitly requests that work.

The canonical parent-directory backlog and this tracked snapshot must stay synchronized before any backlog commit. The pending focused commit contains the snapshot only because the canonical copy is outside the Git worktree. Never claim verification that was not run; record test output, skips, failures, database target, browser blockers, and hardware limitations accurately.

---
