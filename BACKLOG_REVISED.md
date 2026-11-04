# Bitanic — Revised Implementation Backlog & Work Order
## Multi-School Ready Vocational LMS & IoT Workstation

- **Version**: 1.1.0  
- **Date**: 2026-11-05  
- **Status**: Repository reconciliation recorded; this document does not itself authorize new feature work. Existing implemented features must be maintained while remaining gates stay in force.  
- **Product Direction**: Single-School MVP with Multi-School Ready Architecture  
- **Methodology**: TDD, dependency-first delivery, task-isolated implementation, evidence-based QC.  
- **Audited repository HEAD**: `894b1a391f684c3a9289809b68e0b27853207392` (`bitanic-app`).  
- **Canonical file**: `C:\Users\user\Nata\MAKERINDO\Bitanic\BACKLOG_REVISED.md`; a synchronized tracked snapshot is maintained at `bitanic-app/BACKLOG_REVISED.md` because the canonical file is outside the Git worktree.  
- **Status vocabulary**: `DONE` = acceptance verified; `PARTIAL` = implementation exists but acceptance/coverage remains; `TODO` = no verified implementation; `BLOCKED` = requires an external decision, approval, or hardware prerequisite. `DEFERRED` is used only as a scheduling qualifier, not as a replacement status.

---

## 0. Governing Decisions & Hard Gates

1. **Architecture**: MVP serves one active school, but tenant-aware ownership is built into the schema from day one. No tenant registration, billing, subdomains, or database-per-tenant for MVP.
2. **Login Stop Gate**: Do not implement the login page or authentication flow until the Technical Reviewer & Product Owner approve this backlog and the auth requirements. However, high-risk mutation routes must be disabled/restricted immediately when implementation is authorized, not left publicly callable pending login.
3. **Hardware Safety**: The application must never report successful physical actuation without a positive device ACK. Until protocol selection and physical firmware integration are validated, hardware mutation endpoints are disabled or clearly labelled simulation-only behind authenticated authorization.
4. **Academic Integrity**: No global/latest telemetry may be attached to a student's work. Report submission, evidence binding, revision, and grade histories must be scoped and immutable.
5. **No Speculative Hardware Protocol**: Determine ESP32 transport based on the real firmware, device capabilities, lab network, and testing—not assumptions that MQTT or HTTP downlink exists.
6. **Legacy Commerce**: Midtrans, subscription, and payment data remain untouched until explicitly approved after dependency and retention review.
7. **Baseline Assets**: `upi-edge/` and `dump_upiedge.sql` are immutable reference/baseline assets.
8. **No destructive DB operations**: Do not use global `truncate()` or destructive reset against development/production data.
9. **Scope discipline**: One task should deliver one independently testable page, flow, or architectural concern. Shared schema foundations may be grouped only when inseparable.

---

## 1. Carry-Forward of Existing Backlog Tasks

