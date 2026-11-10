# BITANIC — Architecture Baseline v1.0
## Product Decisions, Implemented System Architecture & Gap Analysis

- **Document Version**: 1.0.0
- **Baseline Date**: 2026-11-10
- **Status**: Locked Architectural Baseline
- **Governing Precedence**: `PRD.md` > `ARCHITECTURE.md` > `BACKLOG_REVISED.md` > `AGENTS.md` > This Document

---

## 1. Locked Product Decisions (Platform vs. Tenant Scope)

The Product Owner has locked the architectural direction for the Bitanic LMS & IoT Workstation. The system distinguishes strictly between **Platform-Level Operations** (multi-school administration) and **Tenant-Level Operations** (single school administration and pedagogical execution).

```
+-----------------------------------------------------------------------------+
|                           BITANIC PLATFORM LEVEL                            |
|  - Multi-School Directory & Tenant Provisioning                             |
|  - Global Trainer Kit Master Catalog & Serial Inventory                     |
|  - Commercial School Licenses (1 School = 1 License) & Warranty Management  |
|  - Platform Audit Logging & Cross-School Monitoring                         |
|  - Actor: Superuser (Bitanic Ops / Principal Engineer)                      |
+-----------------------------------------------------------------------------+
                                      │
                   Multi-Tenant Tenant Boundary (school_id)
                                      ▼
+-----------------------------------------------------------------------------+
|                            TENANT (SCHOOL) LEVEL                            |
|  - School Profile & Custom Agro-Threshold Settings                          |
|  - Academic Staff & Student Management (Admin Sekolah, Guru, Siswa)         |
|  - Classrooms (Rombel), Student Groups & Trainer Kit Meja Allocations       |
|  - Practicum Modules (12 Kurikulum Merdeka ATPH 4.0), Schedules & LKS       |
|  - IoT Monitoring, Agronomy Analytics, Telemetry History (Assigned Fleet)   |
|  - Student Competency Gradebook & Rubric-Based Assessment                   |
+-----------------------------------------------------------------------------+
```

### 1.1 Target Role Architecture vs. Boundary
1. **Superuser (Platform)**: Global system operator with unrestricted visibility across all schools, master hardware catalog, and system settings. Superuser actions outside their assigned school require explicit tenant context parameters.
2. **Admin Sekolah (Tenant)**: Head of vocational lab or school IT administrator. Manages user accounts (teachers, students), school master settings, and class allocations strictly for their own school. **Does NOT have platform-wide access or global hardware master catalog mutation rights.**
3. **Guru (Teacher)**: Vocational instructor in ATPH. Creates classes, student groups, practicum schedules; supervises real-time telemetry; grades LKS submissions using 4-scale rubrics; conducts software calibrations.
4. **Siswa (Student)**: Vocational practicum student. Views assigned practicum modules; executes experiments on allocated Trainer Kits; submits digital LKS with snapshot evidence; views own verified competency grades.
5. **Guest (Public)**: Prospective students, external evaluators, or unauthenticated lab screens. Accesses public landing, curriculum catalog, and default school telemetry in read-only mode without sensitive metadata (IP, MAC, API keys).

---

## 2. Actual Implemented Architecture (Current System State)

### 2.1 Technology Stack
- **Framework**: Laravel 12 (PHP 8.2+) running on PostgreSQL 17/18 (tested on 17.6) and SQLite (in-memory test runner).
- **Frontend Architecture**: Blade templates styled with Tailwind CSS v4, reactive client state powered by Alpine.js, interactive charting with ApexCharts, and Vite 6 asset bundling.
- **Architectural Pattern**: 3-Tier Modular — Thin Controllers → Domain Actions / Services → Eloquent Models with composite indexes and fail-closed tenant scoping.

### 2.2 Implemented Roles (Current Runtime Reality)
In the current code (`app/Http/Middleware/EnsureUserHasRole.php`), exactly five identities operate at runtime:
- `superuser`: Has global system access and bypasses school-level tenancy checks.
- `admin`: **Current Legacy Implementation** — inherits both `guru` and `superuser` permissions within their school, which deviates from the locked target (see Section 3.1).
- `guru`: Authorized for class management, scheduling, LKS grading, telemetry monitoring, and software calibration within their `school_id`.
- `siswa`: Strictly isolated to submitting LKS, viewing own grades, and viewing assigned device monitoring.
- `guest`: Read-only access to public routes scoped to the default active school.

