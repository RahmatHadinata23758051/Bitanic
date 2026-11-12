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

### 2.4 Superuser Data Master Architecture (Sprint 3 / Pack A)
Delivered in Sprint 3 / Pack A under dedicated `App\Http\Controllers\Admin` namespace, exclusively managed by `superuser`:
1. **MASTER-03-01: Indonesian Administrative Region Master (Wilayah Indonesia)**:
   - Complete reference data: 38 Provinsi, 514 Kabupaten/Kota, and verified Kecamatan (West Java, DKI, Banten priority) based on authoritative Kemendagri / BPS standards.
   - Zero runtime external API dependencies; bundled as verified offline local JSON datasets under `database/data/wilayah/`.
   - Tracked with metadata table `region_dataset_metadata` recording source, version `2024.1`, record counts, and SHA-256 checksums.
   - Idempotent artisan command `php artisan bitanic:sync-indonesian-regions`.
   - Dedicated searchable, paginated UI under `/master/regions` and cascading API endpoints `/api/regions/provinces`, `/api/regions/regencies`, `/api/regions/districts`.
2. **MASTER-03-02: Global School Master CRUD (Master Sekolah)**:
   - Central tenant registry built directly upon the core `School` model with additive regional foreign keys (`province_code`, `regency_code`, `district_code`).
   - Full Superuser CRUD under `/master/schools` with search, status filtering, province filtering, and cascading dynamic selects in Alpine.js.
   - Non-destructive backfill for legacy schools (`SMK PPN 1 Sukaraja` -> Prov 32, Kab 3202, Kec 3202170).
   - Strict deletion guard: prohibits hard deletion of schools with associated users, devices, schedules, or append-only audit logs; displays informative feedback recommending archival via status toggle (`inactive`).
   - Append-only audit logging: every school creation, update, status toggle, and deletion is recorded in `audit_logs`.

### 2.5 School User & Academic Master Architecture (Sprint 3 / Pack B)
Delivered in Sprint 3 / Pack B (`MASTER-03-03` + `MASTER-03-04`), providing complete user and academic hierarchy management with fail-closed tenant scoping and strict role boundary enforcement:
1. **MASTER-03-03: School Teacher/Student Master**:
   - Single-user lifecycle management under `/users` for Teachers (`role: guru`) and Students (`role: siswa`).
   - Tenant-scoped account creation, profile editing, and safe active/inactive status toggle (`is_active` boolean on `users` table via migration `000027_add_is_active_to_users_table.php`).
   - Inactive user login guard in `AuthController`: authentication attempts by deactivated accounts are rejected with clear user-facing feedback without leaking tenant details.
   - Deletion safety: users linked to student reports or audit logs are protected against hard deletion and must be archived via active status toggle. Self-deactivation and self-deletion are strictly blocked.
   - Bulk CSV/XLSX user import under `/users/import/preview` and `/users/import/execute` with format autodetection (semicolon/tab/BOM, sparse cells, inline strings), secret provisioning (`must_change_password`), and audit logging.
   - **NISN is strictly optional**: single creation and bulk import allow empty `identifier_number`.
   - **Authorization lockdown**: User import and user creation/mutation routes are restricted strictly to `admin_sekolah`, `admin`, and `superuser`. `guru` and `siswa` receive HTTP 403 Forbidden.
2. **MASTER-03-04: Academic Year, Classroom, Groups, and User Allocation**:
   - Classroom management under `/classes`: create, edit, delete with homeroom teacher assignment.
   - Model booted isolation: `Classroom::booted()` and `StudentGroup::booted()` enforce fail-closed tenancy without arbitrary fallback schools. `StudentGroup` derives its `school_id` directly from its parent `Classroom`.
   - Student groups with Trainer Kit (`device_id`) and group leader assignment; rejects cross-school devices or students from different schools.
   - Student allocation under `POST /students/{student}/allocation`: assigns student to classroom, group, and workstation kit with school matching and audit logging.
   - Academic year & semester settings under `POST /classes/academic-year`: persists school configuration in `school_settings` with audit trail.
   - Anti-enumeration IDOR protection: foreign school classroom/group access aborts with HTTP 404 (Not Found).