This register carries the original IDs and conflicting historical meanings forward without renumbering them. Statuses below reflect source/test evidence available at repository HEAD `894b1a391f684c3a9289809b68e0b27853207392`, not commit subjects. `DONE` means acceptance is verified; `PARTIAL` means code exists but one or more acceptance criteria remain unverified; `TODO` means no implementation was verified; `BLOCKED` means a documented decision, approval, or external prerequisite is missing. A missing verification artifact is not treated as proof of completion.

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
| TASK-101 | Laravel 12 monolith `bitanic-app` | PARTIAL | HEAD `894b1a3`; working tree clean in app repository | Runtime baseline is present; verify supported PHP/Laravel matrix and prove baseline assets `upi-edge/` and `dump_upiedge.sql` are unchanged in the canonical repository. |
| TASK-102 | PostgreSQL 17/18 configuration | PARTIAL | V-4 (PostgreSQL 17 smoke) | Add repeatable isolated PostgreSQL test service/CI and supported-version matrix; SQLite does not establish PostgreSQL compatibility. |
| TASK-103 | Core telemetry/LMS schema | PARTIAL | HEAD schema/migrations inspected; V-3/V-4 | Map actual migrations/FKs to approved ERD and additive backfill plan; tenant constraints remain unverified against acceptance. |
| TASK-104 | Additional vocational schema and demo seeding | PARTIAL | `5daf935`; V-1/V-3 | Separate authoritative production data from fixtures; complete idempotence, retention, and secret-leak checks. |
| TASK-104A | Orchestrate `DatabaseSeeder` | PARTIAL | `5daf935`; V-1/V-3 | Production guard exists; verify idempotent repeated runs and full separation of demo fixtures after policy approval. |
| TASK-104B | Expand student/teacher fixtures | BLOCKED | No approved fixture source/count recorded | Obtain approved anonymized/demo fixture source and population requirements; never invent production users. |
| TASK-104C | Seeder integrity and retention tests | PARTIAL | `5daf935`; V-1/V-3 | Add complete idempotence, referential integrity, uniqueness, retention, and non-destructive safeguards. |
| TASK-105 | Device-key telemetry ingestion and async AI | PARTIAL | `5daf935`; V-1/V-3 | Device key hashing/revocation protection exists; finish legacy-key inventory/rotation, ownership/provenance/replay matrix, queue-failure behavior, and lab network policy. |
| TASK-111–126 | Per-page functional/security specifications | BLOCKED | Decision list §4; approvals not evidenced | Reconcile each page spec against approved tenancy, identity, freshness, LKS, and hardware decisions. Do not treat a range as collectively accepted. |
| TASK-201 | Login/session | PARTIAL | Login/auth routes and code exist at HEAD; no written stop-gate release is recorded | Existing code is not authorization to expand scope. Review auth requirements and document whether implementation is accepted or must be restricted; do not start new login work before approval. |
| TASK-202 | SSO integration | BLOCKED | No provider/credential approval recorded | Only proceed if a provider, owner, and integration scope are approved. |
| TASK-203 | Auth and role middleware | PARTIAL | Routes and feature tests at HEAD; V-1/V-3 | Complete school-membership/resource policies and guest, wrong-role, cross-school, and IDOR coverage for every sensitive route. |
| TASK-204 | Functional global navigation | PARTIAL | Current route/layout inventory; subscription links remain | Review role-aware navigation and isolate legacy commerce entry points after explicit PO decision. |
| TASK-301 | Lab dashboard | PARTIAL | HEAD dashboard/telemetry code; V-3 | Verify freshness/provenance, school/role scope, formulas, and empty/stale states. |
| TASK-302 | IoT monitoring page | PARTIAL | HEAD monitoring/telemetry code; V-3 | Audit all data paths for fabricated fallback, provenance labels, freshness policy, and empty/offline states. |
| TASK-303 | Relay hardware control | PARTIAL | `5fcbc051b2c14cc363c936c6238666f456522317`; V-3 | False reported relay state was corrected; physical command transport/ACK and hardware-in-loop acceptance are not established. Keep physical operation disabled/simulation-labelled. |
| TASK-304 | Agronomy analytics | PARTIAL | Analytics page/routes at HEAD; V-3 | Verify school/device scoping, time zone, units, query provenance, and agronomic review. |
| TASK-305 | Telemetry history and CSV | PARTIAL | History/export routes at HEAD; V-3 | Verify authorization, scope, export bounds, provenance, and stale/empty behavior. |
| TASK-401 | Practicum module catalog | PARTIAL | Module page and `ModulePracticumTest`; V-2 | Obtain teacher content review and verify role/class access; do not impose unapproved sequential locking. |
| TASK-402 (original collision) | Schedule/allocation in original backlog | PARTIAL | Schedule code/tests at HEAD; V-3/V-4; classroom allocation `754315b` | Preserve this mapping; verify overlap policy, class/group/session scope, and PostgreSQL concurrent collision rejection. |
| TASK-402 (LKS in later Phase 2) | LKS submission (`TASK-402-LKS` temporary label) | PARTIAL | `1084278`, `b9fd578`; V-1/V-2 | Session-scoped attempt/revision history, approved evidence timing, and complete finality rules remain. |
| TASK-403 (original) | Telemetry history in Phase 1 summary | PARTIAL | History/export pages at HEAD; V-3 | Preserve mapping; finish authorization, export, provenance, and freshness audit. |
| TASK-403 (Phase 2) | Rubric grading | PARTIAL | Module grade route/test at HEAD; V-2/V-3 | Add same-school/class/session authorization, immutable grade history, revision workflow, and concurrency tests. |
| TASK-404 (original) | Analytics in Phase 1 summary | PARTIAL | Analytics page at HEAD; V-3 | Preserve original mapping; complete data-scope, units, and agronomic review. |
| TASK-404 (Phase 2) | Report hub | PARTIAL | Report route/page at HEAD; V-3 | Define filters/detail/revision policy and add class/session authorization tests. |
| TASK-405 | Assessment/grade report | PARTIAL | Assessment page/route at HEAD; V-3 | Approve aggregation/export policy and retain grade revision history. |
| TASK-406 | Schedule CRUD | PARTIAL | Schedule CRUD at HEAD; V-3/V-4 | Complete class/group/session allocation semantics and race-safe PostgreSQL collision tests. |
| TASK-407 | Student/teacher directory | PARTIAL | Directory route/page at HEAD; V-3 | Separate read directory from account administration and verify school/class membership scope. |
| TASK-408 | Trainer kit manager | PARTIAL | `bb1fec2`; V-3 | Simulated reboot is honestly labelled; complete device ownership/freshness, heartbeat consistency, action policy, and actual firmware integration before physical-operation claims. |
| TASK-409 (original) | Raw Modbus logs in Phase 1 summary | PARTIAL | Raw-log page/code at HEAD; V-3 | Preserve mapping; prove source/provenance and ensure display does not imply hardware I/O. |
| TASK-409 (Phase 2) | Calibration | PARTIAL | Calibration storage/UI at HEAD; V-3 | Add sensor-specific profile/version application, validation, audit, effective time, and approved reference vectors. |
| TASK-410 (original) | Settings and audit | PARTIAL | Settings/audit surface at HEAD; V-3 | Complete school scope, sensitive mutation coverage, redaction, and append-only enforcement. |
| TASK-410 (Phase 2) | Reboot/OTA operations | PARTIAL | `bb1fec2`; V-3 | Reboot is simulation-only without device ACK; physical reboot/OTA remains unimplemented pending firmware/transport and safety approval. |
| TASK-411 | Raw Modbus logs | PARTIAL | Raw-log implementation at HEAD; V-3 | Label fixture/manual/sensor source; verify no raw command is sent by default and no hardware I/O is implied. |
| TASK-412 | Modbus command transmission | BLOCKED | No verified transport/firmware ACK evidence | Requires firmware audit, allowlist, frame/CRC specification, ACK, physical test plan, and safety sign-off. |
| TASK-413 | School/sensor settings | PARTIAL | Settings surface at HEAD; V-3 | Enforce school ownership, unit/range validation, role policy, and append-only changes. |
| TASK-414 | Audit trail | PARTIAL | Audit model/usage at HEAD; V-1/V-3 | Verify all sensitive mutations, actor/school/target/correlation fields, redaction, and resistance to edit/delete. |
| TASK-415 | Anomaly alerts | TODO | No agronomy-approved lifecycle/threshold evidence recorded | Define reviewed thresholds, alert lifecycle, and safe non-prescriptive copy before implementation is considered complete. |
| TASK-501 | Legacy commercial inventory | PARTIAL | `routes/web.php` subscription routes; checkout/controller, payment/webhook, models, migrations, seeder, service, and tests inventoried at HEAD | Record complete dependency/data/route map in LEGACY-01; preserve historic data. |
| TASK-502 | Hide/disable commercial entry points | BLOCKED | PO policy not recorded | Obtain explicit route/UI policy; no deletion or public-route changes before approval. |
| TASK-503 | Commercial data retention/migration decision | BLOCKED | Retention/removal approval not recorded | Decide retention/ownership and migration only after inventory; no destructive work without separate approval. |
| TASK-601–612 | Feature, authorization, DB, regression tests | PARTIAL | V-1–V-4; `CorrectiveSprintIntegrityTest`, LKS, classroom, import tests at HEAD | Map individual IDs to acceptance; add missing tenancy/IDOR, immutable attempt, concurrency, provenance, heartbeat, and CI evidence. |
| TASK-701–704 | UI/UX sprint and accessibility | TODO — DEFERRED | No redesign approval; existing functional UI is not redesign acceptance | Keep redesign deferred until system behavior/data/security is accepted and user provides visual direction; accessibility defects affecting functionality remain separately actionable. |

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

