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

## Workflows Covered
1. **Guest Public Workflow**: Dashboard, IoT Monitoring, Module Catalog, authentication gates.
2. **Siswa Workflow**: Authentication, practicum module view, LKS tab submission with notes, reports hub, unassessed gradebook state, logout.
3. **Guru Workflow**: Authentication, classroom management, scheduling, gradebook review, verified CSV export, media tanam, directory search, logout.
4. **Admin Workflow**: Authentication, school settings & audit trail inspection, trainer kit fleet management, logout.
5. **Superuser Workflow**: Authentication, multi-school global dashboard, raw Modbus RS485 debugger stream, logout.
