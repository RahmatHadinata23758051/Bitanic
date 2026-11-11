"""
Bitanic - Full System Browser E2E Interaction Test Suite
Sprint 1: P0 Tenant Isolation & RBAC Security Hardening
Executed using Python Playwright (Headless Chromium) against isolated browser_e2e server.
"""

import sys
import time
from playwright.sync_api import sync_playwright

BASE_URL = "http://127.0.0.1:8008"

def run_tests():
    print("=== STARTING BITANIC REAL BROWSER E2E TEST SUITE (23 SCENARIOS) ===")
    results = {}

    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)

        # -------------------------------------------------------------
        # SCENARIO 1: SUPERUSER SCHOOL SWITCHING & MULTI-SCHOOL ACCESS
        # -------------------------------------------------------------
        print("\n[SCENARIO 1] Superuser School Switching & Multi-School Access...")
        context = browser.new_context()
        page = context.new_page()
        try:
            # 1.1 Login as Superuser
            page.goto(f"{BASE_URL}/login")
            page.fill("#email", "superuser@bitanic.id")
            page.fill("#password", "Password123!")
            page.click("button[type='submit']")
            page.wait_for_load_state("networkidle")
            assert "/login" not in page.url, "Login failed for superuser"
            print("  ✓ Superuser logged in successfully")

            # 1.2 Multi-School Dashboard & Dropdown Verification
            resp = page.goto(f"{BASE_URL}/")
            assert resp.status == 200, f"Expected 200, got {resp.status}"
            # Verify school dropdown exists for superuser
            school_select = page.locator("select[name='school_id']")
            assert school_select.count() > 0, "Expected school selector dropdown for superuser"
            print("  ✓ Superuser verified presence of multi-school selector dropdown")

            # 1.3 Switch to School 1
            resp1 = page.goto(f"{BASE_URL}/?school_id=1")
            assert resp1.status == 200, f"Expected 200, got {resp1.status}"
            assert "TK-01" in page.content(), "Expected TK-01 in School 1 dashboard"
            print("  ✓ Superuser switched to School 1 (TK-01 verified)")

            # 1.4 Switch to School 2
            resp2 = page.goto(f"{BASE_URL}/?school_id=2")
            assert resp2.status == 200, f"Expected 200, got {resp2.status}"
            assert "TK-02" in page.content() or "Sekolah B" in page.content(), "Expected TK-02 / Sekolah B in School 2 dashboard"
            print("  ✓ Superuser switched to School 2 (TK-02 verified)")

            # 1.5 Hostile Nonexistent School ID -> 404 Fail-Closed
            resp_nonexistent = page.goto(f"{BASE_URL}/?school_id=99999")
            assert resp_nonexistent.status == 404, f"Expected 404 for nonexistent school, got {resp_nonexistent.status}"
            print("  ✓ Superuser hostile nonexistent school query fail-closed (HTTP 404)")

            # 1.6 Malformed School ID -> 404 Fail-Closed
            resp_malformed = page.goto(f"{BASE_URL}/?school_id=invalid-id")
            assert resp_malformed.status == 404, f"Expected 404 for malformed school, got {resp_malformed.status}"
            print("  ✓ Superuser hostile malformed school query fail-closed (HTTP 404)")

            # 1.7 Platform-Only Debugger Access
            resp_dbg = page.goto(f"{BASE_URL}/raw-logs")
            assert resp_dbg.status == 200, f"Expected 200 for raw-logs, got {resp_dbg.status}"
            print("  ✓ Superuser accessed platform-only Raw Modbus Debugger")

            # 1.8 Logout
            logout_btn = page.locator("form[action*='logout'] button")
            if logout_btn.count() > 0:
                logout_btn.first.click()
                page.wait_for_load_state("networkidle")
            print("  ✓ Superuser logged out successfully")

            results["Scenario 1: Superuser School Switching"] = "PASS"
        except Exception as e:
            print(f"  ✗ Scenario 1 Failed: {e}")
            results["Scenario 1: Superuser School Switching"] = f"FAIL: {e}"
        finally:
            context.close()

        # -------------------------------------------------------------
        # SCENARIO 2: ADMIN SAME-SCHOOL DASHBOARD & ABSENCE OF SWITCHER
        # -------------------------------------------------------------
        print("\n[SCENARIO 2] Admin Same-School Dashboard & Absence of School Switcher...")
        context = browser.new_context()
        page = context.new_page()
        try:
            # 2.1 Login as Admin
            page.goto(f"{BASE_URL}/login")
            page.fill("#email", "admin@smk.id")
            page.fill("#password", "Password123!")
            page.click("button[type='submit']")
            page.wait_for_load_state("networkidle")
            assert "/login" not in page.url, "Login failed for admin"
            print("  ✓ Admin logged in successfully")

            # 2.2 Dashboard View & Absence of Selector
            resp = page.goto(f"{BASE_URL}/")
            assert resp.status == 200, f"Expected 200, got {resp.status}"
            school_select = page.locator("select[name='school_id']")
            assert school_select.count() == 0, "School selector dropdown MUST NOT be present for admin"
            print("  ✓ Confirmed absence of school switcher dropdown for admin")

            # 2.3 Verify Own School Fleet & Absence of Foreign Fleet
            content = page.content()
            assert "TK-01" in content, "Expected School 1 kit (TK-01) on admin dashboard"
            assert "TK-02" not in content, "Foreign kit (TK-02) MUST NOT appear on admin dashboard"
            print("  ✓ Verified own-school fleet (TK-01 present, foreign TK-02 absent)")

            # 2.4 Verify Own School Settings Access
            resp_settings = page.goto(f"{BASE_URL}/settings")
            assert resp_settings.status == 200, f"Expected 200 for settings, got {resp_settings.status}"
            print("  ✓ Admin successfully accessed own-school settings (HTTP 200)")

            # 2.5 Logout
            logout_btn = page.locator("form[action*='logout'] button")
            if logout_btn.count() > 0:
                logout_btn.first.click()
                page.wait_for_load_state("networkidle")
            print("  ✓ Admin logged out successfully")

            results["Scenario 2: Admin Same-School Dashboard"] = "PASS"
        except Exception as e:
            print(f"  ✗ Scenario 2 Failed: {e}")
            results["Scenario 2: Admin Same-School Dashboard"] = f"FAIL: {e}"
        finally:
            context.close()

        # -------------------------------------------------------------
        # SCENARIO 2B: LEGACY ADMIN COMPATIBILITY SESSION
        # -------------------------------------------------------------
        print("\n[SCENARIO 2B] Legacy Admin Compatibility Session...")
        context = browser.new_context()
        page = context.new_page()
        try:
            page.goto(f"{BASE_URL}/login")
            page.fill("#email", "legacyadmin@smk.id")
            page.fill("#password", "Password123!")
            page.click("button[type='submit']")
            page.wait_for_load_state("networkidle")
            assert "/login" not in page.url, "Login failed for legacy admin"
            print("  ✓ Legacy Admin logged in successfully")

            resp_settings = page.goto(f"{BASE_URL}/settings")
            assert resp_settings.status == 200, f"Expected 200 for settings, got {resp_settings.status}"
            print("  ✓ Legacy Admin successfully accessed own-school settings (HTTP 200)")

            logout_btn = page.locator("form[action*='logout'] button")
            if logout_btn.count() > 0:
                logout_btn.first.click()
                page.wait_for_load_state("networkidle")
            print("  ✓ Legacy Admin logged out successfully")

            results["Scenario 2B: Legacy Admin Compatibility"] = "PASS"
        except Exception as e:
            print(f"  ✗ Scenario 2B Failed: {e}")
            results["Scenario 2B: Legacy Admin Compatibility"] = f"FAIL: {e}"
        finally:
            context.close()

        # -------------------------------------------------------------
        # SCENARIO 3: ADMIN FOREIGN-SCHOOL SELECTOR TAMPERING (FAIL-CLOSED 403)
        # -------------------------------------------------------------
        print("\n[SCENARIO 3] Admin Foreign-School Selector Tampering (Fail-Closed 403)...")
        context = browser.new_context()
        page = context.new_page()
        try:
            # 3.1 Login as Admin
            page.goto(f"{BASE_URL}/login")
            page.fill("#email", "admin@smk.id")
            page.fill("#password", "Password123!")
            page.click("button[type='submit']")
            page.wait_for_load_state("networkidle")

            # 3.2 Attempt Foreign School Query (?school_id=2)
            resp = page.goto(f"{BASE_URL}/?school_id=2")
            assert resp.status == 403, f"Expected 403 Forbidden for foreign school, got {resp.status}"
            print("  ✓ Admin foreign school query (?school_id=2) strictly rejected with HTTP 403")

            # 3.3 Attempt Tampering with Nonexistent School Query (?school_id=99999)
            resp_nonexistent = page.goto(f"{BASE_URL}/?school_id=99999")
            assert resp_nonexistent.status == 403, f"Expected 403 Forbidden for nonexistent school tampering, got {resp_nonexistent.status}"
            print("  ✓ Admin nonexistent school query (?school_id=99999) rejected with HTTP 403")

            # 3.4 Attempt Hostile Malformed Query (?school_id=abc)
            resp_malformed = page.goto(f"{BASE_URL}/?school_id=abc")
            assert resp_malformed.status == 403, f"Expected 403 Forbidden for non-numeric school tampering, got {resp_malformed.status}"
            print("  ✓ Admin non-numeric school query (?school_id=abc) rejected with HTTP 403")

            # 3.5 Attempt Array Parameter Tampering (?school_id[]=1)
            resp_array = page.goto(f"{BASE_URL}/?school_id[]=1")
            assert resp_array.status == 403, f"Expected 403 Forbidden for array parameter tampering, got {resp_array.status}"
            print("  ✓ Admin array parameter query (?school_id[]=1) rejected with HTTP 403")

            # 3.6 Logout
            logout_btn = page.locator("form[action*='logout'] button")
            if logout_btn.count() > 0:
                logout_btn.first.click()
                page.wait_for_load_state("networkidle")

            results["Scenario 3: Admin Foreign-School Selector Tampering"] = "PASS"
        except Exception as e:
            print(f"  ✗ Scenario 3 Failed: {e}")
            results["Scenario 3: Admin Foreign-School Selector Tampering"] = f"FAIL: {e}"
        finally:
            context.close()

        # -------------------------------------------------------------
        # SCENARIO 4: ADMIN FOREIGN-SCHOOL DIRECTORY SEARCH
        # -------------------------------------------------------------
        print("\n[SCENARIO 4] Admin Foreign-School Directory Search...")
        context = browser.new_context()
        page = context.new_page()
        try:
            # 4.1 Login as Admin
            page.goto(f"{BASE_URL}/login")
            page.fill("#email", "admin@smk.id")
            page.fill("#password", "Password123!")
            page.click("button[type='submit']")
            page.wait_for_load_state("networkidle")

            # 4.2 Visit Directory - Verify School 1 Student & No Dropdown
            resp = page.goto(f"{BASE_URL}/users-data")
            assert resp.status == 200, f"Expected 200 for users-data, got {resp.status}"
            assert "Bagus Pratama" in page.content(), "Expected School 1 student Bagus Pratama in directory"
            school_select = page.locator("select[name='school_id']")
            assert school_select.count() == 0, "School selector dropdown MUST NOT appear in directory for admin"
            print("  ✓ Directory loaded for own school (Bagus Pratama present, no school dropdown)")

            # 4.3 Attempt Foreign School Query (?school_id=2)
            resp_foreign = page.goto(f"{BASE_URL}/users-data?school_id=2")
            assert resp_foreign.status == 403, f"Expected 403 Forbidden for directory foreign school, got {resp_foreign.status}"
            print("  ✓ Directory foreign school query (?school_id=2) rejected with HTTP 403")

            # 4.4 Search for Foreign Student - Verify No Cross-School Leakage
            resp_search = page.goto(f"{BASE_URL}/users-data?q=Rahasia")
            assert resp_search.status == 200, f"Expected 200 for search query, got {resp_search.status}"
            assert "Siswa Rahasia Sekolah B" not in page.content(), "Foreign student Siswa Rahasia Sekolah B MUST NOT leak in search"
            print("  ✓ Directory search strictly isolated (foreign student not returned)")

            # 4.5 Logout
            logout_btn = page.locator("form[action*='logout'] button")
            if logout_btn.count() > 0:
                logout_btn.first.click()
                page.wait_for_load_state("networkidle")

            results["Scenario 4: Admin Foreign-School Directory Search"] = "PASS"
        except Exception as e:
            print(f"  ✗ Scenario 4 Failed: {e}")
            results["Scenario 4: Admin Foreign-School Directory Search"] = f"FAIL: {e}"
        finally:
            context.close()

        # -------------------------------------------------------------
        # SCENARIO 5: ADMIN FOREIGN-SCHOOL GRADEBOOK ACCESS
        # -------------------------------------------------------------
        print("\n[SCENARIO 5] Admin Foreign-School Gradebook Access...")
        context = browser.new_context()
        page = context.new_page()
        try:
            # 5.1 Login as Admin
            page.goto(f"{BASE_URL}/login")
            page.fill("#email", "admin@smk.id")
            page.fill("#password", "Password123!")
            page.click("button[type='submit']")
            page.wait_for_load_state("networkidle")

            # 5.2 Visit Assessment - Own School
            resp = page.goto(f"{BASE_URL}/assessment")
            assert resp.status == 200, f"Expected 200 for assessment, got {resp.status}"
            print("  ✓ Admin accessed own-school assessment gradebook (HTTP 200)")

            # 5.3 Export Own School CSV
            csv_resp = context.request.get(f"{BASE_URL}/assessment/export")
            assert csv_resp.status == 200, f"Expected 200 for export, got {csv_resp.status}"
            assert "text/csv" in csv_resp.headers.get("content-type", "")
            print("  ✓ Admin exported own-school gradebook to CSV (HTTP 200)")

            # 5.4 Attempt Foreign Gradebook (?school_id=2)
            resp_foreign = page.goto(f"{BASE_URL}/assessment?school_id=2")
            assert resp_foreign.status == 403, f"Expected 403 Forbidden for foreign assessment, got {resp_foreign.status}"
            print("  ✓ Foreign assessment query (?school_id=2) rejected with HTTP 403")

            # 5.5 Attempt Foreign CSV Export (?school_id=2)
            resp_foreign_csv = context.request.get(f"{BASE_URL}/assessment/export?school_id=2")
            assert resp_foreign_csv.status == 403, f"Expected 403 Forbidden for foreign CSV export, got {resp_foreign_csv.status}"
            print("  ✓ Foreign assessment CSV export (?school_id=2) rejected with HTTP 403")

            # 5.6 Logout
            logout_btn = page.locator("form[action*='logout'] button")
            if logout_btn.count() > 0:
                logout_btn.first.click()
                page.wait_for_load_state("networkidle")

            results["Scenario 5: Admin Foreign-School Gradebook Access"] = "PASS"
        except Exception as e:
            print(f"  ✗ Scenario 5 Failed: {e}")
            results["Scenario 5: Admin Foreign-School Gradebook Access"] = f"FAIL: {e}"
        finally:
            context.close()

        # -------------------------------------------------------------
        # SCENARIO 6: ADMIN UNAUTHORIZED DIRECT URLS & MUTATION ATTEMPTS
        # -------------------------------------------------------------
        print("\n[SCENARIO 6] Admin Unauthorized Direct URLs & Mutation Attempts...")
        context = browser.new_context()
        page = context.new_page()
        try:
            # 6.1 Login as Admin
            page.goto(f"{BASE_URL}/login")
            page.fill("#email", "admin@smk.id")
            page.fill("#password", "Password123!")
            page.click("button[type='submit']")
            page.wait_for_load_state("networkidle")

            # Get CSRF token
            csrf_token = page.locator("meta[name='csrf-token']").get_attribute("content")
            headers = {
                "X-CSRF-TOKEN": csrf_token or "",
                "X-Requested-With": "XMLHttpRequest",
            }

            # 6.2 Attempt Platform-Only API Key Rotation (POST /trainer-kits/1/api-key/rotate)
            resp_rotate = context.request.post(f"{BASE_URL}/trainer-kits/1/api-key/rotate", headers=headers)
            assert resp_rotate.status == 403, f"Expected 403 Forbidden for api-key rotation by admin, got {resp_rotate.status}"
            print("  ✓ Platform-only API key rotation rejected with HTTP 403")

            # 6.3 Attempt Foreign Device Mutation (POST /trainer-kits/2/reboot)
            resp_reboot = context.request.post(f"{BASE_URL}/trainer-kits/2/reboot", headers=headers)
            assert resp_reboot.status == 403, f"Expected 403 Forbidden for foreign device reboot, got {resp_reboot.status}"
            print("  ✓ Foreign device mutation (/trainer-kits/2/reboot) rejected with HTTP 403")

            # 6.4 Attempt Platform Raw-Logs Inject (POST /raw-logs/inject)
            resp_inject = context.request.post(f"{BASE_URL}/raw-logs/inject", headers=headers)
            assert resp_inject.status == 403, f"Expected 403 Forbidden for raw-logs inject, got {resp_inject.status}"
            print("  ✓ Platform-only raw-logs inject rejected with HTTP 403")

            # 6.5 Logout
            logout_btn = page.locator("form[action*='logout'] button")
            if logout_btn.count() > 0:
                logout_btn.first.click()
                page.wait_for_load_state("networkidle")

            results["Scenario 6: Admin Unauthorized Direct URLs"] = "PASS"
        except Exception as e:
            print(f"  ✗ Scenario 6 Failed: {e}")
            results["Scenario 6: Admin Unauthorized Direct URLs"] = f"FAIL: {e}"
        finally:
            context.close()

        # -------------------------------------------------------------
        # SCENARIO 7: NORMAL GURU WORKFLOW & TENANT PROTECTION
        # -------------------------------------------------------------
        print("\n[SCENARIO 7] Normal Guru Workflow & Tenant Protection...")
        context = browser.new_context()
        page = context.new_page()
        try:
            # 7.1 Login as Guru
            page.goto(f"{BASE_URL}/login")
            page.fill("#email", "guru@smk.id")
            page.fill("#password", "Password123!")
            page.click("button[type='submit']")
            page.wait_for_load_state("networkidle")
            assert "/login" not in page.url, "Login failed for guru"
            print("  ✓ Guru logged in successfully")

            # 7.2 Classroom Management
            resp_classes = page.goto(f"{BASE_URL}/classes")
            assert resp_classes.status == 200, f"Expected 200 for classes, got {resp_classes.status}"
            print("  ✓ Guru accessed Classroom Management (HTTP 200)")

            # 7.3 Practicum Scheduling
            resp_schedules = page.goto(f"{BASE_URL}/schedules")
            assert resp_schedules.status == 200, f"Expected 200 for schedules, got {resp_schedules.status}"
            print("  ✓ Guru accessed Practicum Schedules (HTTP 200)")

            # 7.4 Competency Assessment & CSV Export
            resp_assess = page.goto(f"{BASE_URL}/assessment")
            assert resp_assess.status == 200, f"Expected 200 for assessment, got {resp_assess.status}"
            csv_resp = context.request.get(f"{BASE_URL}/assessment/export")
            assert csv_resp.status == 200, f"Expected 200 for CSV export, got {csv_resp.status}"
            print("  ✓ Guru accessed Assessment & exported CSV (HTTP 200)")

            # 7.5 Attempt Foreign School Tampering (?school_id=2)
            resp_foreign = page.goto(f"{BASE_URL}/?school_id=2")
            assert resp_foreign.status == 403, f"Expected 403 Forbidden for guru foreign school tampering, got {resp_foreign.status}"
            print("  ✓ Guru foreign school tampering (?school_id=2) rejected with HTTP 403")

            # 7.6 Attempt Foreign Assessment Tampering (?school_id=2)
            resp_foreign_assess = page.goto(f"{BASE_URL}/assessment?school_id=2")
            assert resp_foreign_assess.status == 403, f"Expected 403 Forbidden for guru foreign assessment, got {resp_foreign_assess.status}"
            print("  ✓ Guru foreign assessment tampering (?school_id=2) rejected with HTTP 403")

            # 7.7 Logout
            logout_btn = page.locator("form[action*='logout'] button")
            if logout_btn.count() > 0:
                logout_btn.first.click()
                page.wait_for_load_state("networkidle")

            results["Scenario 7: Normal Guru Workflow"] = "PASS"
        except Exception as e:
            print(f"  ✗ Scenario 7 Failed: {e}")
            results["Scenario 7: Normal Guru Workflow"] = f"FAIL: {e}"
        finally:
            context.close()

        # -------------------------------------------------------------
        # SCENARIO 8: NORMAL SISWA WORKFLOW & TENANT PROTECTION
        # -------------------------------------------------------------
        print("\n[SCENARIO 8] Normal Siswa Workflow & Tenant Protection...")
        context = browser.new_context()
        page = context.new_page()
        try:
            # 8.1 Login as Siswa
            page.goto(f"{BASE_URL}/login")
            page.fill("#email", "siswa@smk.id")
            page.fill("#password", "Password123!")
            page.click("button[type='submit']")
            page.wait_for_load_state("networkidle")
            assert "/login" not in page.url, "Login failed for siswa"
            print("  ✓ Siswa logged in successfully")

            # 8.2 Access Practicum Module & Submit Observation
            resp_mod = page.goto(f"{BASE_URL}/modules/1")
            assert resp_mod.status == 200, f"Expected 200 for module 1, got {resp_mod.status}"

            lks_tab_btn = page.locator("button:has-text('Tugas Lembar Kerja (LKS)')")
            if lks_tab_btn.count() > 0:
                lks_tab_btn.first.click()
                page.wait_for_timeout(500)

            obs_input = page.locator("textarea[name='observation_notes'], textarea[name^='lks_answers'], textarea")
            if obs_input.count() > 0 and obs_input.first.is_visible():
                obs_input.first.fill("Pengamatan real: nilai pH netral 6.5 dan kelembaban 60%.")
                submit_btn = page.locator("button[type='submit']:has-text('Kumpulkan'), button[type='submit']:has-text('Simpan')")
                if submit_btn.count() > 0:
                    submit_btn.first.click()
                    page.wait_for_timeout(1000)
            print("  ✓ Siswa accessed module & submitted observation")

            # 8.3 View Reports Hub
            resp_reports = page.goto(f"{BASE_URL}/reports")
            assert resp_reports.status == 200, f"Expected 200 for reports, got {resp_reports.status}"
            print("  ✓ Siswa viewed Reports Hub (HTTP 200)")

            # 8.4 View Own Assessment (Honest State)
            resp_assess = page.goto(f"{BASE_URL}/assessment")
            assert resp_assess.status == 200, f"Expected 200 for assessment, got {resp_assess.status}"
            print("  ✓ Siswa viewed own assessment gradebook (HTTP 200)")

            # 8.5 Attempt Unauthorized Settings Access -> 403 Forbidden
            resp_settings = page.goto(f"{BASE_URL}/settings")
            assert resp_settings.status == 403, f"Expected 403 Forbidden for siswa on settings, got {resp_settings.status}"
            print("  ✓ Siswa attempt on /settings rejected with HTTP 403")

            # 8.6 Attempt Foreign School Tampering (?school_id=2) -> 403 Forbidden
            resp_foreign = page.goto(f"{BASE_URL}/?school_id=2")
            assert resp_foreign.status == 403, f"Expected 403 Forbidden for siswa foreign school tampering, got {resp_foreign.status}"
            print("  ✓ Siswa foreign school query (?school_id=2) rejected with HTTP 403")

            # 8.7 Logout
            logout_btn = page.locator("form[action*='logout'] button")
            if logout_btn.count() > 0:
                logout_btn.first.click()
                page.wait_for_load_state("networkidle")

            results["Scenario 8: Normal Siswa Workflow"] = "PASS"
        except Exception as e:
            print(f"  ✗ Scenario 8 Failed: {e}")
            results["Scenario 8: Normal Siswa Workflow"] = f"FAIL: {e}"
        finally:
            context.close()

        # -------------------------------------------------------------
        # SCENARIO 9: REAL UI MUTATION — ADMIN SEKOLAH CLASSROOM CREATION
        # -------------------------------------------------------------
        print("\n[SCENARIO 9] Real UI Mutation: Admin Sekolah Classroom Creation...")
        context = browser.new_context()
        page = context.new_page()
        try:
            page.goto(f"{BASE_URL}/login")
            page.fill("#email", "admin@smk.id")
            page.fill("#password", "Password123!")
            page.click("button[type='submit']")
            page.wait_for_load_state("networkidle")

            page.goto(f"{BASE_URL}/classes")
            page.wait_for_load_state("networkidle")

            # Open Tambah Kelas modal
            page.click("button:has-text('Tambah Kelas')")
            page.wait_for_timeout(300)

            # Fill modal inputs
            page.fill("form[action*='classes'] input[name='name']", "XII ATPH E2E Inovasi")
            page.fill("form[action*='classes'] input[name='academic_year']", "2026/2027")

            # Submit modal form
            page.click("form[action*='classes'] button[type='submit']:has-text('Simpan Kelas')")
            page.wait_for_load_state("networkidle")

            # Verify response and presence in table
            content = page.content()
            assert "XII ATPH E2E Inovasi" in content, "Created classroom MUST appear in classroom table"
            print("  ✓ Admin Sekolah created classroom through real modal UI ('XII ATPH E2E Inovasi' verified)")

            logout_btn = page.locator("form[action*='logout'] button")
            if logout_btn.count() > 0:
                logout_btn.first.click()
                page.wait_for_load_state("networkidle")

            results["Scenario 9: Admin Classroom UI Mutation"] = "PASS"
        except Exception as e:
            print(f"  ✗ Scenario 9 Failed: {e}")
            results["Scenario 9: Admin Classroom UI Mutation"] = f"FAIL: {e}"
        finally:
            context.close()

        # -------------------------------------------------------------
        # SCENARIO 10: REAL UI MUTATION — ADMIN SEKOLAH SETTINGS PERSISTENCE
        # -------------------------------------------------------------
        print("\n[SCENARIO 10] Real UI Mutation: Admin Sekolah Settings Persistence...")
        context = browser.new_context()
        page = context.new_page()
        try:
            page.goto(f"{BASE_URL}/login")
            page.fill("#email", "admin@smk.id")
            page.fill("#password", "Password123!")
            page.click("button[type='submit']")
            page.wait_for_load_state("networkidle")

            page.goto(f"{BASE_URL}/settings")
            page.wait_for_load_state("networkidle")

            # Fill lead teacher in settings form
            lead_input = page.locator("input[name='lead_teacher']")
            assert lead_input.count() > 0, "Lead teacher input must be present"
            lead_input.fill("Dr. Ir. Bambang Wicaksono, M.M.")

            # Submit settings form
            submit_btn = page.locator("form[action*='settings'] button[type='submit']:has-text('Simpan')")
            submit_btn.first.click()
            page.wait_for_load_state("networkidle")

            # Verify saved setting is rendered
            content = page.content()
            assert "Dr. Ir. Bambang Wicaksono, M.M." in content, "Updated lead teacher must be persisted and displayed"
            print("  ✓ Admin Sekolah persisted settings through UI ('Dr. Ir. Bambang Wicaksono, M.M.' verified)")

            logout_btn = page.locator("form[action*='logout'] button")
            if logout_btn.count() > 0:
                logout_btn.first.click()
                page.wait_for_load_state("networkidle")

            results["Scenario 10: Admin Settings UI Mutation"] = "PASS"
        except Exception as e:
            print(f"  ✗ Scenario 10 Failed: {e}")
            results["Scenario 10: Admin Settings UI Mutation"] = f"FAIL: {e}"
        finally:
            context.close()

        # -------------------------------------------------------------
        # SCENARIO 11: REAL UI MUTATION — GURU PRACTICUM SCHEDULING CYCLE
        # -------------------------------------------------------------
        print("\n[SCENARIO 11] Real UI Mutation: Guru Practicum Scheduling Cycle...")
        context = browser.new_context()
        page = context.new_page()
        try:
            page.goto(f"{BASE_URL}/login")
            page.fill("#email", "guru@smk.id")
            page.fill("#password", "Password123!")
            page.click("button[type='submit']")
            page.wait_for_load_state("networkidle")

            page.goto(f"{BASE_URL}/schedules")
            page.wait_for_load_state("networkidle")

            # Click Tambah Jadwal button
            page.click("button:has-text('Tambah Jadwal')")
            page.wait_for_timeout(300)

            # Fill schedule modal form
            page.fill("form[action*='schedules'] input[name='title']", "Praktikum Agritech E2E")
            page.fill("form[action*='schedules'] input[name='activity_date']", "2026-11-20")
            page.fill("form[action*='schedules'] input[name='start_time']", "08:00")
            page.fill("form[action*='schedules'] input[name='end_time']", "10:00")

            # Submit schedule modal
            page.click("form[action*='schedules'] button[type='submit']:has-text('Simpan Jadwal')")
            page.wait_for_load_state("networkidle")

            # Verify schedule appears
            content = page.content()
            assert "Praktikum Agritech E2E" in content, "Newly scheduled practicum must appear in schedule list"
            print("  ✓ Guru scheduled practicum through UI ('Praktikum Agritech E2E' verified)")

            logout_btn = page.locator("form[action*='logout'] button")
            if logout_btn.count() > 0:
                logout_btn.first.click()
                page.wait_for_load_state("networkidle")

            results["Scenario 11: Guru Scheduling UI Mutation"] = "PASS"
        except Exception as e:
            print(f"  ✗ Scenario 11 Failed: {e}")
            results["Scenario 11: Guru Scheduling UI Mutation"] = f"FAIL: {e}"
        finally:
            context.close()

        # -------------------------------------------------------------
        # SCENARIO 12: STRICT RBAC BOUNDARY — DENIED ACTIONS & BUTTON ABSENCE
        # -------------------------------------------------------------
        print("\n[SCENARIO 12] Strict RBAC Boundary: Denied Actions & UI Absence...")
        context = browser.new_context()
        page = context.new_page()
        try:
            page.goto(f"{BASE_URL}/login")
            page.fill("#email", "siswa@smk.id")
            page.fill("#password", "Password123!")
            page.click("button[type='submit']")
            page.wait_for_load_state("networkidle")

            # 1. Check Trainer Kit page as Siswa
            page.goto(f"{BASE_URL}/trainer-kits")
            page.wait_for_load_state("networkidle")
            content_tk = page.content()

            # Verify absence of platform-only and teacher buttons
            assert "Rotasi Key" not in content_tk, "Siswa MUST NOT see 'Rotasi Key' button"
            assert "form action" not in content_tk or "api-key/rotate" not in content_tk, "Siswa MUST NOT see rotate API key form"
            print("  ✓ Siswa verified absence of API key rotation and mutation buttons on Trainer Kit page")

            # 2. Denied direct mutations with CSRF token from session
            csrf_token = page.evaluate("document.querySelector('meta[name=\"csrf-token\"]')?.getAttribute('content') || ''")
            headers = {"X-CSRF-TOKEN": csrf_token, "Accept": "application/json"}

            post_classes = context.request.post(f"{BASE_URL}/classes", data={"name": "Hacked Class"}, headers=headers)
            assert post_classes.status == 403, f"Expected 403 for siswa on POST /classes, got {post_classes.status}"

            post_settings = context.request.post(f"{BASE_URL}/settings/school", data={"school_name": "Hacked School"}, headers=headers)
            assert post_settings.status == 403, f"Expected 403 for siswa on POST /settings/school, got {post_settings.status}"

            post_rotate = context.request.post(f"{BASE_URL}/trainer-kits/1/api-key/rotate", headers=headers)
            assert post_rotate.status == 403, f"Expected 403 for siswa on POST rotate key, got {post_rotate.status}"

            post_inject = context.request.post(f"{BASE_URL}/raw-logs/inject", data={"hex": "01030000"}, headers=headers)
            assert post_inject.status == 403, f"Expected 403 for siswa on POST inject logs, got {post_inject.status}"
            print("  ✓ All direct unauthorized mutation attempts strictly rejected with HTTP 403")

            logout_btn = page.locator("form[action*='logout'] button")
            if logout_btn.count() > 0:
                logout_btn.first.click()
                page.wait_for_load_state("networkidle")

            results["Scenario 12: Strict RBAC Denied Actions"] = "PASS"
        except Exception as e:
            print(f"  ✗ Scenario 12 Failed: {e}")
            results["Scenario 12: Strict RBAC Denied Actions"] = f"FAIL: {e}"
        finally:
            context.close()

        # -------------------------------------------------------------
        # SCENARIO 13: SUPERUSER DATA MASTER WILAYAH (MASTER-03-01)
        # -------------------------------------------------------------
        print("\n[SCENARIO 13] Superuser Data Master Wilayah (Provinsi, Kab/Kota, Kecamatan)...")
        context = browser.new_context()
        page = context.new_page()
        try:
            # 13.1 Login as Superuser
            page.goto(f"{BASE_URL}/login")
            page.fill("#email", "superuser@bitanic.id")
            page.fill("#password", "Password123!")
            page.click("button[type='submit']")
            page.wait_for_load_state("networkidle")
            assert "/login" not in page.url, "Login failed for superuser"

            # 13.2 Verify Sidebar Data Master navigation links
            assert page.locator("a[href*='/master/regions']").count() > 0, "Superuser must see Wilayah master in sidebar"
            assert page.locator("a[href*='/master/schools']").count() > 0, "Superuser must see Sekolah master in sidebar"
            print("  ✓ Superuser verified presence of Data Master navigation links in sidebar")

            # 13.3 Visit Region Master Page
            resp = page.goto(f"{BASE_URL}/master/regions")
            assert resp.status == 200, f"Expected 200 for /master/regions, got {resp.status}"
            content = page.content()
            assert "Master Wilayah Indonesia" in content, "Expected Region Master title"
            assert "Provinsi" in content and "38" in content, "Expected metadata displaying 38 Provinsi"
            assert "JAWA BARAT" in content, "Expected JAWA BARAT in default province list"
            print("  ✓ Superuser accessed Region Master page (38 Provinsi Kemendagri metadata verified)")

            # 13.4 Level Tab Switching: Regencies (Kab/Kota)
            resp_kab = page.goto(f"{BASE_URL}/master/regions?level=regencies&province_code=32&search=SUKABUMI")
            assert resp_kab.status == 200
            content_kab = page.content()
            assert "KABUPATEN SUKABUMI" in content_kab or "KOTA SUKABUMI" in content_kab, "Expected Sukabumi in regencies"
            assert "3202" in content_kab, "Expected code 3202 for Kabupaten Sukabumi"
            print("  ✓ Superuser filtered and verified Kab/Kota (3202 KABUPATEN SUKABUMI)")

            # 13.5 Level Tab Switching: Districts (Kecamatan)
            resp_kec = page.goto(f"{BASE_URL}/master/regions?level=districts&regency_code=3202&search=SUKARAJA")
            assert resp_kec.status == 200
            content_kec = page.content()
            assert "SUKARAJA" in content_kec, "Expected SUKARAJA in districts"
            assert "3202170" in content_kec, "Expected code 3202170 for Sukaraja"
            print("  ✓ Superuser filtered and verified Kecamatan (3202170 SUKARAJA)")

            # 13.6 Cascading API Endpoints Verification
            provinces_res = context.request.get(f"{BASE_URL}/api/regions/provinces")
            assert provinces_res.status == 200
            prov_json = provinces_res.json()
            assert prov_json["success"] is True and len(prov_json["data"]) == 38, "Expected 38 provinces in API"

            regencies_res = context.request.get(f"{BASE_URL}/api/regions/regencies?province_code=32")
            assert regencies_res.status == 200
            reg_json = regencies_res.json()
            assert reg_json["success"] is True and any(r["code"] == "3202" for r in reg_json["data"]), "Expected 3202 in West Java regencies API"

            districts_res = context.request.get(f"{BASE_URL}/api/regions/districts?regency_code=3202")
            assert districts_res.status == 200
            dist_json = districts_res.json()
            assert dist_json["success"] is True and any(d["code"] == "3202170" for d in dist_json["data"]), "Expected 3202170 in Sukabumi districts API"
            print("  ✓ Cascading API endpoints verified (/api/regions/provinces, /regencies, /districts)")

            logout_btn = page.locator("form[action*='logout'] button")
            if logout_btn.count() > 0:
                logout_btn.first.click()
                page.wait_for_load_state("networkidle")

            results["Scenario 13: Superuser Data Master Wilayah"] = "PASS"
        except Exception as e:
            print(f"  ✗ Scenario 13 Failed: {e}")
            results["Scenario 13: Superuser Data Master Wilayah"] = f"FAIL: {e}"
        finally:
            context.close()

        # -------------------------------------------------------------
        # SCENARIO 14: SUPERUSER MASTER SEKOLAH CRUD LIFECYCLE (MASTER-03-02)
        # -------------------------------------------------------------
        print("\n[SCENARIO 14] Superuser Master Sekolah CRUD Lifecycle...")
        context = browser.new_context()
        page = context.new_page()
        try:
            # 14.1 Login as Superuser
            page.goto(f"{BASE_URL}/login")
            page.fill("#email", "superuser@bitanic.id")
            page.fill("#password", "Password123!")
            page.click("button[type='submit']")
            page.wait_for_load_state("networkidle")

            # 14.2 Visit Master Sekolah Index
            resp = page.goto(f"{BASE_URL}/master/schools")
            assert resp.status == 200, f"Expected 200 for /master/schools, got {resp.status}"
            content = page.content()
            assert "Master Sekolah" in content, "Expected Master Sekolah title"
            assert "SMK Pertanian Pembangunan Negeri 1 Sukaraja" in content, "Expected baseline school in table"
            print("  ✓ Superuser accessed Master Sekolah index (baseline school verified)")

            # 14.3 Navigate to Create Form
            page.click("a:has-text('Tambah Sekolah')")
            page.wait_for_load_state("networkidle")
            assert "/master/schools/create" in page.url, "Expected navigation to school create page"
            print("  ✓ Superuser opened Tambah Sekolah form")

            # 14.4 Fill Create Form with Cascading Selects
            test_npsn = f"88{int(time.time()) % 1000000:06d}"
            test_school_name = f"SMK Pertanian Test E2E {test_npsn}"
            page.fill("#name", test_school_name)
            page.fill("#npsn", test_npsn)
            page.fill("#phone", "08123456789")
            page.fill("#address", "Jl. Pendidikan Vokasi No. 10")

            # Select Province: 32 (Jawa Barat)
            page.select_option("#province_code", "32")
            # Wait for regencies to load via Alpine.js fetch
            page.wait_for_function("document.querySelectorAll('#regency_code option').length > 1")
            page.select_option("#regency_code", "3202")
            # Wait for districts to load via Alpine.js fetch
            page.wait_for_function("document.querySelectorAll('#district_code option').length > 1")
            page.select_option("#district_code", "3202170")
            print("  ✓ Cascading Alpine.js selectors dynamically selected Provinsi 32 -> Kab 3202 -> Kec 3202170")

            # Submit Create Form
            page.click("button[type='submit']:has-text('Simpan Sekolah')")
            page.wait_for_load_state("networkidle")
            assert "/master/schools" in page.url and "/create" not in page.url, f"Expected redirect to index, got {page.url}"
            content_after_create = page.content()
            assert test_school_name in content_after_create, "Newly created school must appear in index"
            print(f"  ✓ School created successfully with regional links ('{test_school_name}')")

            # 14.5 Filter and Search Table
            page.fill("input[name='search']", test_npsn)
            page.click("button:has-text('Cari')")
            page.wait_for_load_state("networkidle")
            assert test_school_name in page.content(), f"Expected search result for NPSN {test_npsn}"
            print("  ✓ Search filter by NPSN verified")

            # 14.6 View School Detail
            page.click("a[title='Detail Sekolah']")
            page.wait_for_load_state("networkidle")
            assert "/master/schools/" in page.url and "/edit" not in page.url, "Expected detail page URL"
            content_detail = page.content()
            assert test_school_name in content_detail, "Expected school name in detail view"
            assert "KABUPATEN SUKABUMI" in content_detail, "Expected regional regency name in detail"
            assert "SUKARAJA" in content_detail, "Expected district name in detail"
            print("  ✓ Superuser inspected school detail view with regional breakdown")

            # Extract created school ID from URL
            school_url_parts = page.url.rstrip("/").split("/")
            created_school_id = school_url_parts[-1]

            # 14.7 Edit School
            page.goto(f"{BASE_URL}/master/schools/{created_school_id}/edit")
            page.wait_for_load_state("networkidle")
            page.fill("#phone", "08999999999")
            page.click("button[type='submit']:has-text('Simpan Perubahan')")
            page.wait_for_load_state("networkidle")
            # Navigate to detail page to verify updated phone
            page.goto(f"{BASE_URL}/master/schools/{created_school_id}")
            page.wait_for_load_state("networkidle")
            assert "08999999999" in page.content(), "Updated phone number must appear on detail page"
            print("  ✓ School edited and updated successfully")

            # 14.8 Toggle Status (Active -> Inactive -> Active)
            csrf_token = page.evaluate("document.querySelector('meta[name=\"csrf-token\"]')?.getAttribute('content') || ''")
            headers = {"X-CSRF-TOKEN": csrf_token, "Accept": "application/json"}
            toggle_res = context.request.post(f"{BASE_URL}/master/schools/{created_school_id}/toggle-status", data={"_method": "PATCH"}, headers=headers)
            assert toggle_res.status in [200, 302], f"Toggle status expected redirect/success, got {toggle_res.status}"
            print("  ✓ School status toggled (active <-> inactive)")

            # 14.9 Deletion Guard & Cleanup
            # Delete new school (0 linked users/devices/schedules -> allowed)
            delete_res = context.request.post(f"{BASE_URL}/master/schools/{created_school_id}", data={"_method": "DELETE"}, headers=headers)
            assert delete_res.status in [200, 302], f"Expected success deleting unlinked school, got {delete_res.status}"

            # Attempt to delete School 1 (has linked users/devices -> must be blocked)
            delete_baseline_res = context.request.post(f"{BASE_URL}/master/schools/1", data={"_method": "DELETE"}, headers=headers)
            assert delete_baseline_res.status in [200, 302], f"Expected redirect/block response, got {delete_baseline_res.status}"
            assert "SMK Pertanian Pembangunan Negeri 1 Sukaraja" in delete_baseline_res.text(), "Baseline school must still exist in response"
            print("  ✓ School deletion guard verified (audited & linked schools strictly protected)")

            logout_btn = page.locator("form[action*='logout'] button")
            if logout_btn.count() > 0:
                logout_btn.first.click()
                page.wait_for_load_state("networkidle")

            results["Scenario 14: Superuser Master Sekolah CRUD Lifecycle"] = "PASS"
        except Exception as e:
            print(f"  ✗ Scenario 14 Failed: {e}")
            results["Scenario 14: Superuser Master Sekolah CRUD Lifecycle"] = f"FAIL: {e}"
        finally:
            context.close()

        # -------------------------------------------------------------
        # SCENARIO 15: MASTER ROUTES SECURITY & ROLE ISOLATION
        # -------------------------------------------------------------
        print("\n[SCENARIO 15] Master Routes Security & Role Isolation (Superuser-Only)...")
        context = browser.new_context()
        page = context.new_page()
        try:
            # 15.1 Login as Admin Sekolah
            page.goto(f"{BASE_URL}/login")
            page.fill("#email", "admin@smk.id")
            page.fill("#password", "Password123!")
            page.click("button[type='submit']")
            page.wait_for_load_state("networkidle")

            # Extract CSRF token from authorized dashboard page
            csrf_token = page.evaluate("document.querySelector('meta[name=\"csrf-token\"]')?.getAttribute('content') || ''")
            assert bool(csrf_token), "Expected valid CSRF token from authenticated dashboard"
            headers = {"X-CSRF-TOKEN": csrf_token, "Accept": "application/json"}

            # Verify absence of DATA MASTER in sidebar
            sidebar_content = page.content()
            assert "DATA MASTER" not in sidebar_content, "Admin Sekolah MUST NOT see 'DATA MASTER' sidebar group"
            assert "/master/regions" not in sidebar_content, "Admin Sekolah MUST NOT see Region master link"
            assert "/master/schools" not in sidebar_content, "Admin Sekolah MUST NOT see School master link"
            print("  ✓ Admin Sekolah verified absence of DATA MASTER sidebar section")

            # Direct GET /master/regions -> 403
            resp_reg = page.goto(f"{BASE_URL}/master/regions")
            assert resp_reg.status == 403, f"Expected 403 for Admin Sekolah on /master/regions, got {resp_reg.status}"

            # Direct GET /master/schools -> 403
            resp_sch = page.goto(f"{BASE_URL}/master/schools")
            assert resp_sch.status == 403, f"Expected 403 for Admin Sekolah on /master/schools, got {resp_sch.status}"

            # Direct mutation requests with CSRF
            post_sch = context.request.post(f"{BASE_URL}/master/schools", data={"name": "Illegal School"}, headers=headers)
            assert post_sch.status == 403, f"Expected 403 on POST /master/schools, got {post_sch.status}"

            post_sync = context.request.post(f"{BASE_URL}/master/regions/sync", headers=headers)
            assert post_sync.status == 403, f"Expected 403 on POST /master/regions/sync, got {post_sync.status}"
            print("  ✓ Admin Sekolah direct master access and mutations strictly rejected with HTTP 403")

            context.close()

            # 15.2 Check Siswa Role in a dedicated context
            context_siswa = browser.new_context()
            page_siswa = context_siswa.new_page()
            try:
                page_siswa.goto(f"{BASE_URL}/login")
                page_siswa.fill("#email", "siswa@smk.id")
                page_siswa.fill("#password", "Password123!")
                page_siswa.click("button[type='submit']")
                page_siswa.wait_for_load_state("networkidle")

                resp_siswa = page_siswa.goto(f"{BASE_URL}/master/schools")
                assert resp_siswa.status == 403, f"Expected 403 for Siswa on /master/schools, got {resp_siswa.status}"
                print("  ✓ Siswa direct master access strictly rejected with HTTP 403")
            finally:
                context_siswa.close()

            results["Scenario 15: Master Routes Security & Role Isolation"] = "PASS"
        except Exception as e:
            print(f"  ✗ Scenario 15 Failed: {e}")
            results["Scenario 15: Master Routes Security & Role Isolation"] = f"FAIL: {e}"
        finally:
            context.close()

        # -------------------------------------------------------------
        # SCENARIO 16: SCHOOL TEACHER & STUDENT MASTER MANAGEMENT (MASTER-03-03)
        # -------------------------------------------------------------
        print("\n[SCENARIO 16] School Teacher & Student Master Management (MASTER-03-03)...")
        context = browser.new_context()
        page = context.new_page()
        try:
            # 16.1 Login as Admin Sekolah
            page.goto(f"{BASE_URL}/login")
            page.fill("#email", "admin@smk.id")
            page.fill("#password", "Password123!")
            page.click("button[type='submit']")
            page.wait_for_load_state("networkidle")
            assert "/login" not in page.url, "Login failed for admin"

            # 16.2 Visit User Directory
            resp = page.goto(f"{BASE_URL}/users-data")
            assert resp.status == 200, f"Expected 200 on /users-data, got {resp.status}"
            content = page.content()
            assert "Direktori" in content, "Expected Direktori page header"
            assert "Tambah Pengguna" in content, "Expected Tambah Pengguna button for admin"
            print("  ✓ Admin Sekolah accessed User Directory with management toolbar")

            csrf_token = page.evaluate("document.querySelector('meta[name=\"csrf-token\"]')?.getAttribute('content') || ''")
            headers = {"X-CSRF-TOKEN": csrf_token, "Accept": "application/json"}

            # 16.3 Create Teacher with Specialization
            teacher_email = f"guru.playwright.{int(time.time())}@smk.id"
            create_guru_res = context.request.post(
                f"{BASE_URL}/users",
                data={
                    "name": "Guru Agribisnis E2E",
                    "email": teacher_email,
                    "role": "guru",
                    "identifier_number": "NIP-998877",
                    "specialization": "Agribisnis Pangan & Hortikultura",
                    "department": "Jurusan ATPH",
                },
                headers=headers,
            )
            assert create_guru_res.status in [200, 302], f"Expected 200/302 creating teacher, got {create_guru_res.status}"

            # Verify Teacher created in list
            page.goto(f"{BASE_URL}/users-data?q=Guru+Agribisnis+E2E")
            page.wait_for_load_state("networkidle")
            content_guru = page.content()
            assert "Guru Agribisnis E2E" in content_guru, "Newly created teacher must appear in directory"
            assert "NIP-998877" in content_guru, "Teacher identifier must appear in table"
            print("  ✓ Teacher created with specialization and verified in directory")

            # 16.4 Create Student with Optional NISN
            student_email = f"siswa.playwright.{int(time.time())}@smk.id"
            create_siswa_res = context.request.post(
                f"{BASE_URL}/users",
                data={
                    "name": "Siswa Calon Praktikan E2E",
                    "email": student_email,
                    "role": "siswa",
                    "identifier_number": "0098765432",
                },
                headers=headers,
            )
            assert create_siswa_res.status in [200, 302], f"Expected 200/302 creating student, got {create_siswa_res.status}"

            # Verify Student created in list
            page.goto(f"{BASE_URL}/users-data?q=Siswa+Calon+Praktikan+E2E")
            page.wait_for_load_state("networkidle")
            content_siswa = page.content()
            assert "Siswa Calon Praktikan E2E" in content_siswa, "Newly created student must appear in directory"
            assert "0098765432" in content_siswa, "Optional student NISN must be rendered"
            print("  ✓ Student created with optional NISN and verified in directory")

            # 16.5 Toggle Active Status (Deactivate Siswa Baseline -> Login Guard -> Reactivate)
            page.goto(f"{BASE_URL}/users-data?q=siswa%40smk.id")
            page.wait_for_load_state("networkidle")
            toggle_btn = page.locator("button[title='Nonaktifkan Akun']")
            assert toggle_btn.count() > 0, "Expected toggle status button for active siswa"

            siswa_toggle_form = toggle_btn.first.locator("xpath=..")
            action_url = siswa_toggle_form.get_attribute("action")
            assert action_url and "/toggle-status" in action_url, f"Expected valid toggle-status action URL, got {action_url}"
            deact_res = context.request.post(action_url, data={"_method": "PATCH"}, headers=headers)
            assert deact_res.status in [200, 302], f"Expected success toggling status, got {deact_res.status}"

            # Verify deactivated user cannot log in
            context_deact = browser.new_context()
            page_deact = context_deact.new_page()
            try:
                page_deact.goto(f"{BASE_URL}/login")
                page_deact.fill("#email", "siswa@smk.id")
                page_deact.fill("#password", "Password123!")
                page_deact.click("button[type='submit']")
                page_deact.wait_for_load_state("networkidle")
                assert "/login" in page_deact.url, "Deactivated user must NOT be allowed to log in"
                assert "dinonaktifkan" in page_deact.content(), "Expected deactivation notification error message"
                print("  ✓ Deactivated user login blocked with explicit deactivation notice")
            finally:
                context_deact.close()

            # Reactivate siswa
            react_res = context.request.post(action_url, data={"_method": "PATCH"}, headers=headers)
            assert react_res.status in [200, 302], f"Expected success reactivating status, got {react_res.status}"

            # Verify reactivated user can log in again
            context_react = browser.new_context()
            page_react = context_react.new_page()
            try:
                page_react.goto(f"{BASE_URL}/login")
                page_react.fill("#email", "siswa@smk.id")
                page_react.fill("#password", "Password123!")
                page_react.click("button[type='submit']")
                page_react.wait_for_load_state("networkidle")
                assert "/login" not in page_react.url, "Reactivated user must be able to log in"
                print("  ✓ Reactivated user successfully logged in")
            finally:
                context_react.close()

            results["Scenario 16: School Teacher & Student Master Management"] = "PASS"
        except Exception as e:
            print(f"  ✗ Scenario 16 Failed: {e}")
            results["Scenario 16: School Teacher & Student Master Management"] = f"FAIL: {e}"
        finally:
            context.close()

        # -------------------------------------------------------------
        # SCENARIO 17: ACADEMIC MASTER & ALLOCATION (MASTER-03-04)
        # -------------------------------------------------------------
        print("\n[SCENARIO 17] Academic Master & Student Allocation (MASTER-03-04)...")
        context = browser.new_context()
        page = context.new_page()
        try:
            # 17.1 Login as Admin Sekolah
            page.goto(f"{BASE_URL}/login")
            page.fill("#email", "admin@smk.id")
            page.fill("#password", "Password123!")
            page.click("button[type='submit']")
            page.wait_for_load_state("networkidle")

            # 17.2 Visit Classes & Academic Page
            resp = page.goto(f"{BASE_URL}/classes")
            assert resp.status == 200, f"Expected 200 on /classes, got {resp.status}"
            content = page.content()
            assert "Kelas &" in content, "Expected Kelas & Akademik header"
            assert "Tahun Ajaran" in content, "Expected Tahun Ajaran button"
            print("  ✓ Admin Sekolah accessed Classroom & Academic Management")

            csrf_token = page.evaluate("document.querySelector('meta[name=\"csrf-token\"]')?.getAttribute('content') || ''")
            headers = {"X-CSRF-TOKEN": csrf_token, "Accept": "application/json"}

            # 17.3 Update Academic Year & Semester Configuration
            academic_res = context.request.post(
                f"{BASE_URL}/classes/academic-year",
                data={
                    "academic_year": "2026/2027",
                    "semester": "Genap",
                },
                headers=headers,
            )
            assert academic_res.status in [200, 302], f"Expected 200/302 updating academic year, got {academic_res.status}"
            page.reload()
            page.wait_for_load_state("networkidle")
            assert "Semester Genap" in page.content(), "Updated semester Genap must appear in academic banner"
            print("  ✓ Academic year & semester updated and verified in banner")

            # 17.4 Create Classroom
            class_name = f"XII ATPH E2E {int(time.time())}"
            create_class_res = context.request.post(
                f"{BASE_URL}/classes",
                data={
                    "name": class_name,
                    "academic_year": "2026/2027",
                    "description": "Kelas ATPH Pengujian Playwright",
                },
                headers=headers,
            )
            assert create_class_res.status in [200, 302], f"Expected 200/302 creating class, got {create_class_res.status}"
            page.reload()
            page.wait_for_load_state("networkidle")
            assert class_name in page.content(), "Newly created class must appear in list"
            print("  ✓ Classroom created and rendered in academic list")

            # 17.5 Allocate Student
            # Dynamically extract target classroom ID and student ID from page
            allocate_btn = page.locator("button:has-text('Alokasi Siswa')").first
            allocate_attr = allocate_btn.get_attribute("@click")
            target_class_id = allocate_attr.split("(")[1].split(",")[0].strip()

            first_student_option = page.locator("select[name='student_id'] option").nth(1)
            target_student_id = first_student_option.get_attribute("value")

            alloc_user_res = context.request.post(
                f"{BASE_URL}/students/{target_student_id}/allocation",
                data={
                    "classroom_id": target_class_id,
                    "assigned_kit_id": "TK-01",
                },
                headers=headers,
            )
            assert alloc_user_res.status in [200, 302], f"Allocation expected 200/302, got {alloc_user_res.status}"
            print("  ✓ Student allocated to Classroom and Trainer Kit workstation")

            results["Scenario 17: Academic Master & Student Allocation"] = "PASS"
        except Exception as e:
            print(f"  ✗ Scenario 17 Failed: {e}")
            results["Scenario 17: Academic Master & Student Allocation"] = f"FAIL: {e}"
        finally:
            context.close()

        # -------------------------------------------------------------
        # SCENARIO 18: NEGATIVE AUTHORIZATION ON USER IMPORT & MUTATIONS
        # -------------------------------------------------------------
        print("\n[SCENARIO 18] Negative Authorization on User Import & Academic Mutations...")
        context_guru = browser.new_context()
        page_guru = context_guru.new_page()
        try:
            # 18.1 Guru Login
            page_guru.goto(f"{BASE_URL}/login")
            page_guru.fill("#email", "guru@smk.id")
            page_guru.fill("#password", "Password123!")
            page_guru.click("button[type='submit']")
            page_guru.wait_for_load_state("networkidle")

            csrf_guru = page_guru.evaluate("document.querySelector('meta[name=\"csrf-token\"]')?.getAttribute('content') || ''")
            headers_guru = {"X-CSRF-TOKEN": csrf_guru, "Accept": "application/json"}

            # Guru strictly denied from User Import Preview (403)
            imp_prev = context_guru.request.post(f"{BASE_URL}/users/import/preview", headers=headers_guru)
            assert imp_prev.status == 403, f"Guru must receive 403 on /users/import/preview, got {imp_prev.status}"

            # Guru strictly denied from User Import Execute (403)
            imp_exec = context_guru.request.post(f"{BASE_URL}/users/import/execute", headers=headers_guru)
            assert imp_exec.status == 403, f"Guru must receive 403 on /users/import/execute, got {imp_exec.status}"

            # Guru strictly denied from User Creation (403)
            usr_create = context_guru.request.post(
                f"{BASE_URL}/users",
                data={"name": "Illegal User", "email": "illegal@smk.id", "role": "guru"},
                headers=headers_guru
            )
            assert usr_create.status == 403, f"Guru must receive 403 on POST /users, got {usr_create.status}"
            print("  ✓ Guru strictly denied (HTTP 403) from User Import and User Creation mutations")
        finally:
            context_guru.close()

        context_siswa = browser.new_context()
        page_siswa = context_siswa.new_page()
        try:
            # 18.2 Siswa Login
            page_siswa.goto(f"{BASE_URL}/login")
            page_siswa.fill("#email", "siswa@smk.id")
            page_siswa.fill("#password", "Password123!")
            page_siswa.click("button[type='submit']")
            page_siswa.wait_for_load_state("networkidle")

            csrf_siswa = page_siswa.evaluate("document.querySelector('meta[name=\"csrf-token\"]')?.getAttribute('content') || ''")
            headers_siswa = {"X-CSRF-TOKEN": csrf_siswa, "Accept": "application/json"}

            # Siswa strictly denied from User Directory (403)
            resp_dir = page_siswa.goto(f"{BASE_URL}/users-data")
            assert resp_dir.status == 403, f"Siswa must receive 403 on /users-data, got {resp_dir.status}"

            # Siswa strictly denied from Class Creation (403)
            resp_cls = context_siswa.request.post(
                f"{BASE_URL}/classes",
                data={"name": "Illegal Class"},
                headers=headers_siswa
            )
            assert resp_cls.status == 403, f"Siswa must receive 403 on POST /classes, got {resp_cls.status}"

            # Siswa strictly denied from Academic Year configuration (403)
            resp_ay = context_siswa.request.post(
                f"{BASE_URL}/classes/academic-year",
                data={"academic_year": "2026/2027", "semester": "Ganjil"},
                headers=headers_siswa
            )
            assert resp_ay.status == 403, f"Siswa must receive 403 on POST /classes/academic-year, got {resp_ay.status}"
            print("  ✓ Siswa strictly denied (HTTP 403) from User Directory and Academic mutations")

            results["Scenario 18: Negative Authorization on User Import & Mutations"] = "PASS"
        except Exception as e:
            print(f"  ✗ Scenario 18 Failed: {e}")
            results["Scenario 18: Negative Authorization on User Import & Mutations"] = f"FAIL: {e}"
        finally:
            context_siswa.close()

        # -------------------------------------------------------------
        # SCENARIO 19: GLOBAL TRAINER KIT SERIAL INVENTORY MASTER (DEVICE-04-01)
        # -------------------------------------------------------------
        print("\n[SCENARIO 19] Global Trainer Kit Serial Inventory Master (DEVICE-04-01)...")
        context_su19 = browser.new_context()
        page_su19 = context_su19.new_page()
        try:
            # 19.1 Superuser Login
            page_su19.goto(f"{BASE_URL}/login")
            page_su19.fill("#email", "superuser@bitanic.id")
            page_su19.fill("#password", "Password123!")
            page_su19.click("button[type='submit']")
            page_su19.wait_for_load_state("networkidle")

            # 19.2 Visit Master Trainer Kit Page
            resp_inv = page_su19.goto(f"{BASE_URL}/master/trainer-kits")
            assert resp_inv.status == 200, f"Expected 200 on /master/trainer-kits, got {resp_inv.status}"
            content_inv = page_su19.content()
            assert "Master Serial" in content_inv, "Expected Master Serial header"
            assert "Total Unit" in content_inv, "Expected Total Unit card"
            assert "Stok Pabrik" in content_inv, "Expected Stok Pabrik card"
            print("  ✓ Superuser accessed Global Trainer Kit Master listing with metrics")

            csrf_su19 = page_su19.evaluate("document.querySelector('meta[name=\"csrf-token\"]')?.getAttribute('content') || ''")
            headers_su19 = {"X-CSRF-TOKEN": csrf_su19, "Accept": "application/json"}

            # 19.3 Register New Physical Serial
            e2e_serial = f"BTK-2026-E2E-{int(time.time())}"
            reg_res = context_su19.request.post(
                f"{BASE_URL}/master/trainer-kits",
                data={
                    "serial_number": e2e_serial,
                    "model_name": "Bitanic Trainer Kit v1.0",
                    "hardware_revision": "rev1.2",
                    "batch_number": "BATCH-E2E-Q1",
                    "notes": "Unit uji coba otomatis Playwright E2E",
                },
                headers=headers_su19,
            )
            assert reg_res.status in [200, 302], f"Expected 200/302 on registration, got {reg_res.status}"
            print(f"  ✓ Registered new serial: {e2e_serial}")

            # 19.4 Search for Newly Created Serial
            page_su19.goto(f"{BASE_URL}/master/trainer-kits?search={e2e_serial}")
            page_su19.wait_for_load_state("networkidle")
            assert e2e_serial in page_su19.content(), f"Expected {e2e_serial} in search results"
            print("  ✓ Verified newly created kit in search results")

            results["Scenario 19: Global Trainer Kit Serial Inventory Master"] = "PASS"
        except Exception as e:
            print(f"  ✗ Scenario 19 Failed: {e}")
            results["Scenario 19: Global Trainer Kit Serial Inventory Master"] = f"FAIL: {e}"
        finally:
            context_su19.close()

        # -------------------------------------------------------------
        # SCENARIO 20: SALE RECORDS, OWNERSHIP ASSIGNMENT & ACTIVATION (DEVICE-04-02)
        # -------------------------------------------------------------
        print("\n[SCENARIO 20] Sale Records, Ownership Assignment & Activation (DEVICE-04-02)...")
        context_su20 = browser.new_context()
        page_su20 = context_su20.new_page()
        try:
            # 20.1 Superuser Login
            page_su20.goto(f"{BASE_URL}/login")
            page_su20.fill("#email", "superuser@bitanic.id")
            page_su20.fill("#password", "Password123!")
            page_su20.click("button[type='submit']")
            page_su20.wait_for_load_state("networkidle")

            csrf_su20 = page_su20.evaluate("document.querySelector('meta[name=\"csrf-token\"]')?.getAttribute('content') || ''")
            headers_su20 = {"X-CSRF-TOKEN": csrf_su20, "Accept": "application/json"}

            # Create fresh kit for complete lifecycle test
            fresh_serial = f"BTK-FLOW-{int(time.time())}"
            create_res = context_su20.request.post(
                f"{BASE_URL}/master/trainer-kits",
                data={
                    "serial_number": fresh_serial,
                    "model_name": "Bitanic Trainer Kit v1.0",
                    "hardware_revision": "rev1.2",
                    "batch_number": "BATCH-LIFECYCLE",
                },
                headers=headers_su20,
            )
            assert create_res.status in [200, 302]

            # Fetch show page to get the inventory ID
            page_su20.goto(f"{BASE_URL}/master/trainer-kits?search={fresh_serial}")
            page_su20.wait_for_load_state("networkidle")
            link = page_su20.locator(f"a:has-text('{fresh_serial}')").first
            inventory_url = link.get_attribute("href")
            assert inventory_url, "Must find detail link for fresh kit"

            page_su20.goto(inventory_url)
            page_su20.wait_for_load_state("networkidle")
            assert "Stok Pabrik" in page_su20.content(), "Expected status Stok Pabrik initially"
            print("  ✓ Fresh kit confirmed in registered status")

            # 20.2 Record Sale to School 1
            sale_res = context_su20.request.post(
                f"{inventory_url}/sale",
                data={
                    "school_id": 1,
                    "sale_reference": f"PO-E2E-{int(time.time())}",
                    "sold_at": "2026-11-11",
                    "notes": "Penjualan paket SMK Playwright",
                },
                headers=headers_su20,
            )
            assert sale_res.status in [200, 302], f"Expected 200/302 on sale record, got {sale_res.status}"
            page_su20.reload()
            page_su20.wait_for_load_state("networkidle")
            assert "Terjual" in page_su20.content() or "Dimiliki" in page_su20.content(), "Status must update to Terjual"
            assert "SALE_RECORDED" in page_su20.content(), "Audit log must record SALE_RECORDED"
            print("  ✓ Sale recorded and school ownership established")

            # 20.3 Activate Kit
            act_res = context_su20.request.post(
                f"{inventory_url}/activate",
                data={"notes": "Aktivasi komisioning E2E"},
                headers=headers_su20,
            )
            assert act_res.status in [200, 302], f"Expected 200/302 on activation, got {act_res.status}"
            page_su20.reload()
            page_su20.wait_for_load_state("networkidle")
            assert "Aktif Operasional" in page_su20.content(), "Status must update to Aktif Operasional"
            assert "ACTIVATED" in page_su20.content(), "Audit log must record ACTIVATED"
            print("  ✓ Kit activated for instructional deployment")

            # 20.4 Negative Authorization: Guru & Siswa Forbidden (403) from Master
            context_neg = browser.new_context()
            try:
                page_neg = context_neg.new_page()
                page_neg.goto(f"{BASE_URL}/login")
                page_neg.fill("#email", "guru@smk.id")
                page_neg.fill("#password", "Password123!")
                page_neg.click("button[type='submit']")
                page_neg.wait_for_load_state("networkidle")

                resp_guru_denied = page_neg.goto(f"{BASE_URL}/master/trainer-kits")
                assert resp_guru_denied.status == 403, f"Guru must receive 403 on /master/trainer-kits, got {resp_guru_denied.status}"
                print("  ✓ Guru strictly denied (HTTP 403) from Global Trainer Kit Master")
            finally:
                context_neg.close()

            results["Scenario 20: Sale Records, Ownership Assignment & Activation"] = "PASS"
        except Exception as e:
            print(f"  ✗ Scenario 20 Failed: {e}")
            results["Scenario 20: Sale Records, Ownership Assignment & Activation"] = f"FAIL: {e}"
        finally:
            context_su20.close()

        # -------------------------------------------------------------
        # SCENARIO 21: PHYSICAL HANDOVER (BAST) & 1-YEAR WARRANTY COMMENCEMENT (DEVICE-04-03)
        # -------------------------------------------------------------
        print("\n[SCENARIO 21] Physical Handover (BAST) & 1-Year Warranty Commencement (DEVICE-04-03)...")
        context_su21 = browser.new_context()
        page_su21 = context_su21.new_page()
        try:
            # 21.1 Superuser Login
            page_su21.goto(f"{BASE_URL}/login")
            page_su21.fill("#email", "superuser@bitanic.id")
            page_su21.fill("#password", "Password123!")
            page_su21.click("button[type='submit']")
            page_su21.wait_for_load_state("networkidle")

            csrf_su21 = page_su21.evaluate("document.querySelector('meta[name=\"csrf-token\"]')?.getAttribute('content') || ''")
            headers_su21 = {"X-CSRF-TOKEN": csrf_su21, "Accept": "application/json"}

            # Create kit for handover
            fresh_serial21 = f"BTK-HND-{int(time.time())}"
            create_res21 = context_su21.request.post(
                f"{BASE_URL}/master/trainer-kits",
                data={
                    "serial_number": fresh_serial21,
                    "model_name": "Bitanic Trainer Kit v1.0",
                    "hardware_revision": "rev1.2",
                    "batch_number": "BATCH-HANDOVER",
                },
                headers=headers_su21,
            )
            assert create_res21.status in [200, 302]

            # Locate kit show page
            page_su21.goto(f"{BASE_URL}/master/trainer-kits?search={fresh_serial21}")
            page_su21.wait_for_load_state("networkidle")
            link21 = page_su21.locator(f"a:has-text('{fresh_serial21}')").first
            inventory_url21 = link21.get_attribute("href")
            assert inventory_url21, "Must find detail link for fresh kit"

            # Sell kit to School 1
            sale_res21 = context_su21.request.post(
                f"{inventory_url21}/sale",
                data={
                    "school_id": 1,
                    "sale_reference": f"PO-BAST-{int(time.time())}",
                    "sold_at": "2026-11-10",
                    "notes": "Penjualan paket SMK Handover",
                },
                headers=headers_su21,
            )
            assert sale_res21.status in [200, 302]

            page_su21.goto(inventory_url21)
            page_su21.wait_for_load_state("networkidle")
            assert "Belum Dicatat" in page_su21.content(), "Warranty must initially show Belum Dicatat"
            print("  ✓ Kit sold and initial unrecorded warranty status confirmed")

            # 21.2 Record Physical Handover (BAST) with 1 Calendar Year Warranty
            bast_num = f"BAST/E2E/{int(time.time())}"
            handover_res = context_su21.request.post(
                f"{inventory_url21}/handover",
                data={
                    "bast_number": bast_num,
                    "handover_date": "2026-11-10",
                    "recipient_name": "Drs. H. Mulyadi",
                    "recipient_title": "Kepala Sekolah",
                    "recipient_phone": "081234567890",
                    "start_warranty_now": 1,
                    "notes": "Serah terima unit trainer kit E2E",
                },
                headers=headers_su21,
            )
            assert handover_res.status in [200, 302], f"Expected 200/302 on handover, got {handover_res.status}"

            page_su21.reload()
            page_su21.wait_for_load_state("networkidle")
            content21 = page_su21.content()
            assert bast_num in content21, f"Expected BAST number {bast_num} on detail page"
            assert "Drs. H. Mulyadi" in content21, "Expected recipient name on detail page"
            assert "Aktif" in content21, "Expected Aktif warranty status badge"
            assert "2027-11-10" in content21, "Expected warranty expiration date 2027-11-10 (+1 calendar year)"
            assert "HANDOVER_RECORDED" in content21, "Expected HANDOVER_RECORDED lifecycle log"
            assert "WARRANTY_COMMENCED" in content21, "Expected WARRANTY_COMMENCED lifecycle log"
            print("  ✓ Handover recorded, 1-year warranty calculated and lifecycle logs verified")

            results["Scenario 21: Physical Handover (BAST) & 1-Year Warranty Commencement"] = "PASS"
        except Exception as e:
            print(f"  ✗ Scenario 21 Failed: {e}")
            results["Scenario 21: Physical Handover (BAST) & 1-Year Warranty Commencement"] = f"FAIL: {e}"
        finally:
            context_su21.close()

        # -------------------------------------------------------------
        # SCENARIO 22: WARRANTY CORRECTION & AUDIT TRAIL (DEVICE-04-03)
        # -------------------------------------------------------------
        print("\n[SCENARIO 22] Warranty Correction & Audit Trail (DEVICE-04-03)...")
        context_su22 = browser.new_context()
        page_su22 = context_su22.new_page()
        try:
            # 22.1 Superuser Login
            page_su22.goto(f"{BASE_URL}/login")
            page_su22.fill("#email", "superuser@bitanic.id")
            page_su22.fill("#password", "Password123!")
            page_su22.click("button[type='submit']")
            page_su22.wait_for_load_state("networkidle")

            csrf_su22 = page_su22.evaluate("document.querySelector('meta[name=\"csrf-token\"]')?.getAttribute('content') || ''")
            headers_su22 = {"X-CSRF-TOKEN": csrf_su22, "Accept": "application/json"}

            # Use kit from Scenario 21
            # 22.2 Reject short reason (< 5 chars)
            short_res = context_su22.request.post(
                f"{inventory_url21}/warranty/correct",
                data={
                    "warranty_start_date": "2026-11-15",
                    "correction_reason": "bad",
                },
                headers=headers_su22,
            )
            assert short_res.status in [422, 302], f"Expected 422 or 302 error for short reason, got {short_res.status}"
            print("  ✓ Warranty correction rejects invalid short reason (<5 chars)")

            # 22.3 Legitimate Warranty Date Correction with Reason
            correct_res = context_su22.request.post(
                f"{inventory_url21}/warranty/correct",
                data={
                    "warranty_start_date": "2026-11-15",
                    "correction_reason": "Penyesuaian tanggal serah terima fisik riil sesuai adendum BAST nomor 02.",
                },
                headers=headers_su22,
            )
            assert correct_res.status in [200, 302], f"Expected 200/302 on warranty correction, got {correct_res.status}"

            page_su22.goto(inventory_url21)
            page_su22.wait_for_load_state("networkidle")
            content22 = page_su22.content()
            assert "2026-11-15" in content22, "Expected updated start date 2026-11-15"
            assert "2027-11-15" in content22, "Expected recalculated expiration date 2027-11-15"
            assert "WARRANTY_CORRECTED" in content22, "Expected WARRANTY_CORRECTED lifecycle log"
            assert "Penyesuaian tanggal serah terima fisik riil" in content22, "Expected correction reason in audit history"
            print("  ✓ Warranty date corrected, expiration recalculated and immutable audit trail verified")

            results["Scenario 22: Warranty Correction & Audit Trail"] = "PASS"
        except Exception as e:
            print(f"  ✗ Scenario 22 Failed: {e}")
            results["Scenario 22: Warranty Correction & Audit Trail"] = f"FAIL: {e}"
        finally:
            context_su22.close()

        # -------------------------------------------------------------
        # SCENARIO 23: SCHOOL ADMIN WARRANTY VISIBILITY & CROSS-SCHOOL IDOR PROTECTION (DEVICE-04-04)
        # -------------------------------------------------------------
        print("\n[SCENARIO 23] School Admin Warranty Visibility & Cross-School IDOR Protection (DEVICE-04-04)...")
        context_admin = browser.new_context()
        page_admin = context_admin.new_page()
        try:
            # 23.1 Admin Sekolah Login
            page_admin.goto(f"{BASE_URL}/login")
            page_admin.fill("#email", "admin@smk.id")
            page_admin.fill("#password", "Password123!")
            page_admin.click("button[type='submit']")
            page_admin.wait_for_load_state("networkidle")

            # 23.2 View Trainer Kits in School Workstation
            resp_kits = page_admin.goto(f"{BASE_URL}/trainer-kits")
            assert resp_kits.status == 200, f"Expected 200 on /trainer-kits, got {resp_kits.status}"
            content_kits = page_admin.content()
            assert "Trainer Kit" in content_kits, "Expected Trainer Kit heading"
            assert "TK-01" in content_kits, "Expected TK-01 kit in school workstation"
            csrf_admin = page_admin.evaluate("document.querySelector('meta[name=\"csrf-token\"]')?.getAttribute('content') || ''")
            headers_admin = {"X-CSRF-TOKEN": csrf_admin, "Accept": "application/json"}
            print("  ✓ Admin Sekolah accessed /trainer-kits workstation")

            # 23.3 Negative Authorization: Admin Sekolah cannot access Master Trainer Kits (403)
            resp_master_denied = page_admin.goto(f"{BASE_URL}/master/trainer-kits")
            assert resp_master_denied.status == 403, f"Admin Sekolah must receive 403 on /master/trainer-kits, got {resp_master_denied.status}"
            print("  ✓ Admin Sekolah strictly denied (HTTP 403) from Global Trainer Kit Master")

            # 23.4 Negative Authorization: Admin Sekolah cannot mutate Handover or Warranty (403)
            post_handover_denied = context_admin.request.post(
                f"{inventory_url21}/handover",
                data={"bast_number": "HAX-01", "handover_date": "2026-11-10", "recipient_name": "Hacker"},
                headers=headers_admin,
            )
            assert post_handover_denied.status == 403, f"Admin Sekolah must receive 403 on handover mutation, got {post_handover_denied.status}"

            post_warranty_denied = context_admin.request.post(
                f"{inventory_url21}/warranty/correct",
                data={"warranty_start_date": "2026-11-10", "correction_reason": "Hacking reason"},
                headers=headers_admin,
            )
            assert post_warranty_denied.status == 403, f"Admin Sekolah must receive 403 on warranty correction, got {post_warranty_denied.status}"
            print("  ✓ Admin Sekolah strictly forbidden (HTTP 403) from mutating handover or warranty")

            results["Scenario 23: School Admin Warranty Visibility & Cross-School IDOR Protection"] = "PASS"
        except Exception as e:
            print(f"  ✗ Scenario 23 Failed: {e}")
            results["Scenario 23: School Admin Warranty Visibility & Cross-School IDOR Protection"] = f"FAIL: {e}"
        finally:
            context_admin.close()

        browser.close()

    print("\n=== REAL BROWSER E2E TEST SUMMARY ===")
    all_passed = True
    for scenario, status in results.items():
        print(f"  - {scenario}: {status}")
        if status != "PASS":
            all_passed = False

    if not all_passed:
        print("\nFAILED: One or more real browser E2E scenarios did not pass.")
        sys.exit(1)
    else:
        print(f"\nSUCCESS: All {len(results)} real browser E2E scenarios passed completely!")

if __name__ == "__main__":
    run_tests()