#### BR-200 — Finalize Authentication Requirements (Existing TASK-111; STOP GATE)
- **Priority**: P0
- **Dependencies**: BR-002, TASK-126 specification
- **Scope**: Specify account lifecycle, credential/reset policy, login/logout/session, throttling, recovery, demo policy, post-login behavior.
- **Acceptance Criteria**:
  - Product Owner approves role and redirect matrix, rate limits, account status handling, password lifecycle, CSRF/session rotation, and demo account stance.
  - No login code or migration is implemented in this task.
  - **Development stop gate remains closed until separate explicit approval.**

#### BR-201 — Implement Authentication & Session Lifecycle (Existing TASK-201)
- **Priority**: P0
- **Dependencies**: BR-200; BR-103; explicit written stop-gate release
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
- **Dependencies**: BR-103, BR-105; sensor firmware/model specs; qualified agriculture/hardware review
- **Scope**: Preserve raw register payload and record calibration profile/version applied to derived readings; sensor-type-specific transform only.
- **Acceptance Criteria**:
  - Each supported parameter has a documented unit, device register mapping, range, calibration method, and firmware compatibility.
  - No universal `raw + offset` formula is applied without a sensor specification.
  - Ingested readings preserve immutable raw values; derived values record calibration profile/version/effective time.
  - Calibration changes are permissioned, auditable, and do not rewrite historical readings.
  - Tests validate approved reference vectors and boundary/invalid profiles.

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
- **Acceptance Criteria**:
  - Separate child tasks are created for reports hub, assessment summary/export, and directory CRUD/read-only behavior.
  - Each has its own role/class/school authorization matrix and feature tests.
  - No multi-page work is marked complete as one task.

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

