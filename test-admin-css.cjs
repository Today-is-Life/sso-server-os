const puppeteer = require('puppeteer');

async function testAdminCSS() {
    const browser = await puppeteer.launch({ 
        headless: false,
        defaultViewport: { width: 1400, height: 900 }
    });
    
    const page = await browser.newPage();
    
    try {
        console.log('🚀 Testing Admin CSS and Icons...');
        
        // Go to admin login
        await page.goto('http://127.0.0.1:8002/admin');
        await page.waitForSelector('form');
        
        console.log('✅ Login page loaded');
        
        // Try to login with test credentials
        await page.type('input[name="email"]', 'admin@test.com');
        await page.type('input[name="password"]', 'password123');
        await page.click('button[type="submit"]');
        
        // Wait for admin dashboard or spa page
        await page.waitForTimeout(3000);
        
        const currentUrl = page.url();
        console.log('Current URL:', currentUrl);
        
        // Check if we're in admin area
        if (currentUrl.includes('/admin') || currentUrl.includes('spa')) {
            console.log('✅ Successfully in admin area');
            
            // Check for Vue.js app
            const hasVueApp = await page.$('#vue-app');
            console.log('Vue App found:', hasVueApp ? 'YES' : 'NO');
            
            // Check CSS loading
            const cssLoaded = await page.evaluate(() => {
                const styles = getComputedStyle(document.body);
                return {
                    fontFamily: styles.fontFamily,
                    background: styles.backgroundColor,
                    hasViteAssets: !!document.querySelector('link[href*="build/assets"]') || !!document.querySelector('script[src*="build/assets"]')
                };
            });
            
            console.log('CSS Status:', cssLoaded);
            
            // Look for Vue components
            const components = await page.evaluate(() => {
                const vueComponents = document.querySelectorAll('[class*="vue"], [data-v-], admin-dashboard, user-manager, sso-admin-app');
                return Array.from(vueComponents).map(el => el.tagName + ' ' + el.className);
            });
            
            console.log('Vue Components found:', components);
            
        } else {
            console.log('⚠️ Still on login page, checking for errors...');
            
            // Check for error messages
            const errors = await page.$$eval('.alert, .error, [class*="error"]', els => 
                els.map(el => el.textContent)
            ).catch(() => []);
            
            console.log('Login errors:', errors);
        }
        
        // Take screenshot
        await page.screenshot({ path: 'admin-css-test.png', fullPage: true });
        console.log('📸 Screenshot saved: admin-css-test.png');
        
        // Keep open for inspection
        console.log('Browser kept open for inspection...');
        
    } catch (error) {
        console.error('❌ Test error:', error.message);
        await page.screenshot({ path: 'admin-error.png', fullPage: true });
    }
}

testAdminCSS();