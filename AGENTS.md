# Bitanic — AGENTS.md

Agent-neutral operational instructions for the Bitanic platform.  
Source of truth: `PRD.md`, `ARCHITECTURE.md`, `BACKLOG_REVISED.md` (canonical at `C:/Users/user/Nata/MAKERINDO/Bitanic/BACKLOG_REVISED.md`), this file.

---

## 1. Project Purpose & Architecture

**Bitanic** = Smart Precision Agriculture LMS & IoT Workstation for SMK Pertanian/ATPH 4.0.  
Core responsibilities:
- 12 Modul Praktikum Kurikulum Merdeka + LKS digital + Rubrik 4-skala
- Trainer Kit fleet (`BITANIC-TK-01`..`TK-06`): 7-in-1 sensor Modbus RS485 + 4 relai aktuator
- Monitoring, analitik, jadwal, rapor kompetensi, direktori, raw Modbus debugger
- Multi-school ready schema; MVP serves one active school

**Stack**: Laravel 12 (PHP 8.2+), PostgreSQL 17/18, Tailwind CSS v4, Alpine.js, ApexCharts, Vite 6.  
**Pattern**: 3-Tier Modular — Thin Controllers → Clean Actions/Services → PostgreSQL Models with composite indexes.

**Baseline assets (immutable)**: `upi-edge/` folder and `dump_upiedge.sql` — must remain pure, no destructive changes.

---

## 2. Technology Conventions

| Area | Convention |
|------|------------|
| **Controllers** | Thin — delegate to Actions/Services only |
| **Actions** | Single-responsibility, named by domain (`IngestTelemetryAction`, `CreateScheduleAction`, …) |
| **Services** | Domain logic (agronomy evaluator, availability resolver) |
| **Models** | Explicit `$fillable`, `$casts`, relationships; no mass-assignment surprises |
| **Migrations** | Composite indexes (`device_id, recorded_at DESC`), FKs, JSONB for flexible payloads |
| **Blade Views** | `resources/views/pages/<domain>/<action>.blade.php` — never in root `views/` |
| **Components** | Atomic, reusable under `resources/views/components/` |
| **Routes** | `routes/web.php` (auth, school-scoped), `routes/api.php` (device.key middleware) |
| **Tests** | Feature under `tests/Feature/`, Unit under `tests/Unit/`; SQLite for CI, PostgreSQL for tenancy/race |
| **Typography** | `--font-sans/--font-ui` = Inter; `--font-mono` = JetBrains Mono (reserved for real technical identifiers/measurements only); utility classes: `.text-page-title`, `.text-section-title`, `.text-body`, `.text-label`, `.text-nav`, `.text-supporting`, `.font-tabular` (tabular-nums) |
| **Colors** | Leaf green scale (`leaf-50`..`leaf-900`), slate surfaces; no decorative gradients on functional pages |
| **Auth** | Session-based email/password; remember-me; first-login password change flow; role middleware (`guru`, `siswa`, `superuser`/`admin`) |
| **RBAC** | Fail-closed school tenancy: `school_id` scoping on every query; cross-school IDOR denied at DB + policy layer |
| **Telemetry Provenance** | Every reading tagged `hardware | simulation | fixture`; only authenticated device ingress sets `hardware` and updates `last_seen_at` |
| **Heartbeat/Availability** | `DeviceAvailabilityResolver` — configurable per-device/school/global timeout (default 900s); simulation/fixture never forge heartbeat |
| **Hardware Safety** | No endpoint claims physical actuation without positive device ACK; relay/reboot/calibration remain simulation-only until firmware/transport/ACK verified |
| **Async AI** | Gemini via queue job (`GenerateCropRecommendationJob`); ticket poll pattern; no blocking HTTP |

---

## 3. Source-of-Truth Precedence

1. `PRD.md` — product scope, FR/NFR
2. `ARCHITECTURE.md` — technical design, DB schema, API contracts
3. `BACKLOG_REVISED.md` — task register with evidence-linked statuses
4. This `AGENTS.md` — operational rules
5. `CLAUDE.md` — legacy agent instructions (do not conflict)

---

## 4. Development Commands (Verified)

```bash
# From bitanic-app/
php artisan test                    # Full suite (SQLite in-memory)
php artisan test --filter=<class>   # Focused
php artisan test --env=pgsql_testing  # PostgreSQL isolated (bitanic_test)
./vendor/bin/pint --test            # Lint
npm run build                       # Production frontend build
php artisan view:clear              # Clear compiled views
```

**Test DB**: `DB_CONNECTION=sqlite` (default), `pgsql_testing` → isolated `bitanic_test` on PostgreSQL 17.6.  
Never touch `bitanic_db` (development) from tests.

---

## 5. Git & Commit Rules

- **Atomic micro-commits**: one logical change = one commit (Conventional: `feat|fix|refactor|style|chore(scope): message`)
- **No AI attribution**: no `Co-Authored-By`, no Claude footers in commit messages or PR descriptions
- **Clean history**: bisect-friendly, focused per-module
- **Push only when authorized**; no force-push, no history rewrite
- **Branch**: `main` is the integration branch; feature branches merged via fast-forward when clean

---

## 6. Frontend Conventions (Blade/Tailwind/Alpine)

