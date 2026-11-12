# BITANIC — Staging & Production Deployment Runbook

**Version**: 1.0 (Release Readiness 01)  
**Applicability**: Production, Staging, and Multi-Tenant Deployment Environments  
**Target Roles**: DevOps, System Administrators, Security Engineers, Superusers  
**Canonical Architecture Reference**: `docs/architecture/BITANIC_ARCHITECTURE_BASELINE_V1.md`

---

## 1. Executive Summary & Deployment Architecture

The Bitanic LMS Platform & IoT Workstation operates as a multi-school ready Laravel 12 monolith backed by PostgreSQL 17/18, Tailwind CSS v4, Alpine.js, and Redis/database queue drivers.

### Core Release Gates (Safe Rollout by Default)
1. **License Enforcement Gate**:
   - `LICENSE_ENFORCEMENT_ENABLED=false` (in `.env` / `config/license.php`).
   - Ensures zero disruption to operational vocational schools upon initial onboarding or staging verification.
2. **Automated Reminder Gate**:
   - `LICENSE_REMINDERS_ENABLED=false` (in `.env` / `config/license.php`).
   - Prevents uncoordinated email dispatches until SMTP credentials and official institutional support contacts are validated.
3. **Safe Contact Fallback**:
   - Default contact credentials in `config/license.php` default to `null` instead of hardcoded placeholder addresses (`admin@bitanic.id`). When contacts are empty, the platform renders safe generic institutional guidance ("Hubungi administrator sistem atau dinas pendidikan terkait").
4. **Timezone Determinism**:
   - Application base timezone remains `UTC` (`config/app.php`).
   - Daily license expiration reminder scheduler explicitly pins `Asia/Jakarta` (WIB) timezone:
     ```php
     Schedule::command('bitanic:check-license-reminders')->dailyAt('07:00')->timezone('Asia/Jakarta');
     ```
     07:00 WIB translates deterministically to 00:00 UTC on the underlying server clock.
5. **Fail-Closed Tenancy & Security**:
   - Authenticated non-Superusers lacking a valid `school_id` or belonging to an inactive school fail closed (HTTP 403 / redirect to login).
   - Superuser retains unrestricted platform oversight.
   - Device telemetry API ingress (`/api/v1/telemetry/ingest`, `/rsc-data`) is strictly isolated and immune to license middleware blocks.

---

## 2. Environment Configuration (`.env`)

Add and verify the following environment settings for production and staging:

```ini
# --- Application Baseline ---
APP_NAME=Bitanic
APP_ENV=production
APP_KEY=base64:...
APP_DEBUG=false
APP_URL=https://app.bitanic.id
APP_TIMEZONE=UTC

# --- Database (PostgreSQL 17+) ---
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=bitanic_production
DB_USERNAME=bitanic_user
DB_PASSWORD="<STRONG_RANDOM_PASSWORD>"

# --- Session & Cache ---
SESSION_DRIVER=database
SESSION_LIFETIME=120
CACHE_STORE=database
QUEUE_CONNECTION=database

# --- School LMS Licensing & Enforcement Gates ---
# Keep false during initial deployment; enable only after operational sign-off
LICENSE_ENFORCEMENT_ENABLED=false

# Keep false until SMTP setup and recipient addresses are validated
LICENSE_REMINDERS_ENABLED=false
LICENSE_REMINDER_DAYS=14

# Official Institutional Support Contacts (leave null/empty to use safe generic notice)
LICENSE_SUPPORT_EMAIL="support@bitanic.id"
LICENSE_SUPPORT_PHONE="+62 811-xxxx-xxxx"
LICENSE_SUPPORT_URL="https://bitanic.id/bantuan"

# --- SMTP / Mailgun Delivery ---
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailgun.org
MAIL_PORT=587
MAIL_USERNAME="postmaster@mg.bitanic.id"
MAIL_PASSWORD="<MAILGUN_SMTP_PASSWORD>"
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="no-reply@bitanic.id"
MAIL_FROM_NAME="Bitanic Precision Agriculture"
```

---

## 3. Database Migration, Backup & Rollback Procedures

### 3.1 Pre-Deployment Database Backup
Always capture a physical or logical dump before running migrations:

```bash
# Logical PostgreSQL backup with timestamp
pg_dump -U bitanic_user -h 127.0.0.1 -d bitanic_production -Fc -f "/var/backups/bitanic_pre_deploy_$(date +%Y%m%d_%H%M%S).dump"
```

### 3.2 Running Additive Migrations
Execute pending migrations in atomic mode:

```bash
php artisan migrate --force
```

*Note: All Bitanic migrations are strictly additive with composite index definitions and foreign keys. Reference assets (`upi-edge/` and `dump_upiedge.sql`) are immutable.*

### 3.3 Emergency Rollback Procedure
If a migration failure occurs:

```bash
# 1. Inspect migration status
php artisan migrate:status

# 2. Rollback the last migration batch if needed
php artisan migrate:rollback --step=1 --force

# 3. If restoring from logical dump:
# Drop and recreate schema / restore dump:
pg_restore -U bitanic_user -h 127.0.0.1 -d bitanic_production --clean "/var/backups/bitanic_pre_deploy_<timestamp>.dump"
```

---

## 4. Cron Scheduler Configuration

Ensure the Laravel scheduler is registered in the production host crontab (`crontab -e` for user `www-data`):

```bash
* * * * * cd /var/www/bitanic-app && php artisan schedule:run >> /dev/null 2>&1
```

### Scheduled Task Verification
To verify that scheduled tasks and timezone calculations are registered correctly:

