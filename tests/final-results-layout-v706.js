'use strict';

const fs = require('fs');
const assert = require('assert');
let chromium;
try {
  ({ chromium } = require('playwright'));
} catch (_) {
  console.log('Final Full Results layout checks skipped: Playwright is not installed.');
  process.exit(0);
}

const source = fs.readFileSync('live-display/final-relative-placement.php', 'utf8');
const css = source.match(/<style>([\s\S]*?)<\/style>/)?.[1];
assert(css, 'Final Full Results CSS must be present');

const judges = Array.from({ length: 8 }, (_, index) =>
  `<th>J${index + 1}<br><small>Judge ${index + 1}</small></th>`
).join('');
const judgeMarks = Array.from({ length: 8 }, (_, index) =>
  `<td>${index < 5 ? index + 1 : '<span class="nr">NR</span>'}</td>`
).join('');
const rows = Array.from({ length: 12 }, (_, index) => `
  <tr>
    <td>${index < 10 ? `#${index + 1}` : '—'}</td>
    <td class="aud-couple"><div class="aud-couple-row">
      <span class="aud-person"><strong>BIB ${101 + index}</strong><img alt="leader flag"><span>Alexandra</span></span>
      <span class="aud-amp">&amp;</span>
      <span class="aud-person"><strong>BIB ${201 + index}</strong><img alt="follower flag"><span>Christopher</span></span>
    </div></td>
    ${judgeMarks}
  </tr>`).join('');

const html = `<!doctype html><html><head><meta charset="utf-8"><style>${css}</style></head><body><div class="stage">
  <div class="event">4th ASIA Open Salsa JACK &amp; JILL COMPETITION 2026</div>
  <div class="meta">SALSA OPEN · FINAL</div>
  <h1>FINAL RELATIVE PLACEMENT · PAGE 1 OF 4<span class="legend">NR = NOT RANKED BY THIS JUDGE</span></h1>
  <div class="wrap"><table style="--row-count:12"><thead><tr><th>Final</th><th>Couple</th>${judges}</tr></thead><tbody>${rows}</tbody></table></div>
</div></body></html>`;

(async () => {
  if (!fs.existsSync(chromium.executablePath())) {
    console.log('Final Full Results layout checks skipped: Playwright Chromium is not installed.');
    return;
  }
  const browser = await chromium.launch({ headless: true });
  try {
    for (const viewport of [{ width: 1363, height: 936 }, { width: 1080, height: 1920 }]) {
      const page = await browser.newPage({ viewport });
      await page.setContent(html);
      const measurements = await page.evaluate(() => {
        const box = selector => document.querySelector(selector).getBoundingClientRect();
        const style = selector => getComputedStyle(document.querySelector(selector));
        return {
          scrollWidth: document.documentElement.scrollWidth,
          scrollHeight: document.documentElement.scrollHeight,
          coupleDisplay: style('.aud-couple').display,
          couple: box('.aud-couple'),
          name: box('.aud-person span:last-child'),
          judge: box('th:nth-child(3)'),
          bibFont: parseFloat(style('.aud-person strong').fontSize),
          nameFont: parseFloat(style('.aud-person span:last-child').fontSize),
          table: box('table'),
        };
      });
      assert(measurements.scrollWidth <= viewport.width, `${viewport.width}x${viewport.height} must not scroll horizontally`);
      assert(measurements.scrollHeight <= viewport.height, `${viewport.width}x${viewport.height} must not scroll vertically`);
      assert.strictEqual(measurements.coupleDisplay, 'table-cell');
      assert(measurements.couple.width >= viewport.width * 0.44, 'Couple column must retain readable width');
      assert(measurements.name.width > 20, 'Competitor name must have non-zero readable width');
      assert(measurements.judge.width > 30, 'Each paged judge column must remain readable');
      assert(measurements.bibFont > measurements.nameFont, 'BIB typography must be larger than competitor name typography');
      assert(measurements.table.left >= viewport.width * 0.049, 'Table must respect the 5% left safe area');
      assert(measurements.table.right <= viewport.width * 0.951, 'Table must respect the 5% right safe area');
      assert(measurements.table.bottom <= viewport.height * 0.901, 'Table must respect the 10% bottom safe area');
      await page.close();
    }
  } finally {
    await browser.close();
  }
  console.log('Final Full Results layout checks passed at landscape and portrait projection sizes.');
})().catch(error => {
  console.error(error);
  process.exit(1);
});
