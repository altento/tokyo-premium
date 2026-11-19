const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');
const evidence = process.env.TOKYO_EVIDENCE_DIR || path.join(__dirname,'../project-docs/visual-qa/booking-gallery-2026-11-19/after');
(async()=>{
  fs.mkdirSync(evidence,{recursive:true});
  const browser=await chromium.launch();
  try{
    for(const mode of [{name:'php',base:process.env.TOKYO_BASE_URL||'http://127.0.0.1:8098',route:p=>'/pages.php?page='+p},{name:'static',base:process.env.TOKYO_STATIC_BASE_URL||'http://127.0.0.1:8099/docs',route:p=>'/'+p+'.html'}]){
      const page=await browser.newPage({reducedMotion:'reduce'});
      const errors=[];page.on('pageerror',e=>errors.push(e.message));
      for(const route of ['booking','gallery'])for(const width of [320,390,768,1024,1440,1920]){
        await page.setViewportSize({width,height:900});
        const response=await page.goto(mode.base+mode.route(route),{waitUntil:'domcontentloaded'});
        assert.equal(response.status(),200);
        if(route==='booking')await page.waitForSelector('.tp-seat-plan');
        await page.evaluate(async()=>{await Promise.all([...document.querySelectorAll('main img[src]')].map(i=>{i.loading='eager';return i.decode()}));await document.fonts.ready});
        const state=await page.evaluate(()=>({
          width:innerWidth,scrollWidth:document.documentElement.scrollWidth,
          clipped:[...document.querySelectorAll('main h1,main h2,.gallery-tile>span')].filter(e=>{const range=document.createRange();range.selectNodeContents(e);const box=e.getBoundingClientRect();return [...range.getClientRects()].some(r=>r.left<Math.max(0,box.left)-2||r.right>Math.min(innerWidth,box.right)+2)}).map(e=>e.textContent),
          noteSpansForm:(()=>{const form=document.querySelector('.booking-form');const note=form?.querySelector('.school-note');return !form||note.getBoundingClientRect().width>=form.clientWidth*.9})()
        }));
        assert.equal(state.scrollWidth,width,`${mode.name} ${route} overflow at ${width}`);
        assert.deepEqual(state.clipped,[],`${mode.name} ${route} heading bounds at ${width}`);
        assert.ok(state.noteSpansForm,'booking notice spans a complete form row');
        if(mode.name==='static'&&[390,1440].includes(width)){
          const style=await page.addStyleTag({content:'.site-header{position:relative!important;top:auto!important}'});
          await page.evaluate(()=>scrollTo({top:0,behavior:'instant'}));
          await page.screenshot({path:path.join(evidence,`${route}-${width}.jpg`),fullPage:true,type:'jpeg',quality:80});
          await style.evaluate(e=>e.remove());
        }
      }
      assert.deepEqual(errors,[]);await page.close();
      console.log(`PASS: ${mode.name} booking/gallery at six widths; heading bounds, photographs and form alignment`);
    }
  }finally{await browser.close()}
})().catch(e=>{console.error(e);process.exit(1)});
