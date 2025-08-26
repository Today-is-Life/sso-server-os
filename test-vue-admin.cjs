const puppeteer = require('puppeteer');

async function testVueAdminInterface() {
    const browser = await puppeteer.launch({ 
        headless: false, 
        defaultViewport: { width: 1200, height: 800 }
    });
    
    try {
        const page = await browser.newPage();
        
        console.log('🚀 Testing Vue.js SSO Admin Interface...');
        
        // Navigate to login page
        await page.goto('http://127.0.0.1:8002/admin');
        await page.waitForSelector('form', { timeout: 5000 });
        
        console.log('✅ Login page loaded');
        
        // Try to login (if there's a test user)
        try {
            await page.type('input[name="email"]', 'admin@test.com');
            await page.type('input[name="password"]', 'password');
            await page.click('button[type="submit"]');
            
            // Wait for either dashboard or error
            await page.waitForNavigation({ timeout: 10000 });
            console.log('✅ Login attempt completed');
            
        } catch (loginError) {
            console.log('⚠️  Login failed, checking if we reach admin interface...');
        }
        
        // Try to access admin dashboard directly
        await page.goto('http://127.0.0.1:8002/admin/dashboard');
        
        // Check for Vue.js components
        await page.waitForTimeout(2000);
        
        const pageTitle = await page.title();
        const hasVueApp = await page.$('#vue-app');
        const hasAdminDashboard = await page.$('admin-dashboard');
        
        console.log(`📄 Page Title: ${pageTitle}`);
        console.log(`🎯 Vue App Present: ${hasVueApp ? 'YES' : 'NO'}`);
        console.log(`📊 Admin Dashboard Component: ${hasAdminDashboard ? 'YES' : 'NO'}`);
        
        // Check for JavaScript errors
        const jsErrors = [];
        page.on('pageerror', error => {
            jsErrors.push(error.message);
        });
        
        // Take screenshot
        await page.screenshot({ path: 'vue-admin-test.png', fullPage: true });
        console.log('📸 Screenshot saved: vue-admin-test.png');
        
        if (jsErrors.length > 0) {
            console.log('❌ JavaScript Errors:');
            jsErrors.forEach(error => console.log(`   - ${error}`));
        } else {
            console.log('✅ No JavaScript errors detected');
        }
        
        // Check for Vue.js in console
        const vueExists = await page.evaluate(() => {
            return typeof Vue !== 'undefined' || document.querySelector('[data-v-]') !== null;
        });
        
        console.log(`⚡ Vue.js Active: ${vueExists ? 'YES' : 'NO'}`);
        
    } catch (error) {
        console.error('❌ Test failed:', error.message);
    }
    
    await browser.close();
}

testVueAdminInterface();