### 2.6 Global Trainer Kit Physical Inventory, Sale Records, and Activation Architecture (Sprint 4 / Pack A)
Delivered in Sprint 4 / Pack A (`DEVICE-04-01` + `DEVICE-04-02`), establishing complete physical inventory lifecycle tracking separated cleanly from local school workstation devices:
1. **DEVICE-04-01: Global Trainer Kit Physical Inventory Master**:
   - Physical inventory tracking model `TrainerKitInventory` backed by migration `2026_01_01_000029_create_trainer_kit_inventories_and_sales_tables.php`.
   - Centralized factory stock registration with unique `serial_number`, `model_name`, `hardware_revision`, `batch_number`, and lifecycle status (`registered` -> `sold` -> `assigned` -> `activated` -> `maintenance` -> `decommissioned`).
   - Strict platform-only authorization via `TrainerKitInventoryPolicy`: exclusively accessible by `superuser` (`role:superuser`). Tenant administrators, teachers, and students receive HTTP 403 Forbidden.
   - Centralized management UI under `/master/trainer-kits` with real-time fleet inventory metrics (Total Unit, Stok Pabrik, Terjual / Dimiliki Sekolah, Aktif Operasional), serial/batch search, status/school filtering, and factory stock registration form.
2. **DEVICE-04-02: Sale Records, Ownership Assignment, and Platform Activation**:
   - Commercial sales logging via `TrainerKitSaleRecord` establishing formal legal ownership between manufacturer inventory and purchasing school (`school_id`).
   - Concurrency-safe workstation device binding (`AssignTrainerKitDeviceAction`): binds physical serial to a school's workstation `Device` record using database transaction and `lockForUpdate()`. Prevents double-assignment collisions across tenants.
   - Workstation fleet transparency: workstation manager (`/trainer-kits`) displays verified factory serial numbers with visual provenance badge (`Hardware SN: BTK-...`) while respecting school isolation.
   - Append-only lifecycle tracking (`TrainerKitLifecycleLog`): tracks every lifecycle transition (`REGISTERED`, `SALE_RECORDED`, `DEVICE_ASSIGNED`, `ACTIVATED`, `METADATA_UPDATED`) with timestamps, actor attribution, status diffs, and notes.
   - Targeted P0 security hardening: tenant administrators (`admin_sekolah`, legacy `admin`) strictly prohibited from modifying administrator roles, editing/deactivating/deleting administrators, or self-escalating role in `UserDirectoryController::update()`. Single user onboarding mandates initial password change gate (`must_change_password => true`), dispatches reset tokens, and conceals plaintext secrets.
   - Device fallback removal: eliminated arbitrary fallback to `smk-ppn-1-sukaraja` or `School::first()` in `Device::booted()`, and dropped PostgreSQL `DEFAULT 1` constraint on `devices.school_id` via migration `000028`.

### 2.7 Physical Handover (BAST), 1-Year Hardware Warranty, and Lifecycle History (Sprint 4 / Pack B)
Delivered in Sprint 4 / Pack B (`DEVICE-04-03` + `DEVICE-04-04`), alongside the critical state-machine integrity fix `DEVICE-SALE-STATE-01`:
1. **DEVICE-SALE-STATE-01: Sale State Integrity & Unauthorized Resale Protection**:
   - Enforces strict lifecycle state transition in `RecordTrainerKitSaleAction`: only units in `registered` factory stock status (`canBeSold()`) can be sold.
   - Rejects unauthorized resale or silent reassignment of sold, assigned, activated, or decommissioned units with HTTP 422 `ValidationException`.
   - Supports idempotent sale retries: matching existing sale reference and purchasing school returns the existing inventory record without duplicate lifecycle or audit logs.
   - Enforces school active status verification before sale recording.
2. **DEVICE-04-03: Physical Handover (BAST) & 1-Year Hardware Warranty**:
   - Physical handover records modeled via `TrainerKitHandover` backed by migration `2026_01_01_000030_create_trainer_kit_handovers_and_warranties_tables.php`.
   - Records official BAST metadata (`bast_number`, `handover_date`, `recipient_name`, `recipient_title`, `recipient_phone`, `notes`, `recorded_by`).
   - Independent 1-year hardware warranty: warranty commencement is decoupled from sales date, serial registration, or software activation. Warranty begins strictly upon explicit physical handover confirmation by Superuser.
   - Exact calendar-year calculation with leap-year handling: `addYearNoOverflow()` ensures calendar fidelity (e.g. `2024-02-29` -> `2025-02-28`).
   - Superuser warranty date correction via `CorrectTrainerKitWarrantyAction`: permits Superuser to correct warranty dates with mandatory audit reason (`min:5` chars), recalculates expiration date, logs immutable history in `TrainerKitWarrantyLog`, and emits `WARRANTY_CORRECTED` lifecycle and `AuditLog` records.
   - Truthful state presentation: units without recorded handover display explicit "Belum Dicatat" status; expired units display "Kedaluwarsa"; valid units display "Aktif".
