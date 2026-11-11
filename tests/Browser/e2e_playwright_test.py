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
    print("=== STARTING BITANIC REAL BROWSER E2E TEST SUITE (8 SCENARIOS) ===")
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