```bash
php artisan schedule:list
```

Expected output:
```
00:00 UTC (07:00 Asia/Jakarta) ... php artisan bitanic:check-license-reminders
```

---

## 5. Queue Worker & Supervisor Daemon Configuration

Bitanic uses database or Redis queues for asynchronous email delivery (`SendSchoolLicenseReminderJob`) and Gemini crop recommendations (`GenerateCropRecommendationJob`).

### 5.1 Worker Concurrency & Safety Principles
- **Atomic Mutex Claiming & 5-Minute Lease**: `SendSchoolLicenseReminderJob` locks notification rows with `lockForUpdate()`, sets status to `processing`, records `claimed_at`, and increments `attempts`. Active leases (`claimed_at > now()->subMinutes(5)`) are respected by concurrent workers and scheduler sweeps.
- **Orphaned Job Recovery**: If a worker daemon terminates abruptly, leaving a notification in `processing` status beyond 5 minutes, `DispatchSchoolLicenseRemindersAction` and `SendSchoolLicenseReminderJob` automatically reclaim the job if `attempts < 3`. If max attempts (3) are reached, the orphaned notification transitions to `status = unknown`, preventing infinite loops.
- **Indeterminate SMTP Delivery Protection (`status = unknown`)**: When network timeouts, broken pipes, or connection resets occur during email transmission, the exact delivery outcome is indeterminate. To strictly prevent duplicate email spam to school administrators, notifications reaching max attempts under uncertain transport conditions transition to `status = unknown` rather than blind retries.
- **Stale Job Protection**: If a job is delayed in queue and `days_remaining < 13`, it is automatically skipped (`status = skipped`) to avoid sending obsolete expiration information.
- **Retry Policy**: Maximum 3 attempts (`--tries=3`). Deterministic 5xx SMTP rejections record durable error messages in `school_license_notifications.error_message` with `status = failed`.

### 5.2 Systemd / Supervisor Configuration
Create `/etc/supervisor/conf.d/bitanic-worker.conf`:

```ini
[program:bitanic-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/bitanic-app/artisan queue:work database --sleep=3 --tries=3 --max-time=3600 --timeout=60
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/bitanic/worker.log
stopwaitsecs=3600
```

Apply supervisor settings:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start bitanic-worker:*
```

---

## 6. Staging / Production Smoke & Verification Checklist

Execute this verification script prior to routing user traffic:

1. **Optimize Cache & Assets**:
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   npm run build
   ```

2. **Verify License Reminder Dry-Run**:
   ```bash
   php artisan bitanic:check-license-reminders --dry-run
   ```
   *Verify console output reflects upcoming candidate schools without database mutations.*

3. **Verify Health & Security Status**:
   - Device API key telemetry ingress (`POST /api/v1/telemetry/ingest` with `X-Device-Key`) returns HTTP 201.
   - Superuser can access `/master/licenses` and `/master/schools`.
   - School Admin can view `/school/license` in read-only mode.
   - Inactive or non-school accounts fail closed with HTTP 403 or redirect.

4. **Rollout Activation Sequence**:
   - Step A: Validate email templates and recipients with `php artisan bitanic:check-license-reminders --dry-run`.
   - Step B: Update `.env` to `LICENSE_REMINDERS_ENABLED=true` and restart queue workers (`php artisan queue:restart`).
   - Step C: Once all schools possess valid first-year licenses or active terms, enable enforcement: `LICENSE_ENFORCEMENT_ENABLED=true`.

---

## 7. Production Seeding & Authentication Security Protocol (`SEC-AUTH-SEED-01`)

### 7.1 Strict Credential Authentication
- **Zero Alternate Password Matching**: `AuthController::login()` strictly uses standard Laravel `Auth::attempt()`. All developer fallback passwords (`password123`, `Password123!`, `password`) and silent hash updates are eradicated.
- **Credential Immutability**: Successful logins never modify or re-hash stored user credentials without user initiation.
- **Uncompromised Security Gates**: Rate limiting (5 attempts/min per IP + email), initial password change requirements (`must_change_password`), and tenant status verification remain fail-closed.

### 7.2 Production Seeder Isolation
Bitanic strictly distinguishes authoritative institutional reference data from ephemeral development fixtures:

| Seeder Class | Type | Allowed Environments | Production Behavior |
| :--- | :--- | :--- | :--- |
| `IndonesianRegionsSeeder` | Reference Master | All (`production`, `staging`, `local`, `testing`) | Authoritative Kemendagri hierarchy sync |
| `SubscriptionPlanSeeder` | Reference Master | All (`production`, `staging`, `local`, `testing`) | Authoritative subscription tier catalog |
| `ModulePracticumSeeder` | Reference Master | All (`production`, `staging`, `local`, `testing`) | Official 12 ATPH Kurikulum Merdeka modules |
| `VocationalLmsSeeder` | Demo Fixture | `local`, `testing`, `browser_e2e` ONLY | **PROHIBITED**: Throws `\RuntimeException` in prod/staging |
| `TrainerKitSeeder` | Demo Fixture | `local`, `testing`, `browser_e2e` ONLY | **PROHIBITED**: Throws `\RuntimeException` in prod/staging |
| `DatabaseSeeder` | Orchestrator | All (`production`, `staging`, `local`, `testing`) | Executes reference seeders, detects production, warns and exits before demo fixtures |

> [!CAUTION]
> Demo accounts (`admin@smk.id`, `guru@smk.id`, `siswa@smk.id`), simulated devices (`TK-01`), and test telemetry must NEVER be seeded in staging or production. Operational databases must never be truncated or wiped.