### 2.3 Route & Domain Architecture (56 Registered Routes)
The application groups functionality into 4 domain namespaces under `App\Http\Controllers`:
1. `Academic`: `ClassroomController`, `ModulePracticumController`, `PracticumReportController`, `CompetencyAssessmentController`.
2. `IoT`: `DashboardController`, `TelemetryMonitoringController`, `TrainerKitManagerController`, `RawLogsDebuggerController`.
3. `Agronomy`: `AgronomyAnalyticsController`, `PlantingMediaController`.
4. `Admin`: `UserDirectoryController`, `SchoolSettingController`.

---

## 3. Gap Analysis: Implemented State vs. Locked Baseline v1.0

The following gaps exist between the current codebase and the locked Architecture Baseline v1.0. These gaps are documented as technical debt and deferred backlog items, in accordance with the Product Owner's mandate not to perform ad-hoc breaking changes.

| # | Architecture Dimension | Target Locked Baseline v1.0 | Current Implementation State | Gap Severity | Action / Resolution Path |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **G-01** | **School Admin Role Separation** | Dedicated `admin_sekolah` role strictly scoped to their tenant. Cannot access global platform settings or other schools. | **Secured in Sprint 1 (`SEC-ADMIN-TENANT-READ`)**: Separated `User::isSuperuser()` to strictly check `role === 'superuser'`. Legacy `admin` now strictly scoped via `SchoolContextResolver`. Cross-school read queries (`?school_id=2`) strictly fail closed with HTTP 403 across all 10 controllers, gradebooks, and exports. School switcher is hidden unless `$isSuperuser && $schools->isNotEmpty()`. Platform operations (`kits.api-key.rotate`, `rawlogs.inject`) strictly restricted to superusers (`role:superuser`), while `/settings` allows own-school admin (`role:superuser,admin`). Full DB migration to distinct `admin_sekolah` role value deferred to `ARCH-RBAC-01`. | **Tenant Isolation Closed; DB Role Deferred** | Verified via `RbacBoundaryVerificationTest` (14/14 tests, 111 assertions on SQLite & PostgreSQL) and Playwright Browser E2E suite (8/8 scenarios PASS). |
| **G-02** | **Trainer Kit Master Catalog** | Global physical hardware inventory with serial numbers, MAC addresses, batch production records, and school assignment lifecycle. | Devices exist directly in `devices` table linked via `school_id`. No global master serial repository exists. | **Medium (Commercial Scope)** | Deferred to `DATA-MASTER-01` and future hardware inventory release. |
| **G-03** | **School License & Warranty Model** | 1 School = 1 Commercial License, 1-Year Hardware Warranty, automated expiration reminders, grace periods. | No licensing or warranty tables exist. School tenancy is controlled via `status = 'active'` flag on `schools`. | **Deferred (Commercial)** | Explicitly deferred by PO directive. Midtrans SaaS routes retired under `LEGACY-01`. |
| **G-04** | **Hardware Downlink & Physical Actuator ACK** | Bidirectional Modbus/RS485 transport over lab gateway with cryptographically verified frame delivery and hardware ACK for relay/reboot. | Soft-reboot and calibration are software-only simulations. UI explicitly flags them as simulated. Physical actuation is fail-closed. | **Accepted Constraint (Hardware Safe)** | Fully compliant with Hardware Safety Gate. Remains simulation-only until ESP32 firmware audit is conducted. |
| **G-05** | **Multi-School Selector Scope** | School switcher on dashboard and fleet view must be restricted strictly to Superusers. | **Resolved at HEAD `345eb73` & verified in `RbacAndMultiTenantAcceptanceTest`**: only superusers can filter/switch schools. | **Closed (Verified)** | Covered by automated test matrix. |
| **G-06** | **Gradebook Provenance** | Rapor Kompetensi gradebook must calculate final averages from verified student reports, ignoring stale user profile caches. | **Resolved at HEAD `60dd9d0` & verified in `CompetencyAssessmentTenantTest`**: removed all unverified profile fallbacks from controller and views; displays honest `—` state when zero graded reports exist; database profile columns preserved; CSV export and gradebook views agree. | **Closed (Verified)** | Covered by automated unit, feature, and regression test matrix. |

---

## 4. Open Academic & Hardware Policies

