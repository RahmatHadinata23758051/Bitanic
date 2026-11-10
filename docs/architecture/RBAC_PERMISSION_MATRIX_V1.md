# BITANIC — RBAC PERMISSION MATRIX V1.0
## Fine-Grained Role-Based Access Control & Tenant Authorization Contract

**Document Version:** 1.0.0  
**Status:** Architecture Contract (Sprint 2 / Pack A — Deliverable 1)  
**Task Reference:** `RBAC-02-01`  
**Target Environment:** Bitanic Vocational LMS & IoT Platform Baseline v1.0  
**Aligned Standards:** Architecture Baseline v1.0, PRD ATPH 4.0, OWASP ASVS v4.0.3 (Access Control)  

---

## 1. Executive Summary & Identity Archetypes

Bitanic operates on a **Fail-Closed Multi-Tenant Role-Based Access Control (RBAC)** model. The platform distinguishes between platform-level operators and school-level vocational actors. 

### The 5 System Roles

```
┌─────────────────────────────────────────────────────────────────────────────┐
│ PLATFORM LEVEL                                                              │
│ ┌─────────────────────────────────────────────────────────────────────────┐ │
│ │ 1. SUPERUSER (Platform Operator / Developer)                            │ │
│ │    • Global multi-school scope (can view all schools, filter any school) │ │
│ │    • Operates hardware provisioning, API key rotation, raw Modbus debug │ │
│ │    • Has NO implicit school assignment (school_id = null)               │ │
│ └─────────────────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────────────────┘
                                       │
┌──────────────────────────────────────▼──────────────────────────────────────┐
│ TENANT LEVEL (Bound strictly to single school_id)                          │
│ ┌─────────────────────────────────────────────────────────────────────────┐ │
│ │ 2. ADMIN SEKOLAH (School Administrator / Lab Manager)                   │ │
│ │    • Tenant-level administrator for their assigned school only          │ │
│ │    • Manages school settings, academic calendar, user import, classes   │ │
│ │    • Inherits teacher capabilities (grading, scheduling) within tenant  │ │
│ │    • STRICTLY DENIED: Cross-school data, platform-wide ops, API keys    │ │
│ └─────────────────────────────────────────────────────────────────────────┘ │
│ ┌─────────────────────────────────────────────────────────────────────────┐ │
│ │ 3. GURU (Vocational Teacher / Instructor)                               │ │
│ │    • Manages classes, student groups, practicum schedules, rubrics      │ │
│ │    • Grades LKS reports, reviews telemetry snapshots, manages media     │ │
│ │    • Performs simulated hardware reboot and calibration within tenant   │ │
│ │    • STRICTLY DENIED: School profile settings, audit logs, raw debug    │ │
│ └─────────────────────────────────────────────────────────────────────────┘ │
│ ┌─────────────────────────────────────────────────────────────────────────┐ │
│ │ 4. SISWA (Vocational Student - ATPH 4.0)                                │ │
│ │    • Enrolled in exactly one school, one classroom, and one group       │ │
│ │    • Accesses 12 practicum modules, submits LKS observations            │ │
│ │    • Views own reports and personal competency rapor                    │ │
│ │    • STRICTLY DENIED: Administrative, grading, and scheduling routes    │ │
│ └─────────────────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────────────────┘
                                       │
┌──────────────────────────────────────▼──────────────────────────────────────┐
│ PUBLIC / GUEST LEVEL                                                        │
│ ┌─────────────────────────────────────────────────────────────────────────┐ │
│ │ 5. GUEST (Unauthenticated Public / Auditor)                             │ │
│ │    • Public catalogue, read-only monitoring on default active school    │ │
│ │    • Sanitized metadata only (zero IP, MAC, API keys, or private PII)   │ │
│ │    • STRICTLY DENIED: Any mutation, private student data, rapor, auth   │ │
│ └─────────────────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## 2. Resource Ownership & Tenant Scoping Taxonomy

| Resource Ownership Classification | Definition | Tenant Scoping Rule |
| :--- | :--- | :--- |
| **Platform-Global** | Hardware inventory catalog, raw Modbus frame stream, device API key hashing, global school registry. | Unscoped; accessible exclusively by `superuser`. Legacy admin and tenant actors receive HTTP 403. |
| **Tenant-Owned (School)** | School settings, audit trail, classrooms, student groups, devices assigned to lab, activity schedules, planting media, module reports. | Scoped strictly by `school_id`. Superuser may filter via `?school_id=`; tenant actors are locked to their own `school_id`. Cross-school access strictly aborts HTTP 403 / 404 fail-closed. |
| **Classroom / Group-Owned** | Workstation allocations, group practicum schedule bookings. | Must belong to teacher's school and student's enrolled classroom. |
| **User-Owned (Personal)** | User profile, personal password change, student LKS submission attempt history, personal competency assessment. | Students may only view and mutate their own records. Teachers/Admins can review records within their school. Cross-student or cross-school tampering is forbidden. |
| **Public-Read (Sanitized)** | Default dashboard, public module catalog, live telemetry of default school. | Stripped of sensitive identifiers (hardware MAC, device IP, private user profiles). |

---

## 3. Comprehensive Route & Action Permission Matrix

The matrix below documents all 59 Laravel routes and domain actions across the application, detailing current implementation status, target architecture contract, resource ownership, tenant scope, and exact differences from Architecture Baseline v1.0.

### Legend
- **`ALLOW`**: Permitted operation within authorized tenant scope.
- **`OWN`**: Permitted strictly for self or within own school tenant.
- **`RO`**: Read-only access (sanitized public or student view).
- **`DENY`**: Explicitly blocked with HTTP 401 Unauthenticated, HTTP 403 Forbidden, or HTTP 404 Fail-Closed.
- **`SIM`**: Simulation mode only; physical actuator downlink requires verified hardware ACK.

---

### A. Authentication, Session & Account Lifecycle

| Route / Action | Method | Implemented Permission | Target Permission Baseline v1.0 | Resource Ownership | Tenant Scope | Superuser | Admin Sekolah | Guru | Siswa | Guest | Gap / Contract Difference |
| :--- | :--- | :--- | :--- | :--- | :--- | :---: | :---: | :---: | :---: | :---: | :--- |
| `/login` | `GET` | `guest` | `guest` | Public Portal | Unscoped | ALLOW | ALLOW | ALLOW | ALLOW | ALLOW | None. Auth portal. |
| `/login` | `POST` | `guest` + rate limit | `guest` + school active check | Auth Session | Validates tenant active | ALLOW | ALLOW | ALLOW | ALLOW | ALLOW | Superuser currently required to have `school_id != null` in `AuthController`. Target allows `school_id = null`. |
| `/logout` | `POST` | `auth` | `auth` | Auth Session | Session | ALLOW | ALLOW | ALLOW | ALLOW | DENY (401) | None. Invalides session. |
| `/password/change-initial` | `GET` | `auth` | `auth` + `must_change_password` | User Account | Self | OWN | OWN | OWN | OWN | DENY (401) | None. First login password change gate. |
| `/password/change-initial` | `POST` | `auth` | `auth` + strong policy | User Account | Self | OWN | OWN | OWN | OWN | DENY (401) | Enforces 12-char mixed-case password update. |

---

### B. Dashboard & Laboratory Workstation

| Route / Action | Method | Implemented Permission | Target Permission Baseline v1.0 | Resource Ownership | Tenant Scope | Superuser | Admin Sekolah | Guru | Siswa | Guest | Gap / Contract Difference |
| :--- | :--- | :--- | :--- | :--- | :--- | :---: | :---: | :---: | :---: | :---: | :--- |
| `/` (`dashboard`) | `GET` | Public / `auth` | Public (sanitized) / Role-aware | Tenant / Public | Scoped via `SchoolContextResolver` | ALLOW (Global / Filter) | ALLOW (Own School) | ALLOW (Own School) | ALLOW (Own School) | ALLOW (Sanitized Default) | Resolved in Sprint 1. School switcher strictly hidden for non-superusers. Tampering receives 403. |

---

### C. IoT Monitoring & Actuator Relays

| Route / Action | Method | Implemented Permission | Target Permission Baseline v1.0 | Resource Ownership | Tenant Scope | Superuser | Admin Sekolah | Guru | Siswa | Guest | Gap / Contract Difference |
| :--- | :--- | :--- | :--- | :--- | :--- | :---: | :---: | :---: | :---: | :---: | :--- |
| `/iot-monitoring`, `/iot` | `GET` | Public / `auth` | Public (sanitized) / Role-aware | Tenant / Public | Scoped via `SchoolContextResolver` | ALLOW (Global / Filter) | ALLOW (Own School) | ALLOW (Own School) | ALLOW (Own School) | ALLOW (Sanitized Default) | Sensitive device IP/MAC excluded from Guest/Siswa views. |
| `/api/v1/devices/relay/toggle` | `POST` | `auth`, `role:guru` | `auth`, `role:guru,admin_sekolah,superuser` | Tenant Device | Own school device | ALLOW (SIM) | ALLOW (SIM) | ALLOW (SIM) | DENY (403) | DENY (401) | Remains simulation-only pending physical hardware ACK (G-04). |

---

### D. Agronomy Analytics & Dynamic Trends

| Route / Action | Method | Implemented Permission | Target Permission Baseline v1.0 | Resource Ownership | Tenant Scope | Superuser | Admin Sekolah | Guru | Siswa | Guest | Gap / Contract Difference |
| :--- | :--- | :--- | :--- | :--- | :--- | :---: | :---: | :---: | :---: | :---: | :--- |
| `/analytics` | `GET` | Public / `auth` | Public (sanitized) / Role-aware | Tenant Analytics | Scoped via `SchoolContextResolver` | ALLOW (Global / Filter) | ALLOW (Own School) | ALLOW (Own School) | ALLOW (Own School) | ALLOW (Sanitized Default) | Cross-school IDOR rejected fail-closed. |
| `/telemetry/charts` | `ANY` | Redirect to `/analytics` | Redirect | System | System | ALLOW | ALLOW | ALLOW | ALLOW | ALLOW | Permanent canonical redirect. |

---

### E. Telemetry History & Data Export

| Route / Action | Method | Implemented Permission | Target Permission Baseline v1.0 | Resource Ownership | Tenant Scope | Superuser | Admin Sekolah | Guru | Siswa | Guest | Gap / Contract Difference |
| :--- | :--- | :--- | :--- | :--- | :--- | :---: | :---: | :---: | :---: | :---: | :--- |
| `/telemetry/history` | `GET` | Public / `auth` | Public (sanitized) / Role-aware | Tenant Telemetry | Scoped via `SchoolContextResolver` | ALLOW (Global / Filter) | ALLOW (Own School) | ALLOW (Own School) | ALLOW (Own School) | ALLOW (Sanitized Default) | Shows honest provenance badges (`hardware`, `simulation`, `fixture`). |
| `/telemetry/history/export` | `GET` | Public / `auth` | Public / `auth` | Tenant Telemetry | Scoped via `SchoolContextResolver` | ALLOW (Global / Filter) | ALLOW (Own School) | ALLOW (Own School) | ALLOW (Own School) | ALLOW (Sanitized Default) | Memory-safe streaming CSV. Foreign school param aborts 403. |

---

### F. Curriculum & Practicum Modules (ATPH 4.0)

| Route / Action | Method | Implemented Permission | Target Permission Baseline v1.0 | Resource Ownership | Tenant Scope | Superuser | Admin Sekolah | Guru | Siswa | Guest | Gap / Contract Difference |
| :--- | :--- | :--- | :--- | :--- | :--- | :---: | :---: | :---: | :---: | :---: | :--- |
| `/modules` | `GET` | Public / `auth` | Public / `auth` | National Curriculum | Global Reference | ALLOW | ALLOW | ALLOW | ALLOW | ALLOW | 12 core ATPH 4.0 modules. |
| `/modules/{id}` | `GET` | Public / `auth` | Public / `auth` | National Curriculum | Global Reference | ALLOW | ALLOW | ALLOW | ALLOW | ALLOW | Detailed SOP, rubrics, and LKS sheet. |
| `/modules/{id}/submit-lks` | `POST` | `auth` | `auth`, `role:siswa` | Student LKS Attempt | Own student attempt | DENY (403) | DENY (403) | DENY (403) | OWN | DENY (401) | Creates append-only attempt; locked once teacher grades report. |
| `/modules/reports/{id}/grade` | `POST` | `auth`, `role:guru,superuser` | `auth`, `role:guru,admin_sekolah,superuser` | Student Report | Own school student report | ALLOW (Own School) | ALLOW (Own School) | ALLOW (Own School) | DENY (403) | DENY (401) | 4-scale rubric grading (1-4). Cross-school grading rejected 403. |
| `/modules/reports/{id}/request-revision` | `POST` | `auth`, `role:guru,superuser` | `auth`, `role:guru,admin_sekolah,superuser` | Student Report | Own school student report | ALLOW (Own School) | ALLOW (Own School) | ALLOW (Own School) | DENY (403) | DENY (401) | Unlocks report for revision attempt with audit note. |

---

### G. Practicum Scheduling & Timeline

| Route / Action | Method | Implemented Permission | Target Permission Baseline v1.0 | Resource Ownership | Tenant Scope | Superuser | Admin Sekolah | Guru | Siswa | Guest | Gap / Contract Difference |
| :--- | :--- | :--- | :--- | :--- | :--- | :---: | :---: | :---: | :---: | :---: | :--- |
| `/schedules` | `GET` | Public / `auth` | Public (sanitized) / Role-aware | Tenant Schedule | Scoped via `SchoolContextResolver` | ALLOW (Global / Filter) | ALLOW (Own School) | ALLOW (Own School) | ALLOW (Own School) | ALLOW (Sanitized Default) | Shows timeline, kit allocations, and teacher schedules. |
| `/schedules` | `POST` | `auth`, `role:guru` | `auth`, `role:guru,admin_sekolah,superuser` | Tenant Schedule | Own school schedule | ALLOW (Own School) | ALLOW (Own School) | ALLOW (Own School) | DENY (403) | DENY (401) | Pessimistic locking prevents kit booking collisions. |
| `/schedules/{id}` | `DELETE` | `auth`, `role:guru` | `auth`, `role:guru,admin_sekolah,superuser` | Tenant Schedule | Own school schedule | ALLOW (Own School) | ALLOW (Own School) | ALLOW (Own School) | DENY (403) | DENY (401) | Deletes scheduled booking. Cross-school schedule delete returns 403/404. |

---

### H. Practicum Reports Hub & Grading Review

| Route / Action | Method | Implemented Permission | Target Permission Baseline v1.0 | Resource Ownership | Tenant Scope | Superuser | Admin Sekolah | Guru | Siswa | Guest | Gap / Contract Difference |
| :--- | :--- | :--- | :--- | :--- | :--- | :---: | :---: | :---: | :---: | :---: | :--- |
| `/reports` | `GET` | `auth` | `auth` | Tenant / Personal | Scoped by role | ALLOW (Global / Filter) | ALLOW (Own School) | ALLOW (Own School) | OWN (Self Reports) | DENY (401) | Siswa only sees own LKS reports. Teachers see own school reports. |

---

### I. Competency Assessment (Rapor Capaian Siswa)

| Route / Action | Method | Implemented Permission | Target Permission Baseline v1.0 | Resource Ownership | Tenant Scope | Superuser | Admin Sekolah | Guru | Siswa | Guest | Gap / Contract Difference |
| :--- | :--- | :--- | :--- | :--- | :--- | :---: | :---: | :---: | :---: | :---: | :--- |
| `/assessment` | `GET` | `auth` | `auth` | Tenant / Personal | Scoped by role | ALLOW (Global / Filter) | ALLOW (Own School) | ALLOW (Own School) | OWN (Self Rapor) | DENY (401) | Siswa views self. Teachers view school gradebook. Honest missing grade `—` state. |
| `/assessment/export` | `GET` | `auth` | `auth`, `role:guru,admin_sekolah,superuser` | Tenant Academic Data | Scoped to own school | ALLOW (Global / Filter) | ALLOW (Own School) | ALLOW (Own School) | DENY (403) | DENY (401) | Exports gradebook to CSV. Cross-school export strictly denied with 403. |

---

### J. Academic User Directory

| Route / Action | Method | Implemented Permission | Target Permission Baseline v1.0 | Resource Ownership | Tenant Scope | Superuser | Admin Sekolah | Guru | Siswa | Guest | Gap / Contract Difference |
| :--- | :--- | :--- | :--- | :--- | :--- | :---: | :---: | :---: | :---: | :---: | :--- |
| `/users-data` | `GET` | `auth`, `role:guru,superuser` | `auth`, `role:guru,admin_sekolah,superuser` | Tenant Academic Data | Scoped via `SchoolContextResolver` | ALLOW (Global / Filter) | ALLOW (Own School) | ALLOW (Own School) | DENY (403) | DENY (401) | Directory search isolated to own school; foreign search returns 0 leaks. |

---

### K. Classroom & Student Group Management

| Route / Action | Method | Implemented Permission | Target Permission Baseline v1.0 | Resource Ownership | Tenant Scope | Superuser | Admin Sekolah | Guru | Siswa | Guest | Gap / Contract Difference |
| :--- | :--- | :--- | :--- | :--- | :--- | :---: | :---: | :---: | :---: | :---: | :--- |
| `/classes` | `GET` | `auth`, `role:guru` | `auth`, `role:guru,admin_sekolah,superuser` | Tenant Academic | Own school classes | ALLOW | ALLOW | ALLOW | DENY (403) | DENY (401) | Classroom and group roster overview. |
| `/classes` | `POST` | `auth`, `role:guru` | `auth`, `role:guru,admin_sekolah,superuser` | Tenant Academic | Own school classes | ALLOW | ALLOW | ALLOW | DENY (403) | DENY (401) | Create new classroom. |
| `/classes/{classroom}` | `PUT` | `auth`, `role:guru` | `auth`, `role:guru,admin_sekolah,superuser` | Tenant Academic | Own school classes | ALLOW | ALLOW | ALLOW | DENY (403) | DENY (401) | Update class name or homeroom teacher. Cross-school 403. |
| `/classes/{classroom}` | `DELETE` | `auth`, `role:guru` | `auth`, `role:guru,admin_sekolah,superuser` | Tenant Academic | Own school classes | ALLOW | ALLOW | ALLOW | DENY (403) | DENY (401) | Delete empty class. Cross-school 403. |
| `/classes/{classroom}/groups` | `POST` | `auth`, `role:guru` | `auth`, `role:guru,admin_sekolah,superuser` | Tenant Academic | Own school groups | ALLOW | ALLOW | ALLOW | DENY (403) | DENY (401) | Create student workstation group. |
| `/groups/{group}` | `PUT` | `auth`, `role:guru` | `auth`, `role:guru,admin_sekolah,superuser` | Tenant Academic | Own school groups | ALLOW | ALLOW | ALLOW | DENY (403) | DENY (401) | Update group name/kit assignment. |
| `/groups/{group}` | `DELETE` | `auth`, `role:guru` | `auth`, `role:guru,admin_sekolah,superuser` | Tenant Academic | Own school groups | ALLOW | ALLOW | ALLOW | DENY (403) | DENY (401) | Delete student group. |
| `/students/{student}/allocation`| `POST` | `auth`, `role:guru` | `auth`, `role:guru,admin_sekolah,superuser` | Tenant Academic | Own school students | ALLOW | ALLOW | ALLOW | DENY (403) | DENY (401) | Assign student to class, group, and kit. |

---

### L. User Bulk Import (CSV / XLSX)

| Route / Action | Method | Implemented Permission | Target Permission Baseline v1.0 | Resource Ownership | Tenant Scope | Superuser | Admin Sekolah | Guru | Siswa | Guest | Gap / Contract Difference |
| :--- | :--- | :--- | :--- | :--- | :--- | :---: | :---: | :---: | :---: | :---: | :--- |
| `/users/import/preview` | `POST` | `auth`, `role:guru` | `auth`, `role:admin_sekolah,superuser` | Tenant Accounts | Own school accounts | ALLOW | ALLOW | DENY (Target) | DENY (403) | DENY (401) | Target restricts bulk account import to Admin Sekolah and Superuser. Currently accessible by Guru. |
| `/users/import/execute` | `POST` | `auth`, `role:guru` | `auth`, `role:admin_sekolah,superuser` | Tenant Accounts | Own school accounts | ALLOW | ALLOW | DENY (Target) | DENY (403) | DENY (401) | Generates random passwords, sets `must_change_password=true`, logs audit trail. |

---

### M. Hardware Fleet & Trainer Kit Management

| Route / Action | Method | Implemented Permission | Target Permission Baseline v1.0 | Resource Ownership | Tenant Scope | Superuser | Admin Sekolah | Guru | Siswa | Guest | Gap / Contract Difference |
| :--- | :--- | :--- | :--- | :--- | :--- | :---: | :---: | :---: | :---: | :---: | :--- |
| `/trainer-kits` | `GET` | Public / `auth` | Public (sanitized) / Role-aware | Tenant Hardware | Scoped via `SchoolContextResolver` | ALLOW (Global / Filter) | ALLOW (Own School) | ALLOW (Own School) | ALLOW (Sanitized) | ALLOW (Sanitized Default) | Shows hardware fleet table. Sensitive columns hidden for Guest/Siswa. |
| `/trainer-kits/{device}/reboot` | `POST` | `auth`, `role:guru` | `auth`, `role:guru,admin_sekolah,superuser` | Tenant Device | Own school device | ALLOW (SIM) | ALLOW (SIM) | ALLOW (SIM) | DENY (403) | DENY (401) | Simulated soft reboot. Cross-school reboot rejected 403. |
| `/trainer-kits/{device}/calibration`| `POST` | `auth`, `role:guru` | `auth`, `role:guru,admin_sekolah,superuser` | Tenant Device | Own school device | ALLOW (SIM) | ALLOW (SIM) | ALLOW (SIM) | DENY (403) | DENY (401) | Multi-parameter offsets with atomic rollback. Cross-school 403. |
| `/trainer-kits/{device}/api-key/rotate`| `POST`| `auth`, `role:superuser` | `auth`, `role:superuser` | Platform Gateway Auth | Platform Scope | ALLOW | DENY (403) | DENY (403) | DENY (403) | DENY (401) | **STRICT PLATFORM OPERATION**. Admin Sekolah cannot rotate device API keys. |

---

### N. Raw Modbus RS485 Hex Stream Debugger

| Route / Action | Method | Implemented Permission | Target Permission Baseline v1.0 | Resource Ownership | Tenant Scope | Superuser | Admin Sekolah | Guru | Siswa | Guest | Gap / Contract Difference |
| :--- | :--- | :--- | :--- | :--- | :--- | :---: | :---: | :---: | :---: | :---: | :--- |
| `/raw-logs`, `/rawlogs` | `GET` | Public / `auth` | Public (sanitized) / Superuser | Lab Gateway Logs | Scoped to default / Superuser | ALLOW | DENY (Target) | DENY (Target) | DENY (Target) | ALLOW (Legacy Demo) | Target restricts raw hex debugging to platform Superuser and certified lab technician. |
| `/raw-logs/inject` | `POST` | `auth`, `role:superuser` | `auth`, `role:superuser` | Lab Gateway Serial Bus | Platform Scope | ALLOW | DENY (403) | DENY (403) | DENY (403) | DENY (401) | **STRICT PLATFORM OPERATION**. Injects synthetic Modbus RTU frames. |

---

### O. School Settings & Audit Trail

| Route / Action | Method | Implemented Permission | Target Permission Baseline v1.0 | Resource Ownership | Tenant Scope | Superuser | Admin Sekolah | Guru | Siswa | Guest | Gap / Contract Difference |
| :--- | :--- | :--- | :--- | :--- | :--- | :---: | :---: | :---: | :---: | :---: | :--- |
| `/settings` | `GET` | `auth`, `role:superuser,admin`| `auth`, `role:superuser,admin_sekolah`| Tenant Settings | Own school settings | ALLOW (Filter) | ALLOW (Own School) | DENY (403) | DENY (403) | DENY (401) | Admin Sekolah views own school profile and immutable append-only audit trail. |
| `/settings/school` | `POST` | `auth`, `role:superuser,admin`| `auth`, `role:superuser,admin_sekolah`| Tenant Settings | Own school settings | ALLOW (Filter) | ALLOW (Own School) | DENY (403) | DENY (403) | DENY (401) | Updates academic year, semester, lead teacher, and alarm settings. |

---

### P. Planting Media Management (Media Tanam)

| Route / Action | Method | Implemented Permission | Target Permission Baseline v1.0 | Resource Ownership | Tenant Scope | Superuser | Admin Sekolah | Guru | Siswa | Guest | Gap / Contract Difference |
| :--- | :--- | :--- | :--- | :--- | :--- | :---: | :---: | :---: | :---: | :---: | :--- |
| `/media` | `GET` | `auth`, `role:guru,superuser` | `auth`, `role:guru,admin_sekolah,superuser` | Tenant Agronomy | Scoped to own school | ALLOW | ALLOW | ALLOW | DENY (403) | DENY (401) | List planting media formulations. |
| `/media/create` | `GET` | `auth`, `role:guru,superuser` | `auth`, `role:guru,admin_sekolah,superuser` | Tenant Agronomy | Own school | ALLOW | ALLOW | ALLOW | DENY (403) | DENY (401) | Form for new substrate formulation. |
| `/media` | `POST` | `auth`, `role:guru,superuser` | `auth`, `role:guru,admin_sekolah,superuser` | Tenant Agronomy | Own school | ALLOW | ALLOW | ALLOW | DENY (403) | DENY (401) | Store new media with automatic agronomic evaluation. |
| `/media/{medium}/edit` | `GET` | `auth`, `role:guru,superuser` | `auth`, `role:guru,admin_sekolah,superuser` | Tenant Agronomy | Own school | ALLOW | ALLOW | ALLOW | DENY (403) | DENY (401) | Edit media formulation. Cross-school 403. |
| `/media/{medium}` | `PUT` | `auth`, `role:guru,superuser` | `auth`, `role:guru,admin_sekolah,superuser` | Tenant Agronomy | Own school | ALLOW | ALLOW | ALLOW | DENY (403) | DENY (401) | Update formulation and re-evaluate thresholds. |
| `/media/{medium}` | `DELETE` | `auth`, `role:guru,superuser` | `auth`, `role:guru,admin_sekolah,superuser` | Tenant Agronomy | Own school | ALLOW | ALLOW | ALLOW | DENY (403) | DENY (401) | Delete media formulation. Cross-school 403. |

---

### Q. AI Agronomic Advisor (Asynchronous Gemini Queue)

| Route / Action | Method | Implemented Permission | Target Permission Baseline v1.0 | Resource Ownership | Tenant Scope | Superuser | Admin Sekolah | Guru | Siswa | Guest | Gap / Contract Difference |
| :--- | :--- | :--- | :--- | :--- | :--- | :---: | :---: | :---: | :---: | :---: | :--- |
| `/api/v1/recommendations/request` | `POST` | `auth` | `auth`, `role:guru,admin_sekolah,superuser` | Tenant AI Service | Own school telemetry | ALLOW | ALLOW | ALLOW | DENY (Target) | DENY (401) | Asynchronous job dispatch. Target restricts AI compute quota to educators/admins. |
| `/api/v1/recommendations/status/{ticketId}` | `GET` | `auth` | `auth`, `role:guru,admin_sekolah,superuser` | Tenant AI Service | Own school ticket | ALLOW | ALLOW | ALLOW | DENY (Target) | DENY (401) | Polls ticket status from cache. Foreign ticket returns 403. |

---

### R. IoT Edge Telemetry Ingestion (Hardware Ingress)

| Route / Action | Method | Implemented Permission | Target Permission Baseline v1.0 | Resource Ownership | Tenant Scope | Superuser | Admin Sekolah | Guru | Siswa | Guest | Gap / Contract Difference |
| :--- | :--- | :--- | :--- | :--- | :--- | :---: | :---: | :---: | :---: | :---: | :--- |
| `/api/v1/telemetry/ingest`, `/api/rsc-data` | `POST` | `device.key` middleware | `device.key` middleware | Hardware Device | Bound to device's `school_id` | N/A (Device) | N/A (Device) | N/A (Device) | N/A (Device) | N/A (Device) | Ingress authenticated via SHA-256 key hash. Updates `last_seen_at` and sets `provenance='hardware'`. |

---

### S. Legacy Payment & Webhooks (In-Flight Reconciliation)

| Route / Action | Method | Implemented Permission | Target Permission Baseline v1.0 | Resource Ownership | Tenant Scope | Superuser | Admin Sekolah | Guru | Siswa | Guest | Gap / Contract Difference |
| :--- | :--- | :--- | :--- | :--- | :--- | :---: | :---: | :---: | :---: | :---: | :--- |
| `/api/v1/payments/webhook`, `/api/payment/callback` | `POST` | Public Webhook | Signature verified webhook | Historic Financial | External Gateway | ALLOW (Gateway) | ALLOW (Gateway) | ALLOW (Gateway) | ALLOW (Gateway) | ALLOW (Gateway) | Reconciles in-flight legacy transactions. New checkouts disabled under `LEGACY-01`. |

---

## 4. Key Security Invariants Enforced

1. **Strict Separation of Platform vs. Tenant Administration**:
   - `Admin Sekolah` cannot rotate device API keys, inject raw Modbus frames, or inspect global telemetry.
   - `Superuser` can observe any school via explicit filter `?school_id=`, but mutating devices defaults to their assigned school or requires positive multi-school privilege.
2. **Fail-Closed Cross-School Protection**:
   - Any attempt by `admin`, `guru`, or `siswa` to append or tamper with `?school_id=<other_school>` immediately aborts with `HTTP 403 Forbidden`.
   - Hostile non-numeric (`?school_id=abc`) or array tampering (`?school_id[]=1`) immediately aborts with `HTTP 403 Forbidden` for tenant actors, and `HTTP 404 Not Found` for superusers.
3. **Student Privacy (Dapodik / PII Protection)**:
   - Students cannot view the user directory, teacher schedules, school audit trails, or other students' gradebooks.
   - In `/reports` and `/assessment`, students are strictly limited to their own personal submissions and grades.
4. **Hardware Safety Guardrail**:
   - Relay toggling, reboots, and sensor calibration downlinks remain simulation-only. No UI or endpoint shall report a physical hardware success until the lab gateway bidirectional ACK protocol is verified.
