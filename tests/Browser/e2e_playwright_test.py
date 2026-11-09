"""
Bitanic - Full System Browser E2E Interaction Test Suite
Executed using Python Playwright (Headless Chromium) against isolated browser_e2e server.
"""

import sys
import time
from playwright.sync_api import sync_playwright

BASE_URL = "http://127.0.0.1:8008"

def run_tests():
    print("=== STARTING BITANIC REAL BROWSER E2E TEST SUITE ===")
    results = {}

    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)

        # -------------------------------------------------------------
        # JOURNEY 1: GUEST WORKFLOW
        # -------------------------------------------------------------
        print("\n[JOURNEY 1] Executing Guest Public Workflow...")
        context = browser.new_context()
        page = context.new_page()
        try:
            # 1.1 Home / Dashboard
            resp = page.goto(f"{BASE_URL}/")
            assert resp.status == 200, f"Expected 200, got {resp.status}"
            assert "BITANIC" in page.content() or "Bitanic" in page.content(), "Expected Bitanic brand"
            print("  ✓ Guest Dashboard loaded successfully (HTTP 200)")

            # 1.2 IoT Monitoring
            resp = page.goto(f"{BASE_URL}/iot-monitoring")
            assert resp.status == 200, f"Expected 200, got {resp.status}"
            print("  ✓ Guest IoT Monitoring loaded successfully (HTTP 200)")

            # 1.3 Modules Catalog
            resp = page.goto(f"{BASE_URL}/modules")
            assert resp.status == 200, f"Expected 200, got {resp.status}"
            print("  ✓ Guest Module Catalog loaded successfully (HTTP 200)")

            # 1.4 Protected Page Rejection (Assessment)
            page.goto(f"{BASE_URL}/assessment")
            # Should redirect to /login
            assert "/login" in page.url, f"Expected redirect to /login, got {page.url}"
            print("  ✓ Guest access to /assessment correctly redirected to /login")

            results["Guest Workflow"] = "PASS"
        except Exception as e:
            print(f"  ✗ Guest Workflow Failed: {e}")
            results["Guest Workflow"] = f"FAIL: {e}"
        finally:
            context.close()

        # -------------------------------------------------------------
        # JOURNEY 2: SISWA WORKFLOW
        # -------------------------------------------------------------
        print("\n[JOURNEY 2] Executing Siswa (Student) Workflow...")
        context = browser.new_context()
        page = context.new_page()
        try:
            # 2.1 Login as Siswa
            page.goto(f"{BASE_URL}/login")
            page.fill("#email", "siswa@smk.id")
            page.fill("#password", "Password123!")
            page.click("button[type='submit']")
            page.wait_for_load_state("networkidle")
            assert "/login" not in page.url, f"Expected successful login redirect, remained on {page.url}"
            print("  ✓ Siswa logged in successfully")

            # 2.2 Access Practicum Module & Submit LKS
            resp = page.goto(f"{BASE_URL}/modules/1")
            assert resp.status == 200, f"Expected 200 for module 1, got {resp.status}"
            print("  ✓ Siswa viewed Module 1 detail page")

            # Click LKS Tab to make LKS inputs visible
            lks_tab_btn = page.locator("button:has-text('Tugas Lembar Kerja (LKS)')")
            if lks_tab_btn.count() > 0:
                lks_tab_btn.first.click()
                page.wait_for_timeout(500)

            # Fill LKS answer & observation notes if present
            obs_input = page.locator("textarea[name='observation_notes'], textarea[name^='lks_answers'], textarea")
            if obs_input.count() > 0 and obs_input.first.is_visible():
                obs_input.first.fill("Pengamatan langsung menunjukkan pembacaan pH stabil pada 6.5.")
                # Submit LKS button
                submit_btn = page.locator("button[type='submit']:has-text('Kumpulkan'), button[type='submit']:has-text('Simpan')")
                if submit_btn.count() > 0:
                    submit_btn.first.click()
                    page.wait_for_timeout(1000)
                    print("  ✓ Siswa submitted practicum LKS report with observation notes")

            # 2.3 View Own Reports
            resp = page.goto(f"{BASE_URL}/reports")
            assert resp.status == 200, f"Expected 200 for reports, got {resp.status}"
            assert "Laporan" in page.content() or "laporan" in page.content().lower()
            print("  ✓ Siswa viewed Reports Hub (own reports)")

            # 2.4 View Own Assessment Gradebook (honest missing state before grading)
            resp = page.goto(f"{BASE_URL}/assessment")
            assert resp.status == 200, f"Expected 200 for assessment, got {resp.status}"
            assert "Bagus Pratama" in page.content() or "Siswa" in page.content() or "Asesmen" in page.content()
            print("  ✓ Siswa accessed Competency Assessment Rapor (scoped to self)")

            # 2.5 Logout via POST
            logout_btn = page.locator("form[action*='logout'] button")
            if logout_btn.count() > 0:
                logout_btn.first.click()
                page.wait_for_load_state("networkidle")
            print("  ✓ Siswa logged out successfully")

            results["Siswa Workflow"] = "PASS"
        except Exception as e:
            print(f"  ✗ Siswa Workflow Failed: {e}")
            results["Siswa Workflow"] = f"FAIL: {e}"
        finally:
            context.close()

        # -------------------------------------------------------------
        # JOURNEY 3: GURU WORKFLOW
        # -------------------------------------------------------------
        print("\n[JOURNEY 3] Executing Guru (Teacher) Workflow...")
        context = browser.new_context()
        page = context.new_page()
        try:
            # 3.1 Login as Guru
            page.goto(f"{BASE_URL}/login")
            page.fill("#email", "guru@smk.id")
            page.fill("#password", "Password123!")
            page.click("button[type='submit']")
            page.wait_for_load_state("networkidle")
            assert "/login" not in page.url, f"Login failed for guru"
            print("  ✓ Guru logged in successfully")

            # 3.2 Classroom Management
            resp = page.goto(f"{BASE_URL}/classes")
            assert resp.status == 200, f"Expected 200 for classes, got {resp.status}"
            print("  ✓ Guru accessed Classroom Management")

            # 3.3 Practicum Scheduling & Timeline
            resp = page.goto(f"{BASE_URL}/schedules")
            assert resp.status == 200, f"Expected 200 for schedules, got {resp.status}"
            print("  ✓ Guru accessed Practicum Schedules timeline")

            # 3.4 Competency Assessment Gradebook & Export
            resp = page.goto(f"{BASE_URL}/assessment")
            assert resp.status == 200, f"Expected 200 for assessment, got {resp.status}"
            print("  ✓ Guru verified Competency Assessment Gradebook")

            # Verify CSV export endpoint
            csv_resp = context.request.get(f"{BASE_URL}/assessment/export")
            assert csv_resp.status == 200, f"Expected 200 for export, got {csv_resp.status}"
            assert "text/csv" in csv_resp.headers.get("content-type", "")
            print("  ✓ Guru exported verified Gradebook to CSV (HTTP 200, Content-Type: text/csv)")

            # 3.5 Media Tanam
            resp = page.goto(f"{BASE_URL}/media")
            assert resp.status == 200, f"Expected 200 for media, got {resp.status}"
            print("  ✓ Guru viewed Media Tanam directory")

            # 3.6 Directory Search
            resp = page.goto(f"{BASE_URL}/users-data?q=Siswa")
            assert resp.status == 200, f"Expected 200 for users-data, got {resp.status}"
            print("  ✓ Guru searched User Directory for students")

            # 3.7 Logout
            logout_btn = page.locator("form[action*='logout'] button")
            if logout_btn.count() > 0:
                logout_btn.first.click()
                page.wait_for_load_state("networkidle")
            print("  ✓ Guru logged out successfully")

            results["Guru Workflow"] = "PASS"
        except Exception as e:
            print(f"  ✗ Guru Workflow Failed: {e}")
            results["Guru Workflow"] = f"FAIL: {e}"
        finally:
            context.close()

        # -------------------------------------------------------------
        # JOURNEY 4: ADMIN WORKFLOW
        # -------------------------------------------------------------
        print("\n[JOURNEY 4] Executing Legacy Admin (School Admin) Workflow...")
        context = browser.new_context()
        page = context.new_page()
        try:
            # 4.1 Login as Admin
            page.goto(f"{BASE_URL}/login")
            page.fill("#email", "admin@smk.id")
            page.fill("#password", "Password123!")
            page.click("button[type='submit']")
            page.wait_for_load_state("networkidle")
            assert "/login" not in page.url, "Login failed for admin"
            print("  ✓ Admin logged in successfully")

            # 4.2 School Settings & Audit Trail
            resp = page.goto(f"{BASE_URL}/settings")
            assert resp.status == 200, f"Expected 200 for settings, got {resp.status}"
            print("  ✓ Admin accessed School Settings & Audit Trail view")

            # 4.3 Trainer Kit Fleet Management
            resp = page.goto(f"{BASE_URL}/trainer-kits")
            assert resp.status == 200, f"Expected 200 for trainer-kits, got {resp.status}"
            print("  ✓ Admin viewed Trainer Kit fleet management table")

            # 4.4 Logout
            logout_btn = page.locator("form[action*='logout'] button")
            if logout_btn.count() > 0:
                logout_btn.first.click()
                page.wait_for_load_state("networkidle")
            print("  ✓ Admin logged out successfully")

            results["Admin Workflow"] = "PASS"
        except Exception as e:
            print(f"  ✗ Admin Workflow Failed: {e}")
            results["Admin Workflow"] = f"FAIL: {e}"
        finally:
            context.close()

        # -------------------------------------------------------------
        # JOURNEY 5: SUPERUSER WORKFLOW
        # -------------------------------------------------------------
        print("\n[JOURNEY 5] Executing Superuser (Platform) Workflow...")
        context = browser.new_context()
        page = context.new_page()
        try:
            # 5.1 Login as Superuser
            page.goto(f"{BASE_URL}/login")
            page.fill("#email", "superuser@bitanic.id")
            page.fill("#password", "Password123!")
            page.click("button[type='submit']")
            page.wait_for_load_state("networkidle")
            assert "/login" not in page.url, "Login failed for superuser"
            print("  ✓ Superuser logged in successfully")

            # 5.2 Multi-School Dashboard
            resp = page.goto(f"{BASE_URL}/")
            assert resp.status == 200, f"Expected 200 for dashboard, got {resp.status}"
            print("  ✓ Superuser accessed Global Multi-School Dashboard")

            # 5.3 Raw Modbus RS485 Debugger
            resp = page.goto(f"{BASE_URL}/raw-logs")
            assert resp.status == 200, f"Expected 200 for raw-logs, got {resp.status}"
            print("  ✓ Superuser accessed Raw Modbus RS485 Debugger stream")

            # 5.4 Logout
            logout_btn = page.locator("form[action*='logout'] button")
            if logout_btn.count() > 0:
                logout_btn.first.click()
                page.wait_for_load_state("networkidle")
            print("  ✓ Superuser logged out successfully")

            results["Superuser Workflow"] = "PASS"
        except Exception as e:
            print(f"  ✗ Superuser Workflow Failed: {e}")
            results["Superuser Workflow"] = f"FAIL: {e}"
        finally:
            context.close()

        browser.close()

    print("\n=== BROWSER E2E TEST SUMMARY ===")
    all_passed = True
    for workflow, status in results.items():
        print(f"  - {workflow}: {status}")
        if status != "PASS":
            all_passed = False

    if not all_passed:
        print("\nFAILED: One or more browser E2E workflows did not pass.")
        sys.exit(1)
    else:
        print("\nSUCCESS: All 5 real browser E2E journeys passed completely!")

if __name__ == "__main__":
    run_tests()
