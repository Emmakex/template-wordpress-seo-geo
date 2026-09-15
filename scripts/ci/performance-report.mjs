import fs from 'node:fs';
import path from 'node:path';
import process from 'node:process';

const reportsDir = process.argv[2] ?? 'performance-results';
const budgetsPath = process.argv[3] ?? 'tests/performance/budgets.json';
const budgets = JSON.parse(fs.readFileSync(budgetsPath, 'utf8'));

const pageKeys = ['en', 'es'];
const reportCount = 3;

const median = (values) => {
  const sorted = values.filter(Number.isFinite).sort((a, b) => a - b);
  if (!sorted.length) return null;
  return sorted[Math.floor(sorted.length / 2)];
};

const round = (value, decimals = 2) => {
  if (!Number.isFinite(value)) return null;
  const factor = 10 ** decimals;
  return Math.round(value * factor) / factor;
};

function readReport(page, index) {
  const file = path.join(reportsDir, `${page}-${index}.json`);
  return JSON.parse(fs.readFileSync(file, 'utf8'));
}

function extractDomNodes(lhr) {
  const legacy = lhr.audits?.['dom-size']?.numericValue;
  if (Number.isFinite(legacy)) return legacy;

  const insight = lhr.audits?.['dom-size-insight'];
  const queue = [...(insight?.details?.items ?? [])];
  while (queue.length) {
    const item = queue.shift();
    if (!item || typeof item !== 'object') continue;
    if (Array.isArray(item.items)) queue.push(...item.items);

    const statistic = String(item.statistic ?? '').toLowerCase();
    if (statistic !== 'total elements' && statistic !== 'total dom elements') continue;

    if (Number.isFinite(item.value)) return Number(item.value);
    if (item.value && Number.isFinite(item.value.value)) return Number(item.value.value);
  }

  return null;
}

function pageMetrics(page) {
  const reports = Array.from({ length: reportCount }, (_, i) => readReport(page, i + 1));
  const snapshots = reports.map((lhr) => {
    const requests = lhr.audits?.['network-requests']?.details?.items ?? [];
    const finalUrl = new URL(lhr.finalUrl);
    const sameOriginOrNonNetwork = (url) => {
      try {
        const parsed = new URL(url);
        if (!['http:', 'https:'].includes(parsed.protocol)) return true;
        return parsed.origin === finalUrl.origin;
      } catch {
        return true;
      }
    };
    const transfer = (type) => requests
      .filter((item) => item.resourceType === type)
      .reduce((sum, item) => sum + (Number(item.transferSize) || 0), 0);
    const projectJs = requests
      .filter((item) => item.resourceType === 'Script')
      .filter((item) => /\/wp-content\/(themes\/seo-geo-theme|plugins\/seo-geo-core)\//.test(item.url ?? ''))
      .reduce((sum, item) => sum + (Number(item.transferSize) || 0), 0);
    const thirdPartyRequests = requests.filter((item) => !sameOriginOrNonNetwork(item.url ?? '')).length;

    return {
      performanceScore: (lhr.categories?.performance?.score ?? 0) * 100,
      fcpMs: lhr.audits?.['first-contentful-paint']?.numericValue,
      lcpMs: lhr.audits?.['largest-contentful-paint']?.numericValue,
      cls: lhr.audits?.['cumulative-layout-shift']?.numericValue,
      tbtMs: lhr.audits?.['total-blocking-time']?.numericValue,
      speedIndexMs: lhr.audits?.['speed-index']?.numericValue,
      totalBytes: lhr.audits?.['total-byte-weight']?.numericValue,
      htmlBytes: transfer('Document'),
      cssBytes: transfer('Stylesheet'),
      jsBytes: transfer('Script'),
      imageBytes: transfer('Image'),
      totalRequests: requests.length,
      thirdPartyRequests,
      projectJavaScriptBytes: projectJs,
      domNodes: extractDomNodes(lhr),
    };
  });

  const metricNames = Object.keys(snapshots[0]);
  const aggregate = {};
  for (const metric of metricNames) {
    aggregate[metric] = median(snapshots.map((snapshot) => Number(snapshot[metric])));
  }

  return {
    samples: snapshots,
    median: aggregate,
  };
}

const result = Object.fromEntries(pageKeys.map((page) => [page, pageMetrics(page)]));

const failures = [];
const globalBudget = budgets.global ?? {};
for (const page of pageKeys) {
  const metrics = result[page].median;
  if (Number.isFinite(globalBudget.maxThirdPartyRequests) && metrics.thirdPartyRequests > globalBudget.maxThirdPartyRequests) {
    failures.push(`${page}: thirdPartyRequests ${metrics.thirdPartyRequests} > ${globalBudget.maxThirdPartyRequests}`);
  }
  if (Number.isFinite(globalBudget.maxProjectJavaScriptBytes) && metrics.projectJavaScriptBytes > globalBudget.maxProjectJavaScriptBytes) {
    failures.push(`${page}: projectJavaScriptBytes ${metrics.projectJavaScriptBytes} > ${globalBudget.maxProjectJavaScriptBytes}`);
  }

  if (budgets.mode === 'enforce') {
    const pageBudget = budgets.pages?.[page];
    if (!pageBudget) {
      failures.push(`${page}: missing page budget`);
      continue;
    }
    const comparisons = [
      ['performanceScore', 'minPerformanceScore', 'min'],
      ['lcpMs', 'maxLcpMs', 'max'],
      ['cls', 'maxCls', 'max'],
      ['tbtMs', 'maxTbtMs', 'max'],
      ['totalBytes', 'maxTotalBytes', 'max'],
      ['htmlBytes', 'maxHtmlBytes', 'max'],
      ['cssBytes', 'maxCssBytes', 'max'],
      ['jsBytes', 'maxJsBytes', 'max'],
      ['totalRequests', 'maxRequests', 'max'],
      ['domNodes', 'maxDomNodes', 'max'],
    ];
    for (const [metricName, budgetName, direction] of comparisons) {
      const value = metrics[metricName];
      const limit = pageBudget[budgetName];
      if (!Number.isFinite(limit) || !Number.isFinite(value)) continue;
      if (direction === 'max' && value > limit) failures.push(`${page}: ${metricName} ${round(value)} > ${limit}`);
      if (direction === 'min' && value < limit) failures.push(`${page}: ${metricName} ${round(value)} < ${limit}`);
    }
  }
}

const compact = Object.fromEntries(pageKeys.map((page) => [page, Object.fromEntries(
  Object.entries(result[page].median).map(([key, value]) => [key, round(value, key === 'cls' ? 4 : 2)])
)]));

console.log(`PERFORMANCE_BASELINE_JSON=${JSON.stringify(compact)}`);
console.log(JSON.stringify({ mode: budgets.mode, pages: compact }, null, 2));

if (failures.length) {
  console.error('Performance budget failures:');
  failures.forEach((failure) => console.error(`- ${failure}`));
  process.exit(1);
}