3. **DEVICE-04-04: Lifecycle History & School Warranty Transparency**:
   - Superuser detail workstation under `/master/trainer-kits/{id}`: provides modals for physical handover recording and warranty date correction with reason, displays complete BAST info, warranty validity period, warranty correction history, and chronological lifecycle audit trail (`HANDOVER_RECORDED`, `WARRANTY_COMMENCED`, `WARRANTY_CORRECTED`).
   - Superuser inventory index filter: added `warranty_status` filtering (`all`, `active`, `expired`, `not_recorded`) with visual status badges and expiration date display.
   - School workstation visibility (`/trainer-kits`): tenant administrators (`admin_sekolah`, legacy `admin`) can view their school's authorized trainer kit factory serials, BAST numbers, and warranty validity in a read-only capacity.
   - Fail-closed tenant isolation & anti-tampering: tenant administrators, teachers, and students are strictly forbidden (HTTP 403) from accessing `/master/trainer-kits` or mutating handover/warranty records. Foreign school warranty metadata is strictly protected against IDOR.

### 2.8 School LMS License Architecture & Superuser Management (LICENSE-05-01 & LICENSE-05-02)

1. **One School = One LMS License Contract**:
   - Every active educational institution has exactly one canonical LMS license record (`school_licenses`), enforced by a database unique constraint on `school_id`.
   - The deployment or purchase of multiple Trainer Kits (`BITANIC-TK-01`..`TK-06`) never multiplies or creates redundant software licenses.
   - Decoupled from automated payment gates: no billing system, no invoice generator, no Midtrans, and no automatic entitlement inferred from hardware sales or BAST handovers. All activations and renewals require explicit Superuser authorization.
2. **Entitlement State Machine & Boundary Mathematics**:
   - Dates are evaluated on date-only boundaries under the half-open interval `[start_date, expiration_date)`:
     - `pending`: `today < start_date` or unactivated (`start_date IS NULL`).
     - `active`: `start_date <= today < expiration_date`.
     - `expired`: `today >= expiration_date`.
   - Exact 1-calendar-year duration is calculated with leap-year safety using `addYearNoOverflow()` (e.g. `2024-02-29` to `2025-02-28`).
3. **Superuser License Management Workstation (`/master/licenses` & `/master/licenses/{id}`)**:
   - Centralized registry displaying metric summary cards (Total Lisensi, Lisensi Aktif, Menunggu Periode/Pending, Lisensi Kedaluwarsa, Sekolah Belum Berlisensi).
   - Case-insensitive search across license number, school name, and NPSN.
   - Status filters (`all`, `active`, `pending`, `expired`, `unentitled_only`).
   - Single-click pending license initialization for unentitled active schools.
   - Explicit First-Year Entitlement activation (`LICENSE-05-01`), linking initial 1-year term (`SchoolLicenseTerm::TYPE_INITIAL_INCLUDED`).
   - Annual manual renewal (`SchoolLicenseTerm::TYPE_ANNUAL_RENEWAL`): chains seamlessly from current expiry (or today if expired) without overwriting historical terms.
   - Date correction action with mandatory audit reason (`min:5` characters) recorded in `school_license_logs`.
4. **School Admin Visibility & Multi-Tenant Isolation**:
   - Dedicated school license view (`/school/license`) and School Settings tab (`/settings?tab=license`) allowing Admin Sekolah (`admin_sekolah`, legacy `admin`) to review their institution's license status, valid dates, remaining active days, and historical term ledger.
   - Read-only capacity: mutation endpoints reject tenant administrators with HTTP 403.
   - Fail-closed isolation: cross-school queries and tampering are strictly denied.
   - Guru, Siswa, and unauthenticated guests are completely forbidden from viewing or manipulating license registries.

---