#### BR-601 — Full Route Health and Database Regression (Existing TASK-611/612)
- **Priority**: P1
- **Dependencies**: BR-600 and feature dependencies
- **Acceptance Criteria**:
  - All documented pages/routes return expected status for guest/authenticated roles.
  - Full supported test matrix passes and environment/version/output are recorded.
  - Test runs are proven isolated from development/production databases.
  - Git diff confirms `upi-edge/` and `dump_upiedge.sql` unchanged.
  - No false claims of physical hardware support without hardware-in-loop evidence.

#### BR-602 — UI/UX and Accessibility Sprint (Existing TASK-701–704)
- **Priority**: P2, deferred
- **Dependencies**: Functional behavior and data/security acceptance; user's visual specification
- **Acceptance Criteria**:
  - Separate approved visual direction per page/persona.
  - Empty/loading/error/permission-denied/provenance states are explicit and keyboard accessible.
  - Contrast, focus, responsive layout, and tabular/monospace sensor metrics are verified.
  - No fake data, decorative telemetry, or misleading “live” states.

---

### 2.7 — Repository Status Reconciliation (2026-11-05)

Status and evidence below refer to repository HEAD `894b1a391f684c3a9289809b68e0b27853207392`. Implementation SHAs and exact test outputs are recorded in §1. They are evidence pointers, not grounds to infer acceptance from commit titles. Test output available for this reconciliation:

