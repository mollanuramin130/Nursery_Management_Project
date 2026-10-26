/* Run with PLAYWRIGHT_MODULE pointing to an installed playwright package.
 * Uses each app's real generated CSS; run both production builds first.
 * This is a component/token check, not a full WCAG conformance audit.
 */
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const {chromium} = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
function luminance(rgb) {
  const v = rgb.match(/[\d.]+/g).slice(0, 3).map(Number).map(c => {
    c /= 255; return c <= 0.04045 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
  });
  return v[0] * .2126 + v[1] * .7152 + v[2] * .0722;
}
function ratio(a,b) { const [hi,lo]=[luminance(a),luminance(b)].sort((a,b)=>b-a); return (hi+.05)/(lo+.05); }
(async () => {
  const browser = await chromium.launch({headless:true, args:['--no-sandbox']});
  try {
    for (const app of ['nursery-web','nursery-admin']) {
      const build = path.join('apps', app, '.next');
      const html = fs.readFileSync(path.join(build, 'server/app/login.html'), 'utf8');
      const styles = [...new Set([...html.matchAll(/href="\/_next\/(static\/css\/[^"]+\.css)"/g)].map(m=>m[1]))];
      assert.ok(styles.length, 'Expected CSS linked by the current build');
      const css = styles.map(f=>fs.readFileSync(path.join(build,f),'utf8')).join('\n');
      const prefix = app === 'nursery-web' ? 'color' : 'admin';
      for (const width of [390, 1440]) {
        const page = await browser.newPage({viewport:{width,height:900}});
        await page.setContent(`<style>${css}</style><a id="cta" href="#" class="text-white bg-[var(--${prefix}-primary)]">Shop plants</a><button id="button" class="text-sm font-semibold text-white bg-[var(--${prefix}-primary)]">Add to cart</button><p id="muted" style="color:var(--${prefix}-muted);background:var(--${prefix}-surface-muted)">Supporting text</p>`);
        for (const id of ['cta','button','muted']) {
          const result=await page.locator('#'+id).evaluate(el=>{const s=getComputedStyle(el);return {fg:s.color,bg:s.backgroundColor,weight:s.fontWeight,size:s.fontSize}});
          const contrast=ratio(result.fg,result.bg);
          console.log(JSON.stringify({app,width,id,...result,contrast:Number(contrast.toFixed(2))}));
          assert.ok(contrast>=4.5,`${app} ${id}: ${contrast.toFixed(2)} < 4.5`);
          if(id==='button') assert.equal(result.weight,'600','Button font utility must override base reset');
        }
        await page.close();
      }
    }
  } finally {await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1});