## 3. Gap Analysis: Implemented State vs. Locked Baseline v1.0

The following gaps exist between the current codebase and the locked Architecture Baseline v1.0. These gaps are documented as technical debt and deferred backlog items, in accordance with the Product Owner's mandate not to perform ad-hoc breaking changes.

| # | Architecture Dimension | Target Locked Baseline v1.0 | Current Implementation State | Gap Severity | Action / Resolution Path |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **G-01** | **School Admin Role Separation** | Dedicated `admin_sekolah` role strictly scoped to their tenant. Cannot access global platform settings or other schools. | **Sprint 1 & 2 Pack B Execution (`ARCH-RBAC-01`, `RBAC-02-03`)**: Separated `User::isSuperuser()` to strictly check `role === 'superuser'`. Dual-role compatibility supports `admin_sekolah` and legacy `admin`. Cross-school read queries (`?school_id=2`) strictly fail closed with HTTP 403. School switcher is hidden unless `$isSuperuser && $schools->isNotEmpty()`. Platform operations (`kits.api-key.rotate`, `rawlogs.inject`) strictly restricted to superusers (`role:superuser`). Platform superuser has `school_id = NULL`. Implicit default school fallback removed. | **Closed via Pack B Execution** | Verified via dual-driver test suites and Playwright Browser E2E suite. |
| **G-02** | **Trainer Kit Master Catalog** | Global physical hardware inventory with serial numbers, MAC addresses, batch production records, and school assignment lifecycle. | **Sprint 4 Pack A Execution (`DEVICE-04-01`, `DEVICE-04-02`)**: Implemented `trainer_kit_inventories`, `trainer_kit_sale_records`, and `trainer_kit_lifecycle_logs` tables. Full Superuser UI under `/master/trainer-kits`, sale recording, school binding with `lockForUpdate`, activation workflow, and serial attribution on school workstation manager. | **Closed via Sprint 4 Pack A Execution** | Verified via dual-driver test suites (44 assertions) and Playwright Browser E2E scenarios 19 & 20. |
| **G-03** | **School License & Warranty Model** | 1 School = 1 Commercial License, 1-Year Hardware Warranty, automated expiration reminders, grace periods. | **Hardware Warranty Closed in Sprint 4 Pack B; School LMS License Closed via Sprint 5 Pack A Execution (`LICENSE-05-01`, `LICENSE-05-02`)**: Implemented canonical `school_licenses` (One School = One License constraint), append-only `school_license_terms`, and `school_license_logs`. Superuser workstation with lifecycle management (initialization, first-year activation, annual renewal without overwriting past history, date correction with mandatory reason). School Admin read-only visibility on `/school/license` and `/settings` license tab. Half-open interval convention `[start_date, expiration_date)` with leap-year safety. Multi-tenant fail-closed security. | **Closed (Verified)** | Verified via dual-driver test suite `SchoolLicenseManagementTest` (82 assertions) and Playwright Browser E2E Scenario 24. |
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
- **Unit & Feature Suite (SQLite)**: 591 passed, 0 failures, 5 skipped (3,372 assertions) on SQLite in-memory (`php artisan test`).
- **PostgreSQL 17.6 Test Suite**: 592 passed, 0 failures, 4 skipped (3,384 assertions) on isolated `bitanic_test` (`$env:DB_CONNECTION="pgsql_testing"; php artisan test`).
- **Data Master UX & Business Flow Refinement Suite (`DATA-MASTER-UX-01`)**: `tests/Feature/SchoolDistributionAndMasterUxTest.php` (7 tests, 52 assertions across SQLite and PostgreSQL) verifying:
  1. *Wilayah Partner Geographic Distribution*: `/master/regions` prioritizes real partner school distribution over dry administrative reference lists. Evaluates active/pending/expired license statuses via `SchoolLicenseResolver`, displays interactive Leaflet.js map with verified coordinates, clusters markers, provides reset zoom to Indonesia, renders hierarchical Province -> Regency -> District drill-down tree with partner counts, and cleanly isolates unmapped schools (*"Lokasi belum dipetakan"*) to prevent false coordinate artifacts. Reference Kemendagri dataset browsing is seamlessly preserved under `?view=reference`.
  2. *Trainer Kit Cumulative Business KPIs & Operational Tabs*: `/master/trainer-kits` replaces misleading disjoint counters with 5 cumulative business metrics: `Total Unit Fisik`, `Stok Belum Terjual` (`status = 'registered'`), `Unit Milik Sekolah` (`whereNotNull('school_id')`), `Terhubung Workstation` (`whereNotNull('device_id')`), and `Aktif Operasional` (`status = 'activated'`). Provides 5 intuitive operational filter tabs (`Semua Unit`, `Stok Pabrik`, `Milik Sekolah`, `Dalam Perawatan`, `Dipensiunkan`), clear explanatory notes on overlapping states, and actionable inventory management.
  3. *School-Rooted LMS License Registry*: `/master/licenses` roots query in `School::with(['license.terms', 'provinceRelation'])` guaranteeing 100% of registered schools appear in the registry (including uninitialized ones). Distinguishes `Belum Memiliki Lisensi` (action: Inisialisasi), `Belum Diaktivasi` (action: Aktivasi), `Menunggu Efektif` (action: Kelola), `Aktif` (action: Kelola), and `Kedaluwarsa` (action: Perpanjang), with 5 summary cards for commercial entitlement oversight.
  4. *Additive GIS Coordinates on School Master*: Additive migration adding `latitude`, `longitude`, `coordinate_source`, `coordinate_verified_at` with composite index. School Master CRUD supports optional coordinate inputs, coordinate verification badge in school detail, and coordinate validation (`between:-90,90` and `between:-180,180`).
