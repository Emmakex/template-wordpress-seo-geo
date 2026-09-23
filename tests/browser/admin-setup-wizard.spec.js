const { test, expect } = require('@playwright/test');
const AxeBuilder = require('@axe-core/playwright').default;

const wcagTags = [
  'wcag2a',
  'wcag2aa',
  'wcag21a',
  'wcag21aa',
  'wcag22a',
  'wcag22aa',
];

async function login(page) {
  await page.goto('/wp-login.php', { waitUntil: 'networkidle' });
  await page.locator('#user_login').fill('admin');
  await page.locator('#user_pass').fill('wordpress-browser-admin');
  await page.getByRole('button', { name: /log in/i }).click();
  await expect(page.locator('#wpadminbar')).toBeVisible();
}

async function gotoWizard(page, language = 'en') {
  await login(page);
  const response = await page.goto(
    `/wp-admin/themes.php?page=seo-geo-setup&fixture_lang=${language}`,
    { waitUntil: 'networkidle' },
  );
  expect(response).not.toBeNull();
  expect(response.ok()).toBeTruthy();
  await expect(page.locator('.seo-geo-setup-wizard')).toBeVisible();
}

test.describe('Phase 9D theme setup wizard', () => {
  test('renders EN/ES with scoped WCAG acceptance and responsive reflow', async ({ page }, testInfo) => {
    await gotoWizard(page, 'en');

    await expect(page.getByRole('heading', { level: 1, name: 'SEO/GEO Setup' })).toBeVisible();
    await expect(page.locator('.seo-geo-setup-wizard__step')).toHaveCount(4);
    await expect(page.locator('form')).toHaveCount(1);
    await expect(page.getByLabel('Preset')).toBeVisible();
    await expect(page.getByLabel('Language map')).toBeVisible();
    await expect(page.getByLabel('Site entity')).toBeVisible();
    await expect(page.getByLabel('I confirm these validated settings should be applied to this site.')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Apply setup' })).toBeVisible();

    const scan = await new AxeBuilder({ page })
      .include('.seo-geo-setup-wizard')
      .withTags(wcagTags)
      .analyze();

    await testInfo.attach('setup-wizard-en-axe-results', {
      body: JSON.stringify(scan, null, 2),
      contentType: 'application/json',
    });

    expect(scan.violations).toEqual([]);

    const reflow = await page.locator('.seo-geo-setup-wizard').evaluate((element) => ({
      width: element.getBoundingClientRect().width,
      scrollWidth: element.scrollWidth,
      viewport: window.innerWidth,
    }));

    expect(reflow.width).toBeLessThanOrEqual(reflow.viewport + 1);
    expect(reflow.scrollWidth).toBeLessThanOrEqual(reflow.width + 1);

    await page.getByLabel('Preset').focus();
    await expect(page.getByLabel('Preset')).toBeFocused();
    await page.keyboard.press('Tab');
    await expect(page.getByLabel('Primary language code')).toBeFocused();

    await page.goto('/wp-admin/themes.php?page=seo-geo-setup&fixture_lang=es', {
      waitUntil: 'networkidle',
    });

    await expect(page.getByRole('heading', { level: 1, name: 'Configuración SEO/GEO' })).toBeVisible();
    await expect(page.getByLabel('Preset')).toBeVisible();
    await expect(page.getByLabel('Mapa de idiomas')).toBeVisible();
    await expect(page.getByLabel('Entidad del sitio')).toBeVisible();

    const esScan = await new AxeBuilder({ page })
      .include('.seo-geo-setup-wizard')
      .withTags(wcagTags)
      .analyze();

    await testInfo.attach('setup-wizard-es-axe-results', {
      body: JSON.stringify(esScan, null, 2),
      contentType: 'application/json',
    });

    expect(esScan.violations).toEqual([]);
  });

  test('validates preview without persistence and moves focus to results', async ({ page }, testInfo) => {
    await gotoWizard(page, 'en');

    await page.getByLabel('Preset').selectOption('corporate');
    await page.getByLabel('Primary language code').fill('en');
    await page.getByLabel('Language map').fill('en=en_US\nes=es_ES');
    await page.getByLabel('Native routing').selectOption('prefix');
    await page.getByLabel('x-default language code').fill('en');
    await page.getByLabel('Site entity').selectOption('organization');
    await page.getByLabel('I confirm this identity describes the real public site.').check();
    await page.getByLabel('OAI-SearchBot').selectOption('allow');
    await page.getByLabel('GPTBot').selectOption('disallow');
    await page.getByLabel('Enable llms.txt').check();
    await page.getByLabel('Enable Markdown alternates').check();
    await page.getByLabel('I understand preview validates only and does not save settings.').check();

    await page.getByRole('button', { name: 'Validate setup' }).click();

    const results = page.locator('#seo-geo-setup-results');
    await expect(results).toBeVisible();
    await expect(results).toBeFocused();
    await expect(page.getByRole('heading', { name: 'Setup preview is valid.' })).toBeVisible();
    await expect(results).toContainText('corporate');
    await expect(results).toContainText('en=en_US');
    await expect(results).toContainText('organization');

    const scan = await new AxeBuilder({ page })
      .include('.seo-geo-setup-wizard')
      .withTags(wcagTags)
      .analyze();

    await testInfo.attach('setup-wizard-preview-axe-results', {
      body: JSON.stringify(scan, null, 2),
      contentType: 'application/json',
    });

    expect(scan.violations).toEqual([]);

  });

  test('announces validation errors and keeps the result keyboard-focusable', async ({ page }) => {
    await gotoWizard(page, 'en');

    await page.getByLabel('Preset').selectOption('corporate');
    await page.getByLabel('Primary language code').fill('en');
    await page.getByLabel('Language map').fill('en=en_US');
    await page.getByLabel('Native routing').selectOption('prefix');
    await page.getByLabel('Site entity').selectOption('organization');
    await page.getByLabel('I understand preview validates only and does not save settings.').check();

    await page.getByRole('button', { name: 'Validate setup' }).click();

    const results = page.locator('#seo-geo-setup-results');
    await expect(results).toBeVisible();
    await expect(results).toBeFocused();
    await expect(page.getByRole('heading', { name: 'Setup preview needs attention.' })).toBeVisible();
    await expect(results.getByRole('alert')).toBeVisible();
    await expect(results).toContainText('identity-confirmation-required');
    await expect(results).toContainText('prefix-routing-requires-multiple-languages');
  });
});