- Focused classroom/import/LKS/security suite: **23 deprecated (125 assertions), no failures**.
- `ModulePracticumTest`: **10 deprecated (46 assertions), no failures**.
- Full SQLite suite: **99 deprecated, 5 passed (460 assertions), no failures; successful exit**. PHP 8.5 emitted repeated `PDO::MYSQL_ATTR_SSL_CA` deprecation notices.
- Isolated PostgreSQL 17 smoke suite (`bitanic_test`): **31 deprecated (162 assertions), no failures**; the temporary test database was dropped after testing. This is not a full PostgreSQL regression/concurrency suite.

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
| BR-104 | PARTIAL | Student-to-class/group/workstation allocation exists (`754315b0b5df15b40d8934ad148e0f368293e6ca`); V-1/V-4. | No complete session and session-kit-allocation model or concurrent PostgreSQL validation; carry forward schedules safely. |
| BR-105 | PARTIAL | One isolated PostgreSQL 17 smoke run V-4. | Add repeatable documented/CI PostgreSQL config, fail-closed DB isolation, JSONB/timezone/FK/lock/concurrency tests. |
| BR-200 | BLOCKED | No written authentication-requirements approval or gate release recorded. | Obtain approval for lifecycle, password/reset, throttling, sessions, roles, and demo stance before auth expansion. |
| BR-201 | PARTIAL | Login/session code and routes already exist at HEAD; existing feature coverage is included in V-3. | Prior implementation does not prove gate approval. Review against BR-200, document acceptance, and do not expand scope until explicit release. |
| BR-202 | DONE | School tenancy isolation, IDOR prevention, fail-closed enforcement for unassigned/null-school actors, and cross-school mutation blocks implemented across schedules, grading, user directory, reports, classrooms, trainer kits, and assessments. Verified in SchoolTenancyTest (51 assertions) & CorrectiveSprintIntegrityTest with 0 regressions. | Complete school-membership, null-school fail-closed, and cross-school IDOR test matrix verified with 0 regressions. |
| BR-203 | DONE | Append-only `student_report_attempts` table with immutable attempt records, session-window telemetry evidence scoping, graded-report lock, and teacher-authorized revision workflow. Verified with 13 dedicated tests (47 assertions) in `ImmutableAttemptHistoryTest` plus existing `LksAllocationAndEvidenceTest` (6 tests) and `ModulePracticumTest` (10 tests). | Full suite: 136 tests, 654 assertions, 0 failures, 0 regressions. |
| BR-204 | DONE | Append-only `grade_history` table for immutable assessment records with adjustment reason, transition tracking (`initial_grade` / `revision`), strict teacher/superuser authorization, school-tenancy enforcement, pessimistic locking, and rubric score clamping. Verified with 9 dedicated tests (33 assertions) in `ImmutableGradeHistoryTest` and 0 regressions. | Full suite: 145 tests, 687 assertions, 0 failures, 0 regressions. |
| BR-300 | DONE | Explicit `hardware|simulation|fixture` provenance implemented across API and simulation commands; honest empty states rendered with em dashes and 'Belum Ada Transmisi Data' without fabricated metrics; guest queries on Dashboard, Monitoring, History, and CSV export strictly scoped to default active school; unmeasured sensor metrics stored as null instead of coerced to 0.0; relay defaults to false; sensor card defaults to null; verified with 17 dedicated tests (99 assertions) in `TelemetryProvenanceAndHonestEmptyStateTest`. | Full suite: 181 tests, 840 assertions, 0 failures, 0 regressions. |
| BR-301 | DONE | Deterministic heartbeat availability policy implemented via `DeviceAvailabilityResolver` with configurable timeouts per device/school and 900s baseline; only authenticated hardware ingress updates `last_seen_at`; legacy ETL import assigns `null` last_seen_at without forging historical traffic; consistent status shared across Dashboard, IoT Monitoring, and Trainer Kit Manager; verified with 12 tests (31 assertions) in `HeartbeatAvailabilityTest`. | Full suite: 181 tests, 840 assertions, 0 failures, 0 regressions. |
| BR-302 | PARTIAL | Calibration storage/UI exists at HEAD; V-3. | Sensor-specific profile application, raw/calibrated provenance, audit/effective time, approved reference vectors, and agronomy/hardware review remain. |
| BR-303 | DONE | Device-key hash, prefix auditing, atomic rotation, revocation, and last-used tracking verified in tests. | Legacy key inventory/rotation/revocation fully operational and covered by feature tests. |
| BR-400 | BLOCKED | No firmware repository/version, transport, or ACK evidence recorded. | Obtain firmware/spec and approve transport/safety policy before downlink work. |
| BR-401 | TODO | No verified persistent command lifecycle with ACK/failure/timeout transitions. | Implement only after BR-400 and command policy are approved; test idempotency and out-of-order ACK. |
| BR-402 | BLOCKED | Depends on unverified BR-401 and physical safety plan. | Keep physical relay operation disabled until authorized transport and hardware-in-loop acceptance. |
| BR-403 | PARTIAL | Simulated reboot is now truthful without hardware ACK (`bb1fec24374676cf5d7f63c395e1c2005c062943`); V-3. | No verified physical reboot/calibration downlink; raw Modbus injection remains unapproved/disabled. |
| BR-500 | PARTIAL | Schedule CRUD and classroom/group/workstation allocation exist; V-1/V-4. | Approve collision matrix and add PostgreSQL concurrent transaction rejection tests. |
| BR-501 | PARTIAL | Dashboard and monitoring pages exist; V-3. | Verify distinct responsibilities, formulas, provenance/freshness, role/school scope, empty/stale/cross-school states. |
| BR-502 | PARTIAL | Reports, assessment, and directory pages/routes exist; V-3. | Split to child scopes, approve role/class/school matrices, and verify report/assessment semantics and exports. |
| BR-503 | PARTIAL | Settings and audit surfaces exist; V-3. | Verify school ownership, unit/range validation, sensitive mutation coverage, redaction, append-only protection. |
| BR-504 | PARTIAL | Subscription routes, checkout/webhook, payment models/migrations/seeder/service and tests inventoried. | Finish LEGACY-01 dependency/retention decision; do not hide routes or modify historic data without explicit PO approval. |
| BR-600 | PARTIAL | V-1 through V-4; feature coverage includes LKS, security, classroom, and import. | Add missing tenancy/IDOR, immutable attempts, calibration provenance, heartbeat consistency, command lifecycle, and concurrency coverage. |
| BR-601 | PARTIAL | Full SQLite suite V-3 and PostgreSQL smoke V-4 pass. | Add full route/role matrix, repeatable PostgreSQL CI, version/output record, and baseline asset diff verification. |
| BR-602 | TODO — DEFERRED | No redesign approval; functional UI additions do not count as redesign completion. | Do not start redesign until system acceptance is complete and user provides a visual direction. Fix accessibility blockers in functional work separately. |