- **High-Resolution Visual Verification Screenshots**: Captured via Playwright automation in `docs/screenshots/` across Desktop (1440x900) and Mobile (390x844):
  - `wilayah_overview_desktop.png` & `wilayah_overview_mobile.png` (Leaflet map pins, summary metrics, directory table).
  - `wilayah_drilldown_desktop.png` (Geographic Explorer opened to Jawa Barat drill-down).
  - `trainer_kit_overview_desktop.png` & `trainer_kit_overview_mobile.png` (5 KPI cards, lifecycle distribution note, operational tabs).
  - `trainer_kit_detail_desktop.png` (Workstation link, warranty status, lifecycle history).
  - `license_registry_desktop.png` & `license_registry_mobile.png` (School-rooted table with diverse license states).
- **Security Release Gate & Authentication Hardening Suite (`SEC-AUTH-SEED-01` & `RELEASE-READINESS-01`)**: `tests/Feature/SchoolLicenseReleaseReadinessAndHardeningTest.php` (18 tests, 81 assertions across SQLite and PostgreSQL) verifying:
  1. *Authentication Strictness*: Elimination of dev fallback passwords (`password123`, `Password123!`, `password`) and silent hash mutation from `AuthController::login()`. Exclusively uses stored bcrypt credentials via standard `Auth::attempt()`.
  2. *Production-Safe Seeder Guards*: Hard `\RuntimeException` prevention on direct invocation of demo seeders (`VocationalLmsSeeder`, `TrainerKitSeeder`) in production and staging. Reference seeders (`IndonesianRegionsSeeder`, `SubscriptionPlanSeeder`, `ModulePracticumSeeder`) execute safely in all environments, while demo users, test credentials, and fake devices (`TK-01`) are strictly isolated from operational databases.
  3. *Email Failure Recovery & Indeterminate Delivery State*: 5-minute worker lease (`claimed_at > now()->subMinutes(5)`) preventing race conditions, automatic recovery of orphaned processing notifications, transition to `status = unknown` on indeterminate SMTP failures (timeouts, broken pipes) at max attempts (3x) to strictly prevent duplicate email spam.
  4. *Scheduler Timezone Determinism*: Explicitly configured daily reminder scheduler pinned to `Asia/Jakarta` at 07:00 WIB (00:00 UTC), evaluated with WIB calendar day boundaries.
  5. *Fail-Closed Tenancy & Telemetry Independence*: Authenticated non-Superusers lacking `school_id` or with inactive schools fail closed with HTTP 403 or redirect, while IoT hardware telemetry ingress (`POST /api/v1/telemetry/ingest`) operates unimpeded.
