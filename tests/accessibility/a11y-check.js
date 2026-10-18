/**
 * @file
 * Automated accessibility and keyboard checks for Demo City Council.
 *
 * Visits each key page and page state in Chromium at desktop and mobile
 * sizes, runs axe-core against WCAG 2.0, 2.1 and 2.2 A/AA plus best-practice
 * rules, checks keyboard behaviour, and saves screenshots.
 * Exits with code 1 if anything fails.
 *
 * Usage: BASE_URL=https://localgov-council.ddev.site npm run a11y
 * Set REPORT=1 to only list problems without failing (used for the audit).
 */

const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');

const BASE_URL = (process.env.BASE_URL || 'http://127.0.0.1:8888').replace(/\/$/, '');
const OUT = path.join(__dirname, 'screenshots');
const AXE = fs.readFileSync(require.resolve('axe-core/axe.min.js'), 'utf8');
const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice'];

// Static pages: [name, path].
const PAGES = [
  ['home page', '/'],
  ['services landing page', '/council-tax'],
  ['service page with a PDF', '/council-tax/council-tax-bands-and-charges'],
  ['guide page', '/housing/apply-council-housing/who-can-apply'],
  ['publication cover page', '/publications/annual-budget-2026-2027'],
  ['publication chapter', '/publications/annual-budget-2026-2027/annual-budget-2026-2027/where-money-goes'],
  ['search results', '/search?s=bins'],
  ['accessibility statement', '/about-website/accessibility-statement'],
];

let failures = 0;

function fail(message) {
  failures += 1;
  console.error(`  FAIL ${message}`);
  if (process.env.GITHUB_ACTIONS) {
    console.log(`::error title=Accessibility check::${message.replace(/%/g, '%25').replace(/\n/g, '%0A')}`);
  }
}

async function audit(page, name) {
  await page.addScriptTag({ content: AXE });
  const result = await page.evaluate((tags) => window.axe.run(document, { runOnly: tags }), TAGS);
  if (result.violations.length === 0) {
    console.log(`  ok   ${name}: 0 violations (${result.passes.length} rules passed)`);
  }
  result.violations.forEach((v) => {
    fail(`${name}: [${v.impact}] ${v.id} - ${v.help} (${v.nodes.map((n) => n.target.join(' ')).slice(0, 5).join(', ')})`);
  });
}

async function expectFocus(page, selector, label) {
  const ok = await page.evaluate((s) => document.activeElement && document.activeElement.matches(s), selector);
  if (ok) {
    console.log(`  ok   ${label}`);
  }
  else {
    const actual = await page.evaluate(() => document.activeElement && document.activeElement.outerHTML.slice(0, 80));
    fail(`${label}: focus is on ${actual}`);
  }
}

async function shot(page, device, name) {
  await page.screenshot({ path: `${OUT}/${device}-${name}.png`, fullPage: true });
}

(async () => {
  fs.mkdirSync(OUT, { recursive: true });
  const browser = await chromium.launch(
    process.env.CHROMIUM_PATH ? { executablePath: process.env.CHROMIUM_PATH } : {},
  );

  for (const [device, viewport] of [['desktop', { width: 1280, height: 900 }], ['mobile', { width: 390, height: 844 }]]) {
    console.log(`\n${device} (${viewport.width}px)`);
    const page = await (await browser.newContext({ viewport })).newPage();

    for (const [name, url] of PAGES) {
      const response = await page.goto(`${BASE_URL}${url}`, { waitUntil: 'networkidle' });
      if (!response || response.status() !== 200) {
        fail(`${name}: ${url} returned ${response ? response.status() : 'no response'}`);
        continue;
      }
      await audit(page, name);
    }
    await page.goto(`${BASE_URL}/`, { waitUntil: 'networkidle' });
    await shot(page, device, 'home');

    // Keyboard: the first Tab stop should be the skip link.
    await page.keyboard.press('Tab');
    await expectFocus(page, 'a[href="#main-content"]', 'first Tab stop is the skip link');

    // Bin day finder: results, then an error.
    await page.goto(`${BASE_URL}/bin-day`, { waitUntil: 'networkidle' });
    await audit(page, 'bin day form');
    await page.fill('main .bin-day-form input[name="postcode"]', 's1 2hh');
    await page.click('main .bin-day-form input[type="submit"]');
    await page.waitForLoadState('networkidle');
    await audit(page, 'bin day results');
    await expectFocus(page, '#bin-day-results-title', 'focus moves to the bin day results');
    await shot(page, device, 'bin-day-results');
    await page.fill('main .bin-day-form input[name="postcode"]', 'Sheffield');
    await page.click('main .bin-day-form input[type="submit"]');
    await page.waitForLoadState('networkidle');
    await audit(page, 'bin day error');

    // Accessible format request: notice, form, error, confirmation.
    await page.goto(`${BASE_URL}/council-tax/council-tax-bands-and-charges`, { waitUntil: 'networkidle' });
    await shot(page, device, 'document-notice');
    const requestLink = page.locator('.accessible-format-notice a').first();
    if (await requestLink.count() === 0) {
      fail('service page with a PDF: no "Request an accessible format" link');
    }
    else {
      await requestLink.click();
      await page.waitForLoadState('networkidle');
      await audit(page, 'accessible format form');
      await page.click('form#accessible-formats-request-form input[type="submit"]');
      await page.waitForLoadState('networkidle');
      await audit(page, 'accessible format form errors');
      await shot(page, device, 'request-errors');
      await page.fill('input[name="name"]', 'Ada Resident');
      await page.fill('input[name="email"]', `ada-${device}@example.com`);
      await page.check('input[name="format"][value="large_print"]');
      await page.click('form#accessible-formats-request-form input[type="submit"]');
      await page.waitForLoadState('networkidle');
      await audit(page, 'accessible format confirmation');
      await expectFocus(page, '#accessible-format-confirmation', 'focus moves to the request confirmation');
      await shot(page, device, 'request-confirmation');
    }
  }

  await browser.close();
  console.log(failures === 0 ? '\nAll accessibility checks passed.' : `\n${failures} check(s) failed.`);
  process.exit(failures === 0 || process.env.REPORT ? 0 : 1);
})().catch((error) => {
  console.error(error);
  if (process.env.GITHUB_ACTIONS) {
    console.log(`::error title=Accessibility script crashed::${String(error.stack || error).slice(0, 1500).replace(/%/g, '%25').replace(/\n/g, '%0A')}`);
  }
  process.exit(1);
});
