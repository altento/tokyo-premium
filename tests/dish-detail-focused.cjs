const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');
const phpBase = process.env.TOKYO_BASE_URL || 'http://127.0.0.1:8098';
const staticBase = process.env.TOKYO_STATIC_BASE_URL || 'http://127.0.0.1:8099/docs';
const evidence = process.env.TOKYO_EVIDENCE_DIR || path.join(__dirname, '..', 'project-docs/visual-qa/menu-dish-2026-11-19/after');
(async () => {
  fs.mkdirSync(evidence, { recursive: true });
  const browser = await chromium.launch();
  try {
    for (const mode of [{name:'php',base:phpBase,menu:'/menu.php',dish:id=>`/pages.php?page=dish&id=${id}`},{name:'static',base:staticBase,menu:'/menu.html',dish:id=>`/dish-${id}.html`}]) {
      const page = await browser.newPage({ viewport:{width:390,height:844}, reducedMotion:'reduce' });
      const errors=[];
      page.on('pageerror',e=>errors.push(e.message));
      page.on('response',r=>{if(r.status()>=400) errors.push(`${r.status()} ${r.url()}`)});
      await page.goto(mode.base+mode.menu,{waitUntil:'domcontentloaded'});
      const catalog=await page.evaluate(()=>window.DISHES.map(d=>({id:Number(d.id),name:d.name})));
      for(const dish of catalog){
        const response=await page.goto(mode.base+mode.dish(dish.id),{waitUntil:'domcontentloaded'});
        assert.equal(response.status(),200);
        assert.equal(await page.locator('h1').innerText(),dish.name);
        await page.locator('#dish-main-image').evaluate(i=>i.decode());
        assert.ok(await page.locator('h1').evaluate(e=>e.scrollWidth<=e.clientWidth+1&&e.getBoundingClientRect().right<=innerWidth+1),`title bounds: ${dish.name}`);
      }
      await page.goto(mode.base+mode.dish(3),{waitUntil:'domcontentloaded'});
      const thumbnails=page.locator('[data-detail-gallery-image]');
      assert.equal(await thumbnails.count(),3);
      assert.equal(new Set(await thumbnails.evaluateAll(nodes=>nodes.map(n=>n.dataset.detailGalleryImage))).size,3,'three distinct photographs');
      await thumbnails.nth(1).click();
      assert.equal(await thumbnails.nth(1).getAttribute('aria-pressed'),'true');
      assert.equal(await page.locator('#dish-main-image').getAttribute('src'),await thumbnails.nth(1).getAttribute('data-detail-gallery-image'));
      await page.evaluate(()=>window.TokyoCart.clear());
      await page.locator('[data-qty-step="1"]').click();
      await page.locator('.detail-purchase-group .add-to-cart').click();
      assert.equal(await page.evaluate(()=>window.TokyoCart.get().find(d=>Number(d.id)===3)?.qty),2,'quantity reaches the shared cart');
      await page.waitForFunction(()=>!document.querySelector('.toast.show'));
      for(const width of [320,390,768,1024,1440,1920]){
        await page.setViewportSize({width,height:900});
        await page.evaluate(async()=>{await Promise.all([...document.querySelectorAll('main img')].map(i=>{i.loading='eager';return i.decode()}));await document.fonts.ready});
        assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth),width,`${mode.name} overflow at ${width}`);
        if([390,1440].includes(width)) {
          const style=await page.addStyleTag({content:'.site-header{position:relative!important;top:auto!important}'});
          await page.evaluate(()=>window.scrollTo({top:0,behavior:'instant'}));
          await page.screenshot({path:path.join(evidence,`03-dish-${mode.name}-${width}.jpg`),fullPage:true,type:'jpeg',quality:80});
          await style.evaluate(node=>node.remove());
        }
      }
      assert.deepEqual(errors,[]);
      console.log(`PASS: ${mode.name} ${catalog.length} public dish routes, real gallery, quantity/add and six viewport widths`);
      await page.close();
    }
  } finally {await browser.close()}
})().catch(e=>{console.error(e);process.exit(1)});
