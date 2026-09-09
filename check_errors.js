import puppeteer from 'puppeteer';

(async () => {
  const browser = await puppeteer.launch({
    headless: "new",
    args: ['--no-sandbox', '--disable-setuid-sandbox']
  });
  const page = await browser.newPage();
  
  page.on('console', msg => {
    if (msg.type() === 'error') {
      console.log(`[Browser Console Error]`, msg.text());
    }
  });

  page.on('pageerror', error => {
    console.log(`[Browser PageError]`, error.message);
  });
  
  try {
    // Navigate directly to roles page. Since auth guard is disabled, it will mount the component.
    await page.goto('http://localhost:5173/roles', { waitUntil: 'networkidle0', timeout: 10000 });
    console.log('Roles page loaded');
  } catch (err) {
    console.log('Error loading page:', err.message);
  }

  await browser.close();
})();
