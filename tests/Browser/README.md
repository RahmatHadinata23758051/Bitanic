# Bitanic Real Browser E2E Interaction Test Suite

This directory contains real browser-based interaction tests executed via Playwright (Headless Chromium).

## Prerequisites
- Python 3.10+
- `pip install playwright`
- `playwright install chromium`

## Test Execution
1. Set up isolated test database fixtures:
   ```bash
   php artisan bitanic:setup-browser-e2e --env=browser_e2e
   ```
2. Start test server on port 8008:
   ```powershell
   $env:APP_ENV="browser_e2e"; php artisan serve --port=8008
   ```
3. Run the E2E test suite:
   ```bash
   python -u tests/Browser/e2e_playwright_test.py
   ```

## Workflows Covered (8 Scenarios)
1. **Superuser School Switching & Multi-School Access**: Verify multi-school dropdown, switching between School 1 and School 2, fail-closed HTTP 404 on nonexistent/malformed school IDs, and platform-only raw logs debugger.
2. **Admin Same-School Dashboard & Absence of School Switcher**: Verify admin is strictly isolated to own school, absence of school switcher dropdown, presence of own fleet (TK-01) and absence of foreign fleet (TK-02).
3. **Admin Foreign-School Selector Tampering (Fail-Closed 403)**: Verify hostile queries (?school_id=2, ?school_id=99999, ?school_id=abc, ?school_id[]=1) strictly receive HTTP 403.
4. **Admin Foreign-School Directory Search**: Verify user directory isolation, absence of school dropdown, rejection of ?school_id=2 with HTTP 403, and search query isolation preventing foreign student leakage.
5. **Admin Foreign-School Gradebook & Export Access**: Verify own-school assessment access and CSV export, and rejection of foreign gradebook and foreign CSV export with HTTP 403.
6. **Admin Unauthorized Direct URLs & Mutation Attempts**: Verify platform-only API key rotation rejection with HTTP 403, foreign device mutation rejection with HTTP 403, and raw-logs injection rejection with HTTP 403.
7. **Normal Guru Workflow & Tenant Protection**: Verify classroom management, scheduling, gradebook review, verified CSV export, and rejection of foreign school tampering with HTTP 403.
8. **Normal Siswa Workflow & Tenant Protection**: Verify practicum module view, LKS observation notes submission, reports hub, honest missing grade assessment view, settings rejection with HTTP 403, and foreign school tampering rejection with HTTP 403.

