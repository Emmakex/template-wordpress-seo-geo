const { test, expect } = require('@playwright/test');

const mobileProjects = new Set(['mobile-320', 'mobile-390']);
const corporatePath = '/corporate-v5-fixture/?fixture_lang=en&fixture_preset=corporate';

test('Corporate mobile menu closes by toggle, outside tap, link and Escape', async ({ page }, testInfo) => {
  test.skip(!mobileProjects.has(testInfo.project.name), 'Mobile navigation only renders below the Corporate breakpoint.');

  const response = await page.goto(corporatePath, { waitUntil: 'networkidle' });
  expect(response).not.toBeNull();
  expect(response.ok()).toBeTruthy();

  const details = page.locator('.seo-geo-preset-navigation__mobile');
  const summary = details.locator('summary');
  const contact = details.locator('.seo-geo-preset-navigation__item').last().locator('a');

  await expect(details).toBeVisible();
  await expect(summary).toBeVisible();
  await expect(page.locator('script[src*="preset-navigation.js"]')).toHaveCount(0);

  const summaryBox = await summary.boundingBox();
  expect(summaryBox).not.toBeNull();
  expect(summaryBox.height).toBeGreaterThanOrEqual(44);

  await summary.click();
  await expect(details).toHaveAttribute('open', '');
  await summary.click();
  await expect(details).not.toHaveAttribute('open', '');

  await summary.click();
  await expect(details).toHaveAttribute('open', '');
  await page.mouse.click(8, 420);
  await expect(details).not.toHaveAttribute('open', '');

  await summary.click();
  await expect(details).toHaveAttribute('open', '');
  await page.keyboard.press('Escape');
  await expect(details).not.toHaveAttribute('open', '');
  await expect(summary).toBeFocused();

  await summary.click();
  await expect(details).toHaveAttribute('open', '');
  await page.evaluate(() => {
    const link = document.querySelector('.seo-geo-preset-navigation__mobile .seo-geo-preset-navigation__item a');
    if (link) {
      link.addEventListener('click', (event) => event.preventDefault(), { once: true });
    }
  });
  await details.locator('.seo-geo-preset-navigation__item a').first().click();
  await expect(details).not.toHaveAttribute('open', '');

  const contactStyles = await contact.evaluate((element) => {
    const styles = getComputedStyle(element);
    return {
      display: styles.display,
      alignItems: styles.alignItems,
      justifyContent: styles.justifyContent,
      textAlign: styles.textAlign,
      minHeight: Number.parseFloat(styles.minHeight) || 0,
    };
  });

  expect(contactStyles.display).toBe('flex');
  expect(contactStyles.alignItems).toBe('center');
  expect(contactStyles.justifyContent).toBe('center');
  expect(contactStyles.textAlign).toBe('center');
  expect(contactStyles.minHeight).toBeGreaterThanOrEqual(52);
});
