from playwright.sync_api import sync_playwright

with sync_playwright() as p:
    browser = p.chromium.launch(headless=True)
    page = browser.new_page()
    page.goto("http://127.0.0.1:8008/login")
    print("Page Title:", page.title())
    page.fill("#email", "siswa@smk.id")
    page.fill("#password", "Password123!")
    
    # Check form action and csrf token
    csrf = page.locator("input[name='_token']").get_attribute("value")
    print("CSRF Token present:", bool(csrf))
    
    page.click("button[type='submit']")
    page.wait_for_timeout(2000)
    print("URL after submit:", page.url)
    
    # Check error messages
    errors = page.locator(".form-message.error, .error, #email-error, #password-error").all_text_contents()
    print("Errors found:", errors)
    
    # Check all p tags
    for p_tag in page.locator(".form-message").all_text_contents():
        print("Form message:", p_tag)
        
    browser.close()