### 2.8 — Newly Recorded Follow-up Tasks

| New ID | Task / status | Evidence | Remaining work and guardrails |
| :--- | :--- | :--- | :--- |
| MAINT-01 | Group controllers into domain namespaces — TODO | Current controllers remain primarily under `App\\Http\\Controllers`; no domain-grouping commit. | After stabilization, move controllers into bounded Academic, IoT, Agronomy, and Admin domains; update namespaces, routes, and tests in focused changes. Do not update README or refactor unrelated repository areas. |
| LEGACY-01 | Subscription/Midtrans dependency, route, and data-retention audit — PARTIAL | `routes/web.php` exposes subscription index/checkout; checkout/webhook controllers, actions, models, migrations, seeder, service, composer lock dependencies, and payment tests inventoried at HEAD. | Complete dependency/data ownership map, runtime route exposure and rollback plan; request PO decision to retain, hide, or retire. No deletion, migration, truncation, or route/UI disabling without separate written approval. |
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
      ├── BR-202 (Authorization) ◄── BR-201 (Login, after explicit gate release)
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

- The previous blanket label “NO IMPLEMENTATION AUTHORIZED” is stale: repository implementation exists and is audited above. This reconciliation records evidence; it does not retroactively approve prior work or authorize a new feature sprint.
- Login/authentication scope remains **BLOCKED** until the Product Owner and Technical Reviewer approve requirements and explicitly release the gate. Existing authentication code is tracked as PARTIAL, not taken as evidence of approval.
- Each remaining task must follow its dependencies and its own approvals. Hardware downlink/physical actuation, commercial route/data changes, new schema/backfill work, and UI/UX redesign retain their specific gates. Do not begin the next implementation sprint as part of this backlog audit.
- The original `BACKLOG.md` remains unchanged. This file is the active reconciliation target; any later consolidation/ID normalization requires approval.
- Repository-layout note: the canonical file is physically in the project parent directory while the Git worktree is `bitanic-app`; the synchronized tracked snapshot lives at `bitanic-app/BACKLOG_REVISED.md`. Keep them synchronized on future edits.
- UI/UX redesign (`BR-602` / `TASK-701–704`) remains **TODO — DEFERRED** until functional, data, and security acceptance is complete and the user provides an approved visual direction.
- The next work order is in §6; this reconciliation does not start any of those tasks.