1. **Academic Evaluation Policy**:
   - Kurikulum Merdeka ATPH 4.0 defines 12 core practicum modules. Modules are teacher-driven; no artificial sequential lock is imposed unless configured by the school.
   - Grading Rubric uses a 4-scale competency model (1: Belum Kompeten [60%], 2: Cukup Kompeten [75%], 3: Kompeten [85%], 4: Sangat Kompeten [100%]).
   - Rapor Kompetensi and CSV export reflect only verified, graded `StudentReport` records. Students without graded reports show an honest unassessed state (`—`). Historical profile data is preserved without destructive data loss.
2. **Hardware & Modbus Transport Policy**:
   - Bitanic Trainer Kits (`BITANIC-TK-01` .. `TK-06`) utilize 7-in-1 Modbus RS485 soil sensors and 4-channel actuator relay boards.
   - Ingress requires an authenticated `X-Device-Key` hashed with SHA-256 (`btk_...`).
   - Telemetry provenance is strictly tagged: `hardware`, `simulation`, or `fixture`. Only authenticated hardware ingress updates `last_seen_at`.
   - Actuator downlinks (solenoid irrigation, dosing pump, misting, ventilation fan) and hardware reboots remain locked in simulation mode until physical hardware-in-the-loop (HIL) verification is completed.

---

## 5. Target Future Components (Post-MVP Roadmap)

```
[ Bitanic v1.0 Single-School MVP ] (Current Foundation)
               │
               ├─► [ ARCH-RBAC-01 ]: Migration of `admin` to dedicated `Admin Sekolah` role (resolving G-01)
               ├─► [ DATA-MASTER-01 ]: Centralized Hardware Serial Master & Batch Factory Provisioning
               ├─► [ SCHOOL-LICENSE-01 ]: B2B School Licensing Engine & Warranty Tracker
               ├─► [ BR-400..403 ]: Lab Gateway Agent (MQTT/Modbus-TCP) with Positive Hardware ACK
               └─► [ AI-ADVISOR-02 ]: Edge LLM Agronomic Anomaly Diagnostics with offline fallbacks
```

---

## 6. Verification & Quality Gates

This architecture baseline is backed by exhaustive automated and end-to-end evidence:
- **Unit & Feature Suite**: 418 passed, 0 failures, 5 skipped (2,593 assertions) on SQLite in-memory (`php artisan test`).
- **Role & Tenancy E2E Acceptance Suite**: `tests/Feature/RbacAndMultiTenantAcceptanceTest.php` (8 tests, 147 assertions) verifying all 5 roles, cross-school IDOR rejection, academic lifecycles, and hardware safeguards.
- **RBAC Boundary Verification & Tenant Isolation Suite**: `tests/Feature/RbacBoundaryVerificationTest.php` (14 tests, 111 assertions across SQLite and PostgreSQL) verifying separation of superuser identity, isolation of legacy admin to assigned school, fail-closed rejection of hostile cross-school query tampering (`?school_id=2`, `?school_id=99999`, `?school_id=abc`, `?school_id[]=1`), and isolation across all 10 read-surface controllers.
- **Real Browser E2E Interaction Test Suite**: `tests/Browser/e2e_playwright_test.py` (Playwright headless Chromium against isolated port 8008 environment `.env.browser_e2e`) verifying 8 comprehensive end-to-end user scenarios:
  1. *Superuser School Switching & Multi-School Access* (PASS).
  2. *Admin Same-School Dashboard & Absence of School Switcher* (PASS).
  3. *Admin Foreign-School Selector Tampering Fail-Closed 403* (PASS).
  4. *Admin Foreign-School Directory Search Isolation* (PASS).
  5. *Admin Foreign-School Gradebook & Export Access Rejection* (PASS).
  6. *Admin Unauthorized Direct URLs & Hardware Mutation Rejection* (PASS).
  7. *Normal Guru Workflow & Tenant Protection* (PASS).
  8. *Normal Siswa Workflow & Tenant Protection* (PASS).
- **PostgreSQL 17.6 Concurrency & Locking**: `PostgresIntegrationAndConcurrencyTest` (9 tests, 69 assertions), `RbacBoundaryVerificationTest` (14 tests, 111 assertions), and `RbacAndMultiTenantAcceptanceTest` (8 tests, 147 assertions) on isolated `bitanic_test`.
- **Code Style & Formatting**: Laravel Pint passed on all newly added and modified files.
- **Frontend Assets**: Vite production build succeeded (93 kB CSS, 1.87 MB JS).
- **Baseline Immutability**: `upi-edge/` and `dump_upiedge.sql` preserved with zero modifications.

