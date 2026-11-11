# BITANIC — School LMS License Enforcement & Email Reminder Runbook

**Version**: 1.0 (Sprint 5 Pack B)  
**Applicability**: Production, Staging, and Testing Environments  
**Target Roles**: DevOps, System Administrators, Platform Superusers, Support Engineers  

---

## 1. Overview & Architectural Policy

The Bitanic LMS Platform operates on an institutional licensing model:
1. **One School = One License Contract**: Each registered vocational school possesses exactly one canonical LMS license (`school_licenses`), granting access to the 12 Modul Praktikum Kurikulum Merdeka ATPH 4.0 and workstation tooling.
2. **Safe Rollout Gate**: License expiration enforcement is controlled via a centralized feature flag (`config('license.enforcement_enabled')`, env variable `LICENSE_ENFORCEMENT_ENABLED`), **defaulting to `false`** so that operational schools are not disrupted during platform onboarding.
3. **Fail-Closed Tenancy & Security**: Superuser platform access is never locked out. Tenant school administrators (`admin_sekolah`, legacy `admin`) retain access to view license status and school settings but are prevented from modifying LMS academic data when expired. Regular teachers (`guru`) and students (`siswa`) are politely redirected to an informative suspension screen.
4. **Data Preservation Guarantee**: Zero data deletion. Telemetry from IoT hardware (`routes/api.php`) continues unblocked. Student grades, practicum observations, and school configuration remain 100% intact.

---

## 2. Environment Configuration

Add the following environment variables to your `.env` configuration:

```ini
# Enable or disable LMS license enforcement (defaults to false for safe deployment)
LICENSE_ENFORCEMENT_ENABLED=false

# Enable or disable automated H-14 reminder email dispatches (defaults to false for safe deployment)
LICENSE_REMINDERS_ENABLED=false

# Threshold in days for automated email expiration reminders (default: 14)
LICENSE_REMINDER_DAYS=14

# Official support contact details displayed on suspension screens & notification emails
# (Defaults to null; when null, safe generic institutional instructions are rendered)
LICENSE_SUPPORT_EMAIL="support@bitanic.id"
LICENSE_SUPPORT_PHONE="+62 811-xxxx-xxxx"
LICENSE_SUPPORT_URL="https://bitanic.id/bantuan"
```

---

## 3. Scheduled Tasks & Worker Execution

### 3.1 Cron Scheduler Setup
Ensure the Laravel scheduler runs every minute on the production server crontab:

```bash
* * * * * cd /path/to/bitanic-app && php artisan schedule:run >> /dev/null 2>&1
```

The reminder checker is scheduled to run daily at 07:00 WIB (00:00 UTC) with explicit timezone configuration:
```php
Schedule::command('bitanic:check-license-reminders')->dailyAt('07:00')->timezone('Asia/Jakarta');
```

### 3.2 Queue Worker Setup
Automated reminder emails are dispatched asynchronously via Laravel Queue. Ensure a supervisor or background daemon worker is running:

```bash
php artisan queue:work --queue=default --tries=3 --timeout=30
```

---

## 4. Manual Operations & CLI Commands

### 4.1 Dry-Run Inspection
To inspect upcoming license expirations without sending emails or modifying database tables:

```bash
php artisan bitanic:check-license-reminders --dry-run
```

### 4.2 Custom Threshold Dispatch
To check and dispatch reminders for a custom threshold (e.g. 7 days):

```bash
php artisan bitanic:check-license-reminders --days=7
```

---

## 5. Annual Renewal & Access Restoration Workflow

When an expired school completes its institutional renewal:
1. Superuser accesses `/master/licenses/{id}`.
2. Under **Perpanjangan Tahunan Lisensi LMS**, inputs the extension duration (e.g., 1 tahun) and notes/reason.
3. Submitting the renewal creates a new term in `school_license_terms` and immediately recalculates the license status to `active`.
4. **Immediate Restoration**: On the subsequent HTTP request, the middleware detects `active` status. Guru, Siswa, and Admin access is restored instantly with zero manual intervention or cache resets.

---

## 6. Audit Trail & Traceability

Every reminder notification is logged in two append-only ledgers:
1. `school_license_notifications`: Records `license_id`, `term_id`, `recipient_email`, `status` (`queued`, `sent`, `failed`, `skipped`), and `sent_at`.
2. `school_license_logs`: Immutable audit trail recording the system-triggered reminder action (`reminder_h14_sent`).

