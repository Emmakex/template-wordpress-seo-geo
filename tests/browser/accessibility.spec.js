const { test, expect } = require('@playwright/test');
const AxeBuilder = require('@axe-core/playwright').default;

const fixtures = [
  {
    language: 'EN',
    path: '/acceptance-en/?fixture_lang=en',
    htmlLang: /^en(?:-|$)/i,
  },
  {
    language: 'ES',
    path: '/acceptance-es/?fixture_lang=es',
    htmlLang: /^es(?:-|$)/i,
  },
  {
    language: 'MIGRATION',
    path: '/migration-parity-fixture/?fixture_lang=en',
    htmlLang: /^en(?:-|$)/i,
  },
];

const wcagTags = [
  'wcag2a',
  'wcag2aa',
  'wcag21a',
  'wcag21aa',
  'wcag22a',
  'wcag22aa',
];

function violationSummary(violations) {
  return violations.map((violation) => ({
    id: violation.id,
    impact: violation.impact,
    targets: violation.nodes.map((node) => node.target),
  }));
}

async function gotoFixture(page, fixture) {
  const response = await page.goto(fixture.path, { waitUntil: 'networkidle' });
  expect(response, `${fixture.language} fixture should return a response`).not.toBeNull();
  expect(response.ok(), `${fixture.language} fixture should return HTTP 2xx`).toBeTruthy();
}

