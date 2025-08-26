const puppeteer = require('puppeteer');

async function simpleTest() {
    const browser = await puppeteer.launch({ 
        headless: false,
        defaultViewport: { width: 1200, height: 800 }
    });
    
    const page = await browser.newPage();
    
    console.log('🚀 Testing SSO Admin Interface...');
    
    // Navigate to admin
    await page.goto('http://127.0.0.1:8002/admin');
    
    // Wait a bit for page to load
    await new Promise(resolve => setTimeout(resolve, 3000));
    
    // Take screenshot
    await page.screenshot({ path: 'admin-test.png', fullPage: true });
    
    // Check page content
    const title = await page.title();
    const content = await page.content();
    
    console.log(`Page title: ${title}`);
    console.log('Vue app found:', content.includes('vue-app'));
    console.log('Admin dashboard found:', content.includes('admin-dashboard'));
    
    // Keep browser open for inspection
    console.log('Browser kept open for manual inspection...');
    
    // Don't close automatically - let user inspect
    // await browser.close();
}

simpleTest().catch(console.error);