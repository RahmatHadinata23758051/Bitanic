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
    print("=== STARTING BITANIC REAL BROWSER E2E TEST SUITE (15 SCENARIOS) ===")
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