- Shared shell: `layouts/app.blade.php` — sidebar (lg: visible, mobile: drawer), mobile top bar (hamburger + "BITANIC"), footer
- **No global topbar** — navigation lives in sidebar
- Login: `resources/views/auth/login.blade.php` — approved design, uses `Plus Jakarta Sans`, custom CSS vars, `bg-login.png` + `logo.png`/`logo-dark.png`
- Dashboard merged at `345eb73` — role-specific content, school selector superuser-only, honest empty states
- Sensor metrics: **always** `.font-tabular` (monospace + tabular-nums) — anti-layout-shift
- Empty/null states: explicit copy, no fake data, no fallback identities
- Provenance badges: Hardware (emerald), Simulasi (indigo), Fixture (slate)
- Responsive breakpoints: `lg` (1024px) sidebar visible; below — mobile drawer + hamburger

---

## 7. Authentication & RBAC

| Role | Scope | Key Routes |
|------|-------|------------|
| `superuser`/`admin` | All schools, settings, audit, API keys, imports | `/settings`, `/users-data`, `/trainer-kits` (mutations), `/raw-logs/inject` |
| `guru` | Own school, classes, schedules, grading, relay (sim), calibration | `/schedules` (CRUD), `/reports` (grade), `/modules` (grade), `/iot-monitoring` |
| `siswa` | Own school, own reports, allocated monitoring, LKS submit | `/modules` (submit), `/reports` (own), `/assessment` (self) |
| Guest | Public catalogue, monitoring (default school, restricted metadata), analytics, history, raw-logs | `/`, `/modules`, `/analytics`, `/telemetry/history`, `/iot-monitoring`, `/trainer-kits` (read), `/raw-logs` |

**Middleware**: `auth`, `role:...`, `device.key` (API), `EnsurePasswordIsChanged`, `AuthenticateDeviceApiKey`.

---

## 8. Fail-Closed School Tenancy & IDOR Protection

- Every model query scoped by `school_id` (explicit or via policy)
- Guest → default active school only; restricted metadata (no IP/MAC/API-key)
- Superuser → explicit `school_id` query param; no implicit global access
- Cross-school mutation attempts → 403/404, no state change
- Test evidence: `SchoolTenancyTest`, `RouteHealthAndSecurityMatrixTest`, `TrainerKitManagerTenancyAndSecurityTest`

---

## 9. Telemetry Provenance & Data Integrity

| Provenance | Source | Updates `last_seen_at` | Anomaly Evaluation |
|------------|--------|------------------------|-------------------|
| `hardware` | Authenticated device POST (`X-Device-Key`) | **Yes** | Yes (fresh only) |
| `simulation` | `SimulateSensorTelemetryCommand` / test seeders | **No** | No |
| `fixture` | Test fixtures, legacy import | **No** | No |

- **Never** fabricate readings; offline = em dashes (`—`) + "Belum Ada Transmisi Data"
- **Never** imply offline = broken; empty state = "Tidak ada telemetri segar untuk dievaluasi"
- Composite index: `(device_id, recorded_at DESC)` on `telemetry_readings`

---

## 10. No Fabricated Hardware Readings / Device ACK

- Relay toggle, reboot, calibration downlink → **simulation-only** until:
  1. Firmware repo + protocol audited (BR-400)
  2. Command lifecycle with ACK implemented (BR-401)
  3. Hardware-in-loop evidence recorded
- UI must show "simulation only" / pending state — never "success/active" without ACK

---

## 11. Data Preservation & Immutable Baseline

- `upi-edge/` and `dump_upiedge.sql` — **read-only reference**, never modified
- No `TRUNCATE` on telemetry tables
- Migrations additive → backfill → verify → constrain; old columns not dropped initially
- Legacy SaaS (subscription/Midtrans) isolated under LEGACY-01 — routes return 404, data preserved

---

## 12. Incremental, Page-by-Page UI Development

**PO Workflow** (non-negotiable):
```
ONE PAGE → IMPLEMENT → TEST → SCREENSHOT → PO REVIEW → APPROVAL → NEXT PAGE
```
- No simultaneous multi-page redesign
- Monitoring, Analytics, Modules, etc. — wait for explicit PO selection
- Screenshot at 390/768/1440/1920 (viewport + full page including tables)
- Visual approval is PO's call; merge ≠ approval

---

## 13. PO Visual Approval Before Proceeding

- Login page: **approved** (do not redesign)
- Dashboard: merged at `345eb73` — **awaiting PO visual review**
- All other pages: untouched, pending PO direction
- Reference design: Trello `https://trello.com/c/F8W2Egdh`

---

## 14. Verified Quality Gates (Current HEAD `345eb73`)

| Gate | Result |
|------|--------|
| PHPUnit (SQLite) | 370 tests / 1960 assertions / 5 skipped — PASS |
| PHPUnit (PostgreSQL `bitanic_test`) | 351 tests / 1913 assertions / 4 skipped — PASS |
| Pint (`--test`) | PASS |
| `npm run build` | 5.71s — PASS |
| Browser smoke (guest 16 routes) | HTTP 200, 0 console errors, 0 failed requests |
| Baseline assets | `git status --short -- upi-edge dump_upiedge.sql` — clean |

---

## 15. Immediate Handover State

- **Local HEAD**: `345eb73b4fd9b3ebe24e3eda659229eccd9a5991` (`main`)
- **Remote `origin/main`**: Same SHA
- **Working tree**: Clean
- **Outstanding branches**: `feature/br-602-dashboard-visual-revision` (merged), `work/non-ui-transition-2026-11-06` (merged)
- **No open PRs**
- **Screenshots**: Previously generated QA PNGs deleted (were untracked artifacts)
- **Next action**: Google Antigravity onboarding + PO-directed work (not automatic redesign)