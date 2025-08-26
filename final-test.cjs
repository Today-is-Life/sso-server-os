const puppeteer = require('puppeteer');

async function finalTest() {
    const browser = await puppeteer.launch({ 
        headless: false,
        defaultViewport: { width: 1400, height: 900 }
    });
    
    const page = await browser.newPage();
    
    console.log('🚀 Final SSO Server Test...');
    
    try {
        // Test 1: SSO Welcome Page
        await page.goto('http://127.0.0.1:8002/');
        await new Promise(resolve => setTimeout(resolve, 2000));
        
        console.log('✅ SSO Welcome page loaded');
        console.log('Title:', await page.title());
        
        // Take screenshot of welcome page
        await page.screenshot({ path: 'sso-welcome-final.png', fullPage: true });
        console.log('📸 Welcome page screenshot saved');
        
        // Test 2: Check if login works
        await page.goto('http://127.0.0.1:8002/auth/login');
        await new Promise(resolve => setTimeout(resolve, 2000));
        
        console.log('✅ Login page loaded');
        await page.screenshot({ path: 'sso-login-final.png', fullPage: true });
        
        // Test 3: Try to access admin (should redirect to login)
        await page.goto('http://127.0.0.1:8002/admin');
        await new Promise(resolve => setTimeout(resolve, 2000));
        
        console.log('✅ Admin access test completed');
        console.log('Final URL:', page.url());
        
        await page.screenshot({ path: 'sso-admin-access-final.png', fullPage: true });
        
        console.log('🎉 All tests completed successfully!');
        console.log('Screenshots saved:');
        console.log('- sso-welcome-final.png');
        console.log('- sso-login-final.png');
        console.log('- sso-admin-access-final.png');
        
        // Keep browser open for 10 seconds for manual inspection
        await new Promise(resolve => setTimeout(resolve, 10000));
        
    } catch (error) {
        console.error('❌ Test error:', error.message);
        await page.screenshot({ path: 'sso-error-final.png', fullPage: true });
    }
    
    await browser.close();
}

finalTest();