- **Staging & Production Deployment Runbook**: Established at `docs/runbooks/PRODUCTION_DEPLOYMENT_RUNBOOK.md` detailing cron scheduler setup (`0 7 * * *`), queue worker supervisor configurations, dead-letter retry policies, database backup/rollback procedures, production seeder boundaries, and rollout activation sequences.
- **School LMS License Email Reminders Suite**: `tests/Feature/SchoolLicenseEmailReminderTest.php` (6 tests, 24 assertions across SQLite and PostgreSQL) verifying automated H-14 reminder notification generation, genuine active school admin recipient selection (`admin_sekolah`, legacy `admin`), idempotency and duplicate avoidance, dry-run simulation mode (`--dry-run`), license threshold date filtering, and job execution revalidation that skips already-renewed licenses.
- **School LMS License Enforcement Suite**: `tests/Feature/SchoolLicenseEnforcementTest.php` (8 tests, 30 assertions across SQLite and PostgreSQL) verifying safe rollout with feature flag disabled by default, Superuser unhindered access, unauthenticated/guest login bypass, Siswa & Guru redirection to polite vocational suspension notice (`/school/license-expired`), Admin Sekolah restricted access (can view `/school/license` and `/settings` but blocked from LMS mutations), JSON/AJAX 403 response, zero disruption to IoT hardware telemetry ingress (`/api/v1/telemetry`), and instantaneous access restoration upon Superuser annual renewal.
- **School LMS License Entitlement Correctness Suite**: `tests/Feature/SchoolLicenseEntitlementCorrectnessTest.php` (6 tests, 31 assertions across SQLite and PostgreSQL) verifying `SchoolLicenseResolver` interval evaluation, inter-term gap resolution (prior expired term + future renewal resolves to expired during the gap), seamless chained renewal coverage, leap-year boundaries, and Superuser explicit school context requirement (redirection to `/master/licenses` when accessing `/school/license` without `school_id`).
- **School LMS License Management Suite**: `tests/Feature/SchoolLicenseManagementTest.php` (11 tests, 82 assertions across SQLite and PostgreSQL) verifying One School = One License contract, isolation from Trainer Kit unit counts, Superuser-only manual activation, 1 calendar-year duration with leap-year protection (`addYearNoOverflow()`), half-open boundary convention `[start_date, expiration_date)`, annual renewal appending to term ledger without overwriting history, date correction with mandatory audit reason (`min:5`), Admin Sekolah read-only visibility, and fail-closed tenant IDOR protection.
- **Trainer Kit Handover & Warranty Suite**: `tests/Feature/TrainerKitHandoverAndWarrantyTest.php` (18 tests, 72 assertions across SQLite and PostgreSQL) verifying mandatory BAST check on warranty correction (`WARRANTY-BAST-GUARD`), consistent owning school, pending status for future start dates, physical BAST handover, independent 1-year warranty commencement, exact calendar-year leap-year calculations (`addYearNoOverflow()`), mandatory audit reason on warranty correction, unauthorized resale rejection (`DEVICE-SALE-STATE-01`), idempotent sale retry, warranty status filtering, school workstation visibility, and fail-closed IDOR protection.
- **Trainer Kit Inventory Master Suite**: `tests/Feature/TrainerKitInventoryMasterTest.php` (15 tests, 44 assertions across SQLite and PostgreSQL) verifying serial registration, validation, search/filtering, sale recording, school ownership assignment, concurrency-safe workstation binding with `lockForUpdate()`, platform activation, lifecycle audit logging, and strict superuser policy protection.
- **School User Master Suite**: `tests/Feature/SchoolUserMasterTest.php` (17 tests, 85 assertions) and `tests/Feature/BulkUserImportTest.php` (16 tests, 114 assertions) verifying teacher/student CRUD, active/inactive account status toggle, login blocking for deactivated accounts, deletion guards for linked users, optional NISN, and role-boundary enforcement (Guru & Siswa strictly forbidden from account provisioning and bulk import).
- **Academic Master & Allocation Suite**: `tests/Feature/AcademicMasterAndAllocationTest.php` (9 tests, 41 assertions) and `tests/Feature/ClassroomManagementTest.php` (6 tests, 32 assertions) verifying classroom and group management, fail-closed booted tenancy (no arbitrary default schools), homeroom teacher validation, trainer kit group binding, student allocation to classroom & workstation kit, and academic year settings.
- **Indonesian Region Master Suite**: `tests/Feature/IndonesianRegionMasterTest.php` (9 tests, 54 assertions across SQLite and PostgreSQL) verifying authoritative Kemendagri codes, hierarchy navigation, idempotent sync command, checksum validation, metadata record tracking, cascading endpoints, and superuser-only mutation control.
- **Global School Master CRUD Suite**: `tests/Feature/SchoolMasterCrudTest.php` (16 tests, 71 assertions across SQLite and PostgreSQL) verifying school listing, search, status/province filtering, full CRUD lifecycle, hierarchy validation, unique NPSN/slug, deletion guard (linked users, devices, schedules, and audit logs protected), status toggling, and tenancy boundary enforcement.
- **User Provisioning & Tenancy Contract Suite**: `tests/Feature/UserProvisioningAndTenancyContractTest.php` (25 tests, 70 assertions across SQLite and PostgreSQL) verifying school membership invariants, student data isolation on reports and assessments, school-scoped CSV exports, classroom & student group CRUD lifecycle, schedule conflict validation, LKS submission and grading cycle, missing-grade provenance (no fabricated historical fallback), device reboot & calibration simulation, superuser settings filtering vs admin locking, and migration 000024 `down()` default verification.
- **Operational Role Migration Safety Suite**: `tests/Feature/RoleMigrationCommandTest.php` (7 tests, 38 assertions across SQLite and PostgreSQL) verifying dry-run, forward execution, idempotence, rollback exact restoration, rollback conflict detection without force, rollback override with force, unique microsecond snapshot UUID and checksum verification.
- **Real Browser E2E Interaction Test Suite**: `tests/Browser/e2e_playwright_test.py` (Playwright headless Chromium against isolated port 8008 environment `.env.browser_e2e`) verifying 26 comprehensive end-to-end user scenarios (100% PASS):
  1. *Superuser School Switching & Multi-School Access* (PASS).
  2. *Admin Same-School Dashboard & Absence of School Switcher* (PASS).
  3. *Legacy Admin Compatibility Session* (PASS).
  4. *Admin Foreign-School Selector Tampering Fail-Closed 403* (PASS).
  5. *Admin Foreign-School Directory Search Isolation* (PASS).
  6. *Admin Foreign-School Gradebook & Export Access Rejection* (PASS).
  7. *Admin Unauthorized Direct URLs & Hardware Mutation Rejection* (PASS).
  8. *Normal Guru Workflow & Tenant Protection* (PASS).
  9. *Normal Siswa Workflow & Tenant Protection* (PASS).
  10. *Real UI Mutation: Admin Sekolah Classroom Creation* (PASS).
  11. *Real UI Mutation: Admin Sekolah Settings Persistence* (PASS).
  12. *Real UI Mutation: Guru Practicum Scheduling Cycle* (PASS).
  13. *Strict RBAC Boundary: Denied Actions & UI Absence* (PASS).
  14. *Superuser Data Master Wilayah (Provinsi, Kab/Kota, Kecamatan)* (PASS).
  15. *Superuser Master Sekolah CRUD Lifecycle & Deletion Guard* (PASS).
  16. *Master Routes Security & Role Isolation (Superuser-Only)* (PASS).
  17. *School Teacher & Student Master Management* (PASS).
  18. *Academic Master & Student Allocation* (PASS).
  19. *Negative Authorization on User Import & Mutations* (PASS).
  20. *Global Trainer Kit Serial Inventory Master (DEVICE-04-01)* (PASS).
  21. *Sale Records, Ownership Assignment & Activation (DEVICE-04-02)* (PASS).
  22. *Physical Handover (BAST) & 1-Year Warranty Commencement (DEVICE-04-03)* (PASS).
  23. *Warranty Correction & Audit Trail (DEVICE-04-03)* (PASS).
  24. *School Admin Warranty Visibility & Cross-School IDOR Protection (DEVICE-04-04)* (PASS).
  25. *School LMS License Lifecycle, Superuser Workstation & School Admin Visibility (LICENSE-05-01 & LICENSE-05-02)* (PASS).
  26. *License Expiration Enforcement, Notice & Restoration (LICENSE-05-03 & LICENSE-05-04)* (PASS).
- **Code Style & Formatting**: Laravel Pint passed (`./vendor/bin/pint --test`).
- **Frontend Assets**: Vite production build succeeded (98.38 kB CSS, 1.87 MB JS).
- **Baseline Immutability**: `upi-edge/` and `dump_upiedge.sql` preserved with zero modifications.

