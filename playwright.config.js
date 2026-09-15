const { defineConfig } = require('@playwright/test');

if (!process.env.SEO_GEO_BASE_URL) {
  throw new Error('SEO_GEO_BASE_URL is required for browser acceptance tests.');
}

module.exports = defineConfig({
  testDir: './tests/browser',
  outputDir: 'test-results',
  timeout: 30_000,
  expect: {
    timeout: 5_000,
  },
  fullyParallel: false,
  workers: 1,
  retries: 0,
  reporter: [['line']],
  use: {
    baseURL: process.env.SEO_GEO_BASE_URL,
    browserName: 'chromium',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },
  projects: [
    {
      name: 'mobile-320',
      use: {
        viewport: { width: 320, height: 800 },
      },
    },
    {
      name: 'tablet-768',
      use: {
        viewport: { width: 768, height: 1024 },
      },
    },
    {
      name: 'desktop-1440',
      use: {
        viewport: { width: 1440, height: 900 },
      },
    },
  ],
});