for (const fixture of fixtures) {
  test.describe(`${fixture.language} representative fixture`, () => {
    test('passes semantic and automated WCAG acceptance', async ({ page }, testInfo) => {
      await gotoFixture(page, fixture);

      await expect(page.locator('html')).toHaveAttribute('lang', fixture.htmlLang);
      await expect(page.getByRole('banner')).toHaveCount(1);
      await expect(page.getByRole('main')).toHaveCount(1);
      await expect(page.getByRole('contentinfo')).toHaveCount(1);
      await expect(page.getByRole('heading', { level: 1 })).toHaveCount(1);

      const headings = await page.locator('h1, h2, h3, h4, h5, h6').evaluateAll((nodes) =>
        nodes.map((node) => ({
          level: Number(node.tagName.substring(1)),
          text: (node.textContent || '').trim(),
        })),
      );

      expect(headings.length, 'Representative fixture should have a useful heading structure').toBeGreaterThan(1);
      expect(headings[0].level, 'The page title must own the first H1').toBe(1);
      expect(headings.every((heading) => heading.text.length > 0), 'Headings must not be empty').toBeTruthy();

      for (let index = 1; index < headings.length; index += 1) {
        expect(
          headings[index].level - headings[index - 1].level,
          `Heading level must not jump from H${headings[index - 1].level} to H${headings[index].level}`,
        ).toBeLessThanOrEqual(1);
      }

      const scan = await new AxeBuilder({ page }).withTags(wcagTags).analyze();
      await testInfo.attach(`${fixture.language.toLowerCase()}-axe-results`, {
        body: JSON.stringify(scan, null, 2),
        contentType: 'application/json',
      });

      expect(
        scan.violations,
        `Automated WCAG violations: ${JSON.stringify(violationSummary(scan.violations))}`,
      ).toEqual([]);
    });

    test('reflows without horizontal overflow', async ({ page }) => {
      await gotoFixture(page, fixture);

      const metrics = await page.evaluate(() => ({
        viewportWidth: window.innerWidth,
        documentClientWidth: document.documentElement.clientWidth,
        documentScrollWidth: document.documentElement.scrollWidth,
        bodyScrollWidth: document.body.scrollWidth,
      }));

      expect(metrics.documentScrollWidth).toBeLessThanOrEqual(metrics.viewportWidth + 1);
      expect(metrics.bodyScrollWidth).toBeLessThanOrEqual(metrics.viewportWidth + 1);
      expect(metrics.documentClientWidth).toBeLessThanOrEqual(metrics.viewportWidth + 1);
    });

    test('supports skip-link, keyboard reachability and visible focus', async ({ page }) => {
      await gotoFixture(page, fixture);

      const skipLink = page.locator('a.skip-link');
      await expect(skipLink).toHaveCount(1);
      await skipLink.focus();
      await expect(skipLink).toBeVisible();
      await page.keyboard.press('Enter');
      await expect(page.getByRole('main')).toBeFocused();

      await gotoFixture(page, fixture);

      let focusEvidence = null;
      const distinctFocusTargets = new Set();

      for (let attempt = 0; attempt < 30; attempt += 1) {
        await page.keyboard.press('Tab');
        const state = await page.evaluate(() => {
          const element = document.activeElement;
          if (!element || element === document.body) {
            return null;
          }

          const signature = [
            element.tagName,
            element.id || '',
            element.getAttribute('class') || '',
            (element.textContent || '').trim().substring(0, 80),
          ].join('|');

          const isMainInteractive = Boolean(
            element.closest('main') && ['A', 'BUTTON'].includes(element.tagName),
          );

          if (!isMainInteractive) {
            return { signature, evidence: null };
          }

          const focused = getComputedStyle(element);
          const focusedSnapshot = {
            color: focused.color,
            backgroundColor: focused.backgroundColor,
            outlineStyle: focused.outlineStyle,
            outlineWidth: focused.outlineWidth,
            boxShadow: focused.boxShadow,
            focusVisible: element.matches(':focus-visible'),
          };

          element.blur();
          const unfocused = getComputedStyle(element);
          const unfocusedSnapshot = {
            color: unfocused.color,
            backgroundColor: unfocused.backgroundColor,
          };

          return {
            signature,
            evidence: {
              focused: focusedSnapshot,
              unfocused: unfocusedSnapshot,
            },
          };
        });

        if (!state) {
          continue;
        }

        distinctFocusTargets.add(state.signature);
        if (state.evidence && !focusEvidence) {
          focusEvidence = state.evidence;
        }
      }

      expect(distinctFocusTargets.size, 'Keyboard navigation should reach multiple distinct controls').toBeGreaterThanOrEqual(3);
      expect(focusEvidence, 'Keyboard navigation should reach an interactive control inside main').not.toBeNull();
      expect(focusEvidence.focused.focusVisible, 'Keyboard-focused control should match :focus-visible').toBeTruthy();

      const outlineWidth = Number.parseFloat(focusEvidence.focused.outlineWidth) || 0;
      const hasOutline = focusEvidence.focused.outlineStyle !== 'none' && outlineWidth > 0;
      const hasShadow = focusEvidence.focused.boxShadow !== 'none';
      const changesColor =
        focusEvidence.focused.color !== focusEvidence.unfocused.color ||
        focusEvidence.focused.backgroundColor !== focusEvidence.unfocused.backgroundColor;

      expect(
        hasOutline || hasShadow || changesColor,
        `Focus indicator must be visible. Evidence: ${JSON.stringify(focusEvidence)}`,
      ).toBeTruthy();
    });

    test('honors reduced-motion preference for project content', async ({ page }) => {
      await page.emulateMedia({ reducedMotion: 'reduce' });
      await gotoFixture(page, fixture);

      const motion = await page.evaluate(() => {
        const toMilliseconds = (value) => {
          const normalized = value.trim();
          if (normalized.endsWith('ms')) {
            return Number.parseFloat(normalized) || 0;
          }
          if (normalized.endsWith('s')) {
            return (Number.parseFloat(normalized) || 0) * 1000;
          }
          return 0;
        };

        const maxDuration = (value) => Math.max(
          0,
          ...value.split(',').map((duration) => toMilliseconds(duration)),
        );

        const offenders = [];
        for (const element of document.querySelectorAll('main *')) {
          const styles = getComputedStyle(element);
          const animationActive =
            styles.animationName !== 'none' && maxDuration(styles.animationDuration) > 0;
          const transitionActive =
            styles.transitionProperty !== 'none' && maxDuration(styles.transitionDuration) > 0;

          if (animationActive || transitionActive) {
            offenders.push({
              tag: element.tagName,
              classes: element.getAttribute('class') || '',
              animationName: styles.animationName,
              animationDuration: styles.animationDuration,
              transitionProperty: styles.transitionProperty,
              transitionDuration: styles.transitionDuration,
            });
          }
        }

        return {
          reducedMotionMatches: window.matchMedia('(prefers-reduced-motion: reduce)').matches,
          scrollBehavior: getComputedStyle(document.documentElement).scrollBehavior,
          offenders,
        };
      });

      expect(motion.reducedMotionMatches).toBeTruthy();
      expect(motion.scrollBehavior).not.toBe('smooth');
      expect(motion.offenders, `Motion still active under reduced motion: ${JSON.stringify(motion.offenders)}`).toEqual([]);
    });
  });
}