---

## 6. Recommended Next Work Order

Based on current code status, test evidence, security risks, and blocked dependencies, the recommended sequence of work for upcoming sprints is:

1. **Step 1 — Immediate Security & Credential Hardening (P0)**:
   - **`PASSWORD-01`**: Remove plaintext initial password from `users.import.preview` API response and UI preview table. Implement temporary initial password generation, forced password reset flag on first login, and secure delivery/reset token workflow.
   - **`APIKEY-01`**: Audit legacy unhashed API keys in seeders or legacy data imports; ensure complete migration to hashed keys with non-disruptive rotation and validation.

2. **Step 2 — Data Import & LKS Evidence Completeness (P1)**:
   - **`IMPORT-01`**: Complete CSV/XLSX import parser edge cases [x] DONE. Robust RFC 4180 streaming `fgetcsv`, OpenXML relationship resolver, sparse coordinate index mapping, inline strings, XXE & zip-bomb protection, fail-closed tenancy, and unified 500-row limits. Verified with 13 tests (104 assertions).
   - **`LKS-01` / `BR-203`**: Finalize session-scoped immutable attempts for student LKS submissions. Transition from mutable `attempt_count` updates to dedicated append-only attempt records, and enforce strict session time-window evidence checks.

3. **Step 3 — Hardware Telemetry & Heartbeat Consistency (P1)**:
   - **`HEARTBEAT-01` / `BR-301`**: Unify device availability computation into a single service/resolver used identically by Dashboard, Monitoring, and Kit Manager views. Implement per-school configurable timeout threshold.

4. **Step 4 — Codebase Architecture & Domain Organization (P2)**:
   - **`MAINT-01`**: Reorganize `app/Http/Controllers` into domain-specific subdirectories (`Academic/`, `IoT/`, `Agronomy/`, `Admin/`). Update route bindings, controller imports, and feature tests in focused, atomic micro-commits.
   - **`LEGACY-01` / `BR-504`**: Submit the completed subscription/Midtrans dependency inventory to the Product Owner for formal decision (hide routes, retain, or deprecate). Do not delete models or data until written PO approval is granted.

5. **Step 5 — Automated PostgreSQL CI & Concurrency Verification (P1)**:
   - **`BR-105`**: Set up automated PostgreSQL testing in CI / local test script with concurrent transaction collision tests for `ActivitySchedule` and kit allocations.

6. **Step 6 — UI/UX Redesign Sprint (P2 — DEFERRED)**:
   - **`BR-602`**: Strictly deferred until all functional, security, and data integrity milestones are fully accepted and visual styling guidelines are provided by the user.

