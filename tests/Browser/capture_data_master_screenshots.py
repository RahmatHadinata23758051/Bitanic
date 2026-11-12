import os
import sys
import time
from playwright.sync_api import sync_playwright

BASE_URL = os.environ.get("BASE_URL", "http://127.0.0.1:8000")
OUT_DIR = os.path.abspath("docs/screenshots")
os.makedirs(OUT_DIR, exist_ok=True)

def run():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        
        # Desktop context
        context_desktop = browser.new_context(viewport={"width": 1440, "height": 900})
        page = context_desktop.new_page()

        print(f"Connecting to {BASE_URL}...")
        # 1. Login as superuser
        page.goto(f"{BASE_URL}/login")
        page.fill("#email", "superuser@bitanic.id")
        page.fill("#password", "password123")
        page.click("button[type='submit']")
        page.wait_for_load_state("networkidle")
        assert "/login" not in page.url, "Login failed for superuser"
        print("Logged in as superuser successfully.")

        # 2. Wilayah Overview (Desktop)
        print("Navigating to /master/regions (Wilayah)...")
        page.goto(f"{BASE_URL}/master/regions")
        page.wait_for_selector('#school-distribution-map')
        time.sleep(2) # Allow Leaflet tiles & markers to render
        desktop_wilayah_path = os.path.join(OUT_DIR, "wilayah_overview_desktop.png")
        page.screenshot(path=desktop_wilayah_path, full_page=True)
        print(f"Captured: {desktop_wilayah_path}")

        # 3. Wilayah Drilldown (Desktop)
        print("Capturing Wilayah Explorer drilldown...")
        try:
            prov_btn = page.query_selector("button:has-text('JAWA BARAT'), button:has-text('Jawa Barat')")
            if prov_btn:
                prov_btn.click()
                time.sleep(1)
        except Exception as e:
            print(f"Note on drilldown click: {e}")
        
        drilldown_path = os.path.join(OUT_DIR, "wilayah_drilldown_desktop.png")
        page.screenshot(path=drilldown_path, full_page=True)
        print(f"Captured: {drilldown_path}")

        # 4. Trainer Kit Overview (Desktop)
        print("Navigating to /master/trainer-kits...")
        page.goto(f"{BASE_URL}/master/trainer-kits")
        page.wait_for_selector('h1:has-text("Inventaris Trainer Kit")')
        time.sleep(1)
        tk_overview_path = os.path.join(OUT_DIR, "trainer_kit_overview_desktop.png")
        page.screenshot(path=tk_overview_path, full_page=True)
        print(f"Captured: {tk_overview_path}")

        # 5. Trainer Kit Detail (Desktop)
        detail_link = page.query_selector("a:has-text('Kelola Unit')")
        if detail_link:
            href = detail_link.get_attribute("href")
            print(f"Navigating to trainer kit detail: {href}")
            page.goto(href)
            page.wait_for_load_state("networkidle")
            time.sleep(1)
            tk_detail_path = os.path.join(OUT_DIR, "trainer_kit_detail_desktop.png")
            page.screenshot(path=tk_detail_path, full_page=True)
            print(f"Captured: {tk_detail_path}")
        else:
            print("No detail link found.")

        # 6. LMS License Registry (Desktop)
        print("Navigating to /master/licenses...")
        page.goto(f"{BASE_URL}/master/licenses")
        page.wait_for_selector('h1:has-text("Master Lisensi LMS Sekolah")')
        time.sleep(1)
        lic_registry_path = os.path.join(OUT_DIR, "license_registry_desktop.png")
        page.screenshot(path=lic_registry_path, full_page=True)
        print(f"Captured: {lic_registry_path}")

        # 7. School Detail & Coordinates Verification (Desktop)
        print("Navigating to /master/schools/1 (GIS Verification)...")
        page.goto(f"{BASE_URL}/master/schools/1")
        page.wait_for_selector('h1')
        time.sleep(1)
        school_gis_path = os.path.join(OUT_DIR, "school_detail_gis_desktop.png")
        page.screenshot(path=school_gis_path, full_page=True)
        print(f"Captured: {school_gis_path}")

        context_desktop.close()

        # Tablet context (768 x 1024 iPad Mini)
        print("Capturing Tablet views (768px)...")
        context_tablet = browser.new_context(viewport={"width": 768, "height": 1024})
        page_t = context_tablet.new_page()
        page_t.goto(f"{BASE_URL}/login")
        page_t.fill("#email", "superuser@bitanic.id")
        page_t.fill("#password", "password123")
        page_t.click("button[type='submit']")
        page_t.wait_for_load_state("networkidle")
        page_t.goto(f"{BASE_URL}/master/regions")
        page_t.wait_for_selector('#school-distribution-map')
        time.sleep(2)
        t_wilayah_path = os.path.join(OUT_DIR, "wilayah_overview_tablet.png")
        page_t.screenshot(path=t_wilayah_path, full_page=True)
        print(f"Captured: {t_wilayah_path}")
        context_tablet.close()

        # Mobile context (390 x 844 iPhone 12/13/14 size)
        print("Capturing Mobile views...")
        context_mobile = browser.new_context(viewport={"width": 390, "height": 844})
        page_m = context_mobile.new_page()

        # Login mobile
        page_m.goto(f"{BASE_URL}/login")
        page_m.fill("#email", "superuser@bitanic.id")
        page_m.fill("#password", "password123")
        page_m.click("button[type='submit']")
        page_m.wait_for_load_state("networkidle")
        assert "/login" not in page_m.url, "Mobile login failed"

        # Wilayah Mobile
        page_m.goto(f"{BASE_URL}/master/regions")
        page_m.wait_for_selector('#school-distribution-map')
        time.sleep(2)
        m_wilayah_path = os.path.join(OUT_DIR, "wilayah_overview_mobile.png")
        page_m.screenshot(path=m_wilayah_path, full_page=True)
        print(f"Captured: {m_wilayah_path}")

        # Trainer Kit Mobile
        page_m.goto(f"{BASE_URL}/master/trainer-kits")
        page_m.wait_for_selector('h1:has-text("Inventaris Trainer Kit")')
        time.sleep(1)
        m_tk_path = os.path.join(OUT_DIR, "trainer_kit_overview_mobile.png")
        page_m.screenshot(path=m_tk_path, full_page=True)
        print(f"Captured: {m_tk_path}")

        # License Registry Mobile
        page_m.goto(f"{BASE_URL}/master/licenses")
        page_m.wait_for_selector('h1:has-text("Master Lisensi LMS Sekolah")')
        time.sleep(1)
        m_lic_path = os.path.join(OUT_DIR, "license_registry_mobile.png")
        page_m.screenshot(path=m_lic_path, full_page=True)
        print(f"Captured: {m_lic_path}")

        context_mobile.close()
        browser.close()
        print("All screenshots successfully captured!")

if __name__ == "__main__":
    run()
