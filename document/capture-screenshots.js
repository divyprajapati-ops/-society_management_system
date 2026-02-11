const { chromium } = require('playwright');
const path = require('path');

(async () => {
    const browser = await chromium.launch();
    const context = await browser.newContext({
        viewport: { width: 1280, height: 720 }
    });
    const page = await context.newPage();
    
    const screenshotsDir = path.join(__dirname, 'screenshots');
    
    // 1. Login Page
    await page.goto('http://localhost:8080/backend/auth/login.php');
    await page.waitForTimeout(1000);
    await page.screenshot({ path: path.join(screenshotsDir, 'login_page.png'), fullPage: false });
    console.log('✓ Login page captured');
    
    // 2. Admin Dashboard
    await page.fill('input[name="email"]', 'admin@society.test');
    await page.fill('input[name="password"]', 'admin123');
    await page.click('button[type="submit"]');
    await page.waitForTimeout(2000);
    await page.screenshot({ path: path.join(screenshotsDir, 'admin_dashboard.png'), fullPage: false });
    console.log('✓ Admin dashboard captured');
    
    // 3. Society Fund
    await page.goto('http://localhost:8080/backend/admin/society_fund.php');
    await page.waitForTimeout(1000);
    await page.screenshot({ path: path.join(screenshotsDir, 'society_fund.png'), fullPage: false });
    console.log('✓ Society fund captured');
    
    // 4. Buildings
    await page.goto('http://localhost:8080/backend/admin/buildings.php');
    await page.waitForTimeout(1000);
    await page.screenshot({ path: path.join(screenshotsDir, 'buildings.png'), fullPage: false });
    console.log('✓ Buildings captured');
    
    // 5. Users Management
    await page.goto('http://localhost:8080/backend/admin/users.php');
    await page.waitForTimeout(1000);
    await page.screenshot({ path: path.join(screenshotsDir, 'users_management.png'), fullPage: false });
    console.log('✓ Users management captured');
    
    // 6. Pramukh Dashboard
    await page.goto('http://localhost:8080/backend/auth/logout.php');
    await page.goto('http://localhost:8080/backend/auth/login.php');
    await page.fill('input[name="email"]', 'pramukh@society.test');
    await page.fill('input[name="password"]', 'pramukh123');
    await page.click('button[type="submit"]');
    await page.waitForTimeout(2000);
    await page.screenshot({ path: path.join(screenshotsDir, 'pramukh_dashboard.png'), fullPage: false });
    console.log('✓ Pramukh dashboard captured');
    
    // 7. Building Admin Dashboard
    await page.goto('http://localhost:8080/backend/auth/logout.php');
    await page.goto('http://localhost:8080/backend/auth/login.php');
    await page.fill('input[name="email"]', 'building@society.test');
    await page.fill('input[name="password"]', 'building123');
    await page.click('button[type="submit"]');
    await page.waitForTimeout(2000);
    await page.screenshot({ path: path.join(screenshotsDir, 'building_admin_dashboard.png'), fullPage: false });
    console.log('✓ Building admin dashboard captured');
    
    // 8. Member Dashboard
    await page.goto('http://localhost:8080/backend/auth/logout.php');
    await page.goto('http://localhost:8080/backend/auth/login.php');
    await page.fill('input[name="email"]', 'member@society.test');
    await page.fill('input[name="password"]', 'member123');
    await page.click('button[type="submit"]');
    await page.waitForTimeout(2000);
    await page.screenshot({ path: path.join(screenshotsDir, 'member_dashboard.png'), fullPage: false });
    console.log('✓ Member dashboard captured');
    
    // 9. Maintenance
    await page.goto('http://localhost:8080/backend/building/maintenance.php');
    await page.waitForTimeout(1000);
    await page.screenshot({ path: path.join(screenshotsDir, 'maintenance.png'), fullPage: false });
    console.log('✓ Maintenance captured');
    
    // 10. Meetings
    await page.goto('http://localhost:8080/backend/building/meetings.php');
    await page.waitForTimeout(1000);
    await page.screenshot({ path: path.join(screenshotsDir, 'meetings.png'), fullPage: false });
    console.log('✓ Meetings captured');
    
    await browser.close();
    console.log('\n✅ All screenshots captured successfully!');
    console.log('📁 Location: document/screenshots/');
})